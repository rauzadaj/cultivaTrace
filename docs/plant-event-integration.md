# PlantEvent — PostgreSQL 16 et qualification P0-06

> **Suivi du 15 septembre 2026 :** les nouvelles écritures JSONB sont corrigées
> après validation explicite ; 28 tests PostgreSQL passent. Voir
> [le correctif et ses limites historiques](plant-event-jsonb-fix.md). Le résultat
> BLOCKED et les preuves ci-dessous restent l'instantané antérieur au correctif.

> Rapport de qualification initiale sur `58081ea` (SHA-256). La PR a ensuite été
> portée sur `869e592`, qui utilise HMAC-SHA256 : cet algorithme existant est conservé.
> Voir [le rapport du portage](pr-integration.md) pour les résultats à jour, les
> 37 migrations et les preuves JSONB HMAC. Les exemples chiffrés ci-dessous restent
> les preuves de la base initiale, sans réécriture.

Résultat du 14 septembre 2026 : **P0-06 BLOCKED**. Les 32 migrations passent sur
PostgreSQL 16.15 (167 requêtes SQL). Les protections ORM/SQL, le verrou par plant,
le premier append concurrent et les rollbacks sont vérifiés. Les deux tests
JSONB échouent réellement ; ils restent des tests bloquants, sans skip ni
`continue-on-error` en CI. Aucune migration ou donnée historique n'est modifiée.

## Exécution

Les tests rapides restent dans `phpunit.xml.dist` et excluent `tests/PostgreSQL`.
Ils utilisent SQLite et SchemaTool. Ne pas diriger `ApiTestCase` vers une base
existante : son ancienne branche PostgreSQL recrée le schéma public.

Le niveau PostgreSQL utilise uniquement les migrations réelles, jamais SchemaTool.
Il exige une URL administrative **explicite** et un serveur PostgreSQL 16 jetable
dont l'utilisateur peut créer des bases. `DATABASE_URL` n'est jamais utilisé pour
choisir la connexion administrative.

```bash
docker compose -f compose.postgres-tests.yaml up -d --wait
export POSTGRES_TEST_ADMIN_URL='postgresql://cultivatrace_test:local-integration-only@127.0.0.1:55432/postgres'
composer test:postgresql
docker compose -f compose.postgres-tests.yaml down
```

Sous PowerShell, affecter l'URL avec `$env:POSTGRES_TEST_ADMIN_URL = '...'`.
Le runner `bin/test-postgresql.php` fonctionne sur Windows et Linux et propage
le code de sortie de PHPUnit. Il génère `cultivatrace_it_<16 hex>_test`, crée
cette base, applique `doctrine:migrations:migrate`, lance la suite dédiée et
supprime **cette seule base** dans un `finally`, y compris après un échec.
Le suffixe `_test` de Doctrine est pris en compte. Le bootstrap et les tests
vérifient le nom effectif de la base ; le worker vérifie également sa connexion.
Une interruption brutale du processus/serveur peut empêcher `finally` : le service
Docker dédié utilise un stockage temporaire, supprimé à l'arrêt du conteneur.

La CI Symfony exécute successivement SQLite puis ce runner avec son service
PostgreSQL 16. Elle conserve `var/plant-event-evidence/` comme artefact même si
les tests échouent. La configuration Compose historique reste sur PostgreSQL 13
avec son volume existant ; elle n'est ni utilisée ni convertie par ce lot.

