# PlantEvent — correction des nouvelles écritures JSONB

Correction autorisée le 15 septembre 2026 pour la PR #287, après les deux échecs
du run `34834708747` sur `531e12f`. **Nouvelles écritures : corrigées et qualifiées.
P0-06 : PARTIAL**, car les événements historiques déjà invalides ne sont pas
réparés et les données de production n'ont pas été inspectées.

## Comportement corrigé

Avant ce correctif, `appendEvent()` signait le payload dans son ordre PHP puis
PostgreSQL JSONB réordonnait ses clés. Après relecture Doctrine, le même événement
ne produisait plus le HMAC stocké. Les preuves de cet échec restent inchangées
dans `docs/evidence/pr-main/jsonb-1.json` et `jsonb-3.json`.

Pour les nouveaux événements PostgreSQL uniquement, `PlantEventRepository`
prépare désormais `payload` et `photoUrls` **avant** leur signature :

1. Encoder avec le type JSON Doctrine installé, passer par JSONB PostgreSQL,
   puis décoder avec le même type, comme le fera la relecture ORM.
2. Comparer les documents JSONB original et préparé avec `IS NOT DISTINCT FROM`.
   Refuser toute conversion qui change la structure ou les valeurs.
3. Vérifier qu'un second aller-retour produit exactement le même JSON sérialisé.
4. Affecter les valeurs préparées au nouvel événement puis appeler le HMAC
   historique, sans modifier son enveloppe ni ses options d'encodage.

Les opérations restent dans la transaction existante, sous le verrou Plant.
Deux requêtes de préparation sont exécutées par champ JSON non nul ; `null`
reste SQL NULL sans requête supplémentaire. Aucun UPDATE après INSERT n'est
nécessaire. Le chemin SQLite reste inchangé et conserve sa suite rapide.

Les objets vides imbriqués et les objets à clés numériques qui deviendraient
des listes sont refusés : par exemple `{"value":{}}` ne devient pas
`{"value":[]}`. Les valeurs non sérialisables sont également refusées.
L'exception est celle d'API Platform (`Metadata\Exception\InvalidArgumentException`),
pour conserver HTTP 400 dans ses opérations et dans le subscriber API existant.
Les contrôleurs Harvest/Destruction conservent leur traitement existant HTTP 422
de la même famille d'exceptions. Les erreurs d'infrastructure SQL ne sont pas
converties en erreurs de saisie.

Un refus ferme l'EntityManager et annule les changements en attente dans la
transaction d'append. Si l'appelant possède une transaction externe, il doit
toujours la rollback et recréer le manager après erreur. Les frontières métier
qui auraient déjà effectué un commit restent le sujet P1-02.

## Compatibilité et historique

`HashChainService.php` ne change pas dans ce correctif : même HMAC-SHA256, même
secret, mêmes champs, même vérificateur strict. Aucun hash, payload, photo ou
timestamp historique n'est réécrit. Aucun changement de schéma ou migration.

Les tests créent deux fixtures historiques avec l'ancien chemin de signature,
avant INSERT : une valide et une invalidée par JSONB. Après ajout d'un nouvel
événement, ils vérifient que toutes les colonnes historiques restent identiques,
que la chaîne valide reste valide et que la chaîne invalide reste signalée à
son ancien événement. Aucun fallback ne pardonne un hash historique incorrect.

Ce correctif dépend de la représentation PostgreSQL/PHP/Doctrine qualifiée.
Il ne définit pas un format canonique indépendant de leurs versions. Les mises
à niveau doivent rejouer cette suite et qualifier l'historique séparément.
Un format versionné durable et la remédiation des anciennes chaînes restent à
décider ; ils ne sont pas introduits implicitement par ce correctif.

## Qualification locale

PHP 8.4.11, PostgreSQL 16.15, vraies migrations, base temporaire supprimée en fin
de test : **37 migrations / 177 requêtes de migration, 28 tests / 195 assertions,
zéro échec et zéro skip**.

- Les deux tests multi-clés initialement rouges gardent leur assertion positive
  de vérification après relecture, sur un et trois événements.
- Les cas supplémentaires couvrent les clés imbriquées, ordre et doublons des
  listes et photos, Unicode, SQL NULL et liste vide, zéro négatif, nombres
  exponentiels, limites entières/flottantes, clés numériques non contiguës.
- Les deux conversions qui changeraient le document sont refusées avec rollback,
  absence d'événement et EntityManager fermé.
- Concurrence réelle à deux connexions, premier événement, rollback externe et
  protections append-only restent verts ; concurrence et rollback emploient
  maintenant des payloads multi-clés imbriqués.
- Les fixtures historiques valides et invalides conservent leur statut initial.

Les nouvelles preuves sont dans `docs/evidence/p0-06-jsonb-fix/`. Les anciennes
preuves rouges restent des instantanés datés, sans remplacement.

La suite rapide passe : **317 tests / 845 assertions, 1 skip PDF/Gotenberg**.
Les résultats PHPStan et CI de la PR sont consignés dans
[le rapport d'intégration](pr-integration.md).