Sur le poste de qualification, Docker Desktop ne démarrait pas. Les
[binaires Windows officiels distribués par EDB](https://www.enterprisedb.com/download-postgresql-binaries)
ont servi à initialiser PostgreSQL **16.15**, sous `var/postgresql16-data`,
en écoute sur `127.0.0.1:55432`. Aucun service PostgreSQL existant n'a été modifié.
Les bases de chaque exécution ont été supprimées après les tests.

## Preuve JSONB : échec conservé

Payload original :

```json
{"quantity":10,"unit":"g","metadata":{"source":"manual","operator":"test"}}
```

Après `appendEvent → flush → clear → reload` :

```json
{"unit":"g","metadata":{"source":"manual","operator":"test"},"quantity":10}
```

Pour l'événement `338f1550-aac5-4940-8324-6060b97fbf00` à l'instant Unix
`1789380816`, prédécesseur de 64 zéros :

- Hash attendu, stocké lors de l'append : `5e3c2cd8c77c55768f88661c4bf47f1eec3695ab7cebedbd6c44ab8bb4697a6c`.
- Hash obtenu en recalculant après reload : `2d067b34009e106337a14264a8ee2b5f2ee99f196efd85e91eb12427fe71c2f6`.
- Vérification : `valid=false`, `checked=0`.

Même échec avec une séquence de trois événements. Les payloads à une seule clé
passent après reload, avec un ou trois événements. La cause observée est l'ordre
des clés transformé par JSONB, alors que `computeHash()` encode l'ordre du tableau
PHP. Le test compare le contenu rechargé réellement, sans normalisation.
Les [preuves à un événement](evidence/p0-06/jsonb-1.json) et
[trois événements](evidence/p0-06/jsonb-3.json) contiennent tous les champs
nécessaires pour recalculer ces deux hashes.

**Décision requise avant remédiation** : choisir un format/versionnement compatible
et une politique pour l'historique déjà stocké. Aucune récupération de l'ordre
original ne peut être présumée à partir de JSONB seul. Aucun rehash, changement
d'enveloppe, ajout au hash, payload ou timestamp historique réécrit dans ce lot.
Le passage des migrations sur une base vide ne qualifie pas une migration sur
des données de production préexistantes.

## Preuve de concurrence et correction autorisée

Avant correction, deux transactions et deux PID PostgreSQL distincts lisent H0
et créent chacun un événement avec `hashPrevious=H0`. Le cas sans prédécesseur
produit deux événements avec 64 zéros. Voir
[avant, H0 existant](evidence/p0-06/concurrency-existing.json) et
[avant, premier événement](evidence/p0-06/concurrency-first.json).

Le test final utilise un processus PHP enfant avec son propre Kernel/EntityManager
et sa propre connexion. Une barrière STDIN assure que les deux transactions ont
lu H0 avant l'append A. A insère sans committer ; B tente son append ; le parent
observe `pg_stat_activity.wait_event_type=Lock`, puis committe A et attend B.
Il vérifie les PID distincts, `B.previous=A.self` et la chaîne après reload.

La correction verrouille la ligne **Plant** avec `FOR NO KEY UPDATE` avant la
relecture du dernier événement, à l'intérieur de `EntityManager::wrapInTransaction()`.
Le parent existe même si aucun événement n'existe. Le verrou inclut l'ID et le
tenant du plant, reste détenu jusqu'au commit externe et ne change aucun filtre
tenant. Ce mode évite un conflit inutile avec les verrous FK `KEY SHARE`.
La [documentation PostgreSQL sur les verrous](https://www.postgresql.org/docs/16/explicit-locking.html)
décrit ces modes. La garantie concerne les appels autorisés à `appendEvent()` ;
un INSERT SQL arbitraire ne valide toujours pas la chaîne.

La transaction doit être `READ COMMITTED`, vérifié par `SHOW transaction_isolation`.
Un snapshot `REPEATABLE READ` antérieur au verrou pourrait encore lire un ancien
prédécesseur ; ce mode est explicitement rejeté et testé, sans modifier l'isolation
du caller. Aucune reprise automatique d'une transaction métier n'est introduite.
Preuves corrigées : [H0 existant](evidence/p0-06/concurrency-existing-after.json),
[premier événement](evidence/p0-06/concurrency-first-after.json).

## Ordre et transactions

`occurredAt` conserve temporairement le décalage historique de +1 seconde pour les
nouveaux événements rapprochés. Le verrou sérialise les écritures, mais n'ajoute
pas d'ordre persistant : avec des dates égales, un UUIDv4 ne restitue pas l'ordre
d'append. Supprimer ce décalage casserait donc potentiellement `findLastForPlant`
et `findByPlantOrderedAsc`. Préparer un ordre technique distinct en P1-02, sans
ajouter ici une colonne ou modifier des dates existantes.

`appendEvent()` conserve sa signature et son flush pour les callers existants.
Le flush est désormais effectué par `wrapInTransaction` pendant le verrou.
Un appel autonome committe ; un appel dans une transaction externe ne fait que
libérer un savepoint. Le rollback externe annule ensemble mutation métier et
événement. Une erreur JSON avant flush ou une contrainte SQL pendant flush annule
l'appel et ferme/efface l'EntityManager, empêchant un flush tardif accidentel.
Le caller doit rollback sa transaction externe et obtenir un nouvel EntityManager
en cas d'échec ; les objets PHP détachés ne sont pas restaurés automatiquement.

P1-02 peut utiliser une transaction externe Doctrine DBAL avant toute mutation,
puis appeler l'API existante : un flush et un commit métier suffisent. L'ancien
flush n'empêchait pas à lui seul cette atomicité ; le défaut est surtout le
placement de la frontière transactionnelle. Les processors qui persistent déjà
avant l'append, et les workflows sans transaction externe, restent à traiter.
Ils ne sont pas convertis en masse dans ce lot.

## Vérificateur et résultats

Le test préalable montrait deux corruptions de `hashPrevious` acceptées : premier
événement et prédécesseur intermédiaire. `verify()` compare désormais le champ
stocké au prédécesseur réel (ou aux 64 zéros initiaux), en plus du recalcul existant.
`computeHash()` reste identique. Les corruptions testées sont des objets en mémoire,
sans désactivation du trigger ; hashSelf et payload corrompus sont aussi détectés.

Suite PostgreSQL : **15 tests, 80 assertions, 2 échecs JSONB**, zéro erreur ou skip.
Les autres contrôles passent : migrations/INSERT, UPDATE/DELETE SQL, UPDATE/DELETE
ORM, reload à une clé, concurrence avec/sans H0, rollback externe, deux échecs
d'append et refus d'isolation incompatible. Les tests ORM rapides prouvent aussi
le refus sur SQLite sans trigger, indépendamment de la protection PostgreSQL.
