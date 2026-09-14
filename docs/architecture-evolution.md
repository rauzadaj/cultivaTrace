# CultivaTrace — évolution de l’architecture

Audit du 14 septembre 2026, référence initiale `58081ea` (branche locale `main`). Le code et le lock Composer priment sur les descriptions historiques. Audit des sources, configurations, migrations, tests et parcours frontend ; aucune inspection des données de production ni certification réglementaire. Les éléments non suivis présents au départ (`.claude/`, `fixtures/FarmScaleFixtures.php`, `frontend/test-results/`) sont hors périmètre.

**État après le deuxième lot : P0-05 DONE, P0-06 BLOCKED (JSONB démontré).** Les constats initiaux ci-dessous restent l'instantané de l'audit ; les résultats actualisés sont dans « Exécution du deuxième lot » en fin de document. Aucun nouvel audit général n'a été réalisé.

## 1. Current architecture

Monolithe Symfony, API Platform et Doctrine, avec frontend Vue 3/TypeScript/Pinia/Quasar/Vite dans `frontend/`. `config/services.yaml` charge les services sous `App\`, et `config/packages/doctrine.yaml` mappe les attributs sous tout `src/`. Deux familles de contrôleurs coexistent : `src/Controller/` et `src/Infrastructure/Http/Controller/`.

| Zone | Réalité observée | Sources |
| --- | --- | --- |
| Runtime | PHP `>=8.4`, plateforme Composer simulée `8.4.0`, image `php:8.4-fpm` ; CLI locale 8.4.11 | `composer.json`, `Dockerfile` |
| Dépendances verrouillées | Symfony 8.0 (composants de 8.0.0 à 8.0.8), API Platform **4.3.3** malgré contrainte `^4.2`, ORM 3.6.2, DoctrineBundle 3.2.2, migrations bundle 3.7.0 | `composer.lock` |
| Cultivation legacy | `Plant`, `Room`, `Farm`, `Strain`, `HarvestRecord`, `InputRecord`, `DestructionIntent` ; API Resources et processors ; récolte/destruction explicites | `src/Entity/`, `src/State/`, `src/Service/{Harvest,Destruction}WorkflowService.php` |
| Cultivation DDD | `Crop`, `JournalEntry`, `Genetic`, value objects ; state machine `crop_lifecycle` et analytics | `src/Domain/Cultivation/`, `src/Application/Cultivation/`, `config/packages/workflow.yaml` |
| Troisième représentation Plant | Modèle `App\Domain\Plant\Plant`, table `plants`, `organizationId`, `batchId` scalaire, origine et workflow propres ; aucun pont avec `App\Entity\Plant` | `src/Domain/Plant/`, `src/Infrastructure/Persistence/PlantRepository.php` |
| Traceability | `PlantEvent` par plant, chaînage SHA-256 ; `JournalEntry` séparé, sealed après persistance/chargement | `src/Repository/PlantEventRepository.php`, `src/Service/HashChainService.php`, `src/Domain/Cultivation/Model/JournalEntry.php` |
| Canada | CSV interne HTTP par plant et **module CTLS CLI déjà séparé** (source, normalisation, mapping, validation, exporter, snapshot officiel) | `src/Controller/CTSReportController.php`, `src/Compliance/CTLS/`, `src/Command/ExportCtlsCommand.php` |
| Identity / Organization | JWT Lexik, refresh tokens hashés, vérification email, invitations, throttling, suspension et rôles ORG_USER/ORG_ADMIN/SUPER_ADMIN/API | `src/Security/`, `src/Service/Auth/`, `config/packages/security.yaml` |
| Billing / KYB | Stripe SDK 19.4.1, checkout et webhook, limites côté serveur, revue KYB ; simulation spécifique `ctls_dev` pilotée par flag | `src/Service/{Stripe,Kyb,PlanLimits}Service.php`, `src/Service/License/LicenseGuard.php` |
| Données / temps réel | PostgreSQL cible 16 ; `sensor_reading` via DBAL, TimescaleDB avec fallback ; Mercure privé par organisation | `src/Repository/SensorReadingRepository.php`, `src/Service/MercureService.php`, migrations |
| Exécution différée | Messenger installé, seul transport `sync` actif, aucun pipeline métier async configuré ; Scheduler licences | `config/packages/messenger.yaml`, `src/Scheduler/LicenseExpirationScheduler.php` |
| Exploitation | nginx pour CORS, Gotenberg pour PDF, Sentry et logs PSR ; stockage local/configurable des documents et exports | `docker/nginx/railway.conf.template`, `src/Service/Storage/ArtifactStorage.php`, `src/Infrastructure/Monitoring/` |

L’ancien `docs/architecture-audit.md` décrit notamment l’absence de RBAC/tests et les ressources Plot/CropActivity : ces constats ne doivent pas être recopiés comme état actuel. Les anciens incidents de `AUDIT_QUESTIONS.md` constituent un historique, pas des résultats de tests actuels.

## 2. Strengths

- Monolithe cohérent avec les opérations transactionnelles métier ; aucune justification observée pour Go, Kafka, Kubernetes, une nouvelle base ou des microservices.
- Contrôles d’organisation sur les ressources, filtre Doctrine activé après authentification, priorité effective du listener **-10** ; contrôles tenant supplémentaires dans les endpoints PDF/reporting et les processors de relations.
- `PlantEvent` exposé uniquement en GET ; migration `Version20260418120000` interdisant UPDATE/DELETE en PostgreSQL. `JournalEntry` possède en plus un scellement objet et un subscriber ORM. Conserver ces protections cumulatives.
- Récolte et confirmation de destruction regroupent modifications et événement dans une transaction. Limites de plan côté serveur avec erreur HTTP 402 ; signature Stripe réellement vérifiée par `Webhook::constructEvent()`.
- Le convertisseur CTLS **refuse les lignes contenant une activité sans mapping** : ne pas remplacer cette sécurité par des zéros supposés. Snapshot, rapport d’erreurs et tests dédiés constituent une base réutilisable.
- Authentification, isolation, règles de licence, workflow, CTLS, routing et stores disposent déjà de tests ; Playwright contient un parcours produit, mais avec API simulée.

## 3. Technical debt

Dette constatée à l’état initial, avant le lot P0 décrit en section 10 :

1. Les scripts `composer phpstan` et `npm run type-check` sont absents. Aucun fichier PHPStan ni baseline n’est suivi, contrairement au présupposé de la demande. `phpunit/phpunit` est 10.5.63, Vitest 3.2.4 et Vite 4.5.14 dans les dépendances installées. Ne pas inventer une baseline existante, ni masquer ce manque avec un script qui réussit sans analyser.
2. Les couches DDD et legacy restent indépendantes ; les enums Plant/Domain Plant/Crop diffèrent. `batchCode` ou `batchId` ne constituent pas un modèle de lots. Aucun déplacement massif ne résoudrait ces divergences.
3. `PlantStateProcessor::process()` persiste le plant avant d’ajouter ses événements ; échec d’append possible après modification durable. Les changements de status, date de germination, RFID ou strain sont sérialisables en écriture, mais seuls création/stage/room génèrent un événement. Le commentaire « uniquement stage, room, rfidTag » ne reflète pas les groupes réels de `Plant`.
4. `InputRecordStateProcessor` persiste l’intrant sans appeler `appendEvent()`, malgré le type `input_record` annoncé dans `PlantEvent`. Pas d’historique de correction explicite.
5. `ReportingExportService` utilise des noms à la seconde, comme `ArtifactStorage::storeKybUpload()` : deux créations simultanées peuvent réutiliser un chemin. Les rapports ne disposent ni d’empreinte du contenu ni de version de mapping.
6. Les rapports hydratent toutes les lignes puis construisent un second tableau avant streaming ; l’export audit et le dashboard lisent des relations actuelles. `SensorReadingController` effectue plusieurs recherches de dernière mesure et une lecture `findRecentForRoom()` dont le résultat n’est pas utilisé.
7. Le scheduler parcourt toutes les organisations, peut renvoyer quotidiennement l’alerte d’une licence déjà expirée, et n’enregistre pas de clé d’idempotence. Messenger ne dispose pas encore de transport d’échec.

### Compatibilité Symfony et plan préalable

La cible à date est **Symfony 8.1**, branche stable maintenue jusqu’à janvier 2027 ; Symfony 8.0 est hors maintenance. Pas de downgrade vers 7.4, ni d’adoption anticipée de 8.2. Sources officielles : [8.1](https://symfony.com/releases/8.1), [8.0](https://symfony.com/releases/8.0).

| Vérification | Résultat |
| --- | --- |
| Contraintes racine | Tous les composants framework et `extra.symfony.require` sont fixés à `8.0.*` |
| API Platform / Doctrine | Versions verrouillées acceptent Symfony `^8.0`, donc 8.1 au sens Composer |
| Bundles | Lexik 3.2.0, Gotenberg 1.2.2, Sentry 5.10.0, Mercure 0.4.2 autorisent également `^8.0` |
| PHP minimum | `composer prohibits symfony/framework-bundle 8.1.6 --locked` révèle **PHP >=8.4.1** pour ce patch, bloqué par `config.platform.php=8.4.0`. La page de branche indique 8.4.0 : les métadonnées du package précis priment pour la résolution |
| Résolution isolée | Copie du manifest/lock dans `var/symfony81-audit`, contraintes 8.1 et plateforme 8.4.11 : `composer update 'symfony/*' -W --dry-run --no-install --no-scripts --no-plugins` réussit. Ce résultat ne prouve pas la compatibilité runtime ; plugins/recettes non exécutés |
| Sécurité | `composer audit --locked` signale des avis sur plusieurs packages Symfony, Twig et guzzlehttp/psr7. Une résolution Symfony seule laisse les avis Guzzle : inclure ce package dans le chantier. Avis ≠ exploitation démontrée de chaque chemin dans CultivaTrace |

Le [guide officiel 8.0 → 8.1](https://raw.githubusercontent.com/symfony/symfony/8.1/UPGRADE-8.1.md) appelle à vérifier les signatures Console, les conversions invalides `ParameterBag`, les interfaces de receivers Messenger et de hiérarchie de rôles, et les exceptions Serializer. La recherche ciblée dans `src/`, `tests/`, `config/` n’a pas trouvé ces extensions/usages directs. Les bundles, autowiring et dépréciations restent à tester avec les dépendances réellement installées.

Ordre de migration : établir des contrôles reproductibles ; fixer le minimum PHP 8.4 compatible avec le patch choisi et vérifier les images ; mettre ensemble les contraintes directes et Flex en 8.1 ; résoudre Symfony, Twig et Guzzle, sans `--ignore-platform-reqs` ; revoir les recettes sans les écraser ; vérifier conteneur dev/test/prod, JWT, Serializer/API Platform, workflow, Messenger/Mercure et PDF ; exécuter analyse statique, tests PostgreSQL/migrations et frontend ; livrer le lock et prévoir retour au précédent artefact. **Migration préparée mais non appliquée dans le premier lot** : les contrôles de qualité de départ sont incomplets et PostgreSQL/Gotenberg ne sont pas disponibles localement. Le dry-run ne suffit pas à qualifier une migration de propre.

## 4. Compliance gaps

### Règles confirmées et limites de confirmation

Le [guide Health Canada pour titulaires fédéraux](https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/tracking-system/monthly-reporting-guide.html) distingue ce public des distributeurs/détaillants provinciaux et demande des rapports mensuels exacts et complets. La [préparation des rapports](https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/tracking-system/monthly-reporting-guide/before-you-start-submitting-reports.html) comporte les statistiques d’employés et de capacité. Le [guide de soumission](https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/tracking-system/monthly-reporting-guide/submit-reports.html) traite inventaires et corrections. Sources consultées le 14 septembre 2026 ; elles ne valident pas le mapping CultivaTrace ni son applicabilité à un client particulier.

Le dépôt embarque le snapshot `flh-cannabis-tracking-reporting-form-20250318.csv`, avec URL et date de récupération dans `CtlsTemplateProvider`. La liste des champs considérés obligatoires et leurs formats sont **des décisions du code à confronter au cas réel**, pas une validation exhaustive du portail actuel.

| Sujet | Présent dans le code | Hypothèse / donnée manquante / validation externe |
| --- | --- | --- |
| CSV HTTP `/api/compliance/ctsreport` | 15 colonnes internes par plant ; tenant en DQL explicite | Ce fichier n’est pas le template CTLS agrégé. L’UI le présente pourtant comme CSV de conformité |
| Période | Filtre `germinatedAt <= fin du mois` | Pas de photographie à clôture : statut, localisation et récolte actuels peuvent être postérieurs au mois demandé. Fuseau réglementaire, bornes et reprise de soldes à valider |
| Identifiants | Plant ID tronqué à 8 caractères ; room libre ; licence donnée manuellement à la CLI | Pas de correspondance versionnée organisation/site/licence ni identifiant réglementaire de site |
| Destruction | Date/raison vides dans le CSV ; `DestructionIntent` contient confirmation et poids | Déterminer les mouvements et catégories applicables, dates retenues et unités avec un acteur canadien ; ne pas assimiler intention et destruction confirmée |
| Quarantaine | CSV constant `no`, données disponibles sur `InputRecord` | Un résultat d’intrant ne définit pas à lui seul l’état réglementaire d’un stock ou d’un lot |
| Inventaire | Poids brut/net par récolte | Aucune ouverture/clôture de stock, ventilation conditionné/non conditionné ni journal de mouvements |
| Mapping officiel | `CtlsRowBuilder` refuse chaque champ d’activité non vide ; export possible pour source sans activité avec métadonnées | Source vide ne prouve pas absence de stock ou d’obligation. Nécessité de distinguer donnée inconnue, zéro confirmé et champ inapplicable |
| Validation | En-têtes et quelques formats, rapport JSON des erreurs | Pas de rapprochement de masses/soldes, preuve de complétude, approbation métier ni acceptation CTLS |
| Historique | `ReportExport` interne séparé, fichier de sortie CLI | Aucune soumission versionnée, accusé de réception, amendement lié à l’original, ou snapshot de sources |

La conversion CTLS reste bloquée pour l’activité ; le lot P0 clarifie le caractère interne/provisoire du téléchargement existant. Il ne remplit ni destruction ni quarantaine par supposition. Les délais et ratios codés dans `DestructionIntent`/`DestructionWorkflowService` doivent également être validés par juridiction avant toute revendication canadienne.

## 5. Traceability gaps

### Usages et invariants existants

Écritures de production : `PlantStateProcessor` (germination, changement de stage, déplacement), `HarvestWorkflowService` (harvest), `DestructionWorkflowService` (intention et confirmation). Lectures : `HashChainService`, `PlantReportController`, `ReportingExportService`, `DashboardController`, relation `Plant.events` et API Resource `PlantEvent`. Des fixtures/tests créent aussi directement des événements. Aucune nouvelle écriture ne doit prendre ces fixtures comme exemple : passer par `PlantEventRepository::appendEvent()`.

Invariants à conserver : tenant dérivé du plant, acteur non nullable, append-only SQL, API en lecture seule, ancrage initial de 64 zéros, SHA-256 et vérification historique. Le hash actuel encode `id | JSON(payload) | occurredAt Unix | previousHash` ; **il ne couvre pas** tenant, plant, eventType, acteur, notes, photos ou IP. `verify()` utilise le hash calculé du prédécesseur sans comparer séparément le champ stocké `hashPrevious`. Ces limites ne justifient pas de recalculer les événements existants.

Risques précis :

- `appendEvent()` lit la fin de chaîne puis insère sans verrou ni séquence unique par plant ; deux connexions peuvent choisir le même prédécesseur. L’ajout d’une seconde à `occurredAt` ne sérialise pas les transactions et peut décaler l’horodatage métier.
- La conversion `plant_event.payload` JSON → JSONB dans `Version20260503100000` peut réordonner les clés ; le calcul actuel dépend de leur ordre. Les tests actuels vérifient surtout dans le même EntityManager ou avec un payload à une clé. Il manque la preuve après reload PostgreSQL et migration d’un historique réel.
- À l’état initial, PlantEvent possède des setters sans scellement et **pas de protection ORM équivalente au subscriber JournalEntry**. Le trigger protège une base migrée, pas un schéma créé par SchemaTool ou une base dont les migrations n’ont pas été appliquées.
- L’IP est enregistrée brute depuis `getClientIp()` et exposée dans `plant_event:read` : la pseudonymisation annoncée n’est pas présente. Politique et compatibilité de sortie à décider ; aucun historique n’est réécrit.
- Pas de `recordedAt` distinct, facility historique, version de payload/hash, causalité, idempotency key ni référence `correctsEventId`. Les liens room/strain/user actuels ne préservent pas leurs libellés historiques.

### Stratégie ledger compatible

1. Caractériser le format actuel sur des événements multi-clés rechargés, vérifier le stock existant en lecture seule, tester append parallèle et rejet ORM/SQL. Conserver les preuves des écarts.
2. Après validation humaine des choix protégés : transaction commune métier+append, sérialisation par plant/tenant, ordre dédié distinct de la date métier. Tester deux connexions et rollback, y compris premier événement.
3. Proposer seulement ensuite un ledger **pour un nouveau besoin métier concret**, avec référence typée `(entityType, entityId)`, tenant/site explicites, dates métier/enregistrement, schéma versionné et correctifs append-only. Pas de dual-write non atomique.
4. Une lecture/adaptation de PlantEvent peut exposer un format commun tout en conservant IDs, octets et algorithme historique. Un nouveau format utilise un vérificateur versionné ; aucun rehash rétroactif et aucune liaison avec JournalEntry sans décision enregistrée dans `AUDIT_QUESTIONS.md`.
5. Migrer un type d’événement à la fois après preuve de parité et stratégie de retour. Correction = nouvel événement référencé, jamais UPDATE silencieux de l’ancien.

## 6. Domain gaps

La colonne « CTLS » décrit le besoin de données à qualifier pour les activités réellement exercées, et non une nouvelle obligation légale déduite du nom d’une classe.

| Étape | État actuel | CTLS / priorité métier | Plus tard |
| --- | --- | --- | --- |
| Seed / Clone | Partiel : catalogue séparé, Genetic/Strain ; origine dans le troisième modèle Plant | Provenance, quantités entrantes, lien source → plants à clarifier | Gestion fournisseurs détaillée |
| Plant | Présent legacy ; stage, room, statut, germination et events | Historique fiable à une date, corrections, unité de comptage | Opérations en masse |
| Harvest | Présent : relation 1:1 plant, brut/net DECIMAL en grammes, date et acteur | Rapprocher récolte et entrée matière/stock ; précision, humidité et classes à valider | Récoltes multi-plants/partielles si nécessaire |
| Batch / Lot | Partiel nominal uniquement : Crop.batchCode et Domain Plant.batchId | Généalogie plusieurs plants → lot, splits/merges et références stables absents | Étiquetage et gestion multi-lots avancée |
| Processing | Absent comme transformation matière | Entrées/sorties, pertes et classe produit si activité de transformation | Recettes et ordonnancement |
| Inventory | Absent comme stock et mouvements | Ouverture, mouvements, clôture, site, quantité/unité ; indispensable pour reconstruire les données d’inventaire applicables | Réservations commerciales |
| Transfer / Sale | Absent comme opération métier | Sorties/entrées, contreparties/sites et références si activité concernée | Connecteurs commerciaux/logistiques |
| Destruction | Présent par plant, intention puis confirmation, acteurs/photos/ratio | Matière/lot concerné, date effective, unité et historique de correction à compléter | Destructions multi-lots |
| Inputs | Présent par plant : produit, quantité/unité libres, application, champs labo/quarantaine | Provenance et impacts réellement pertinents ; journalisation manquante | Catalogue produits et approvisionnements |
| Facility / location | Partiel : Farm/Room et organisation | Correspondance licence/site, fuseau, localisation historique et changement de site | Gestion géographique avancée |
| Unités / quantités | Grammes DECIMAL legacy, int grams Crop, strings/float selon usages | Conversions explicites et arrondis déterministes à décider ; pas d’arithmétique réglementaire implicite en float | Bibliothèque d’unités si besoin démontré |
| Corrections / rapports | Pas de correctif métier référencé ni de cycle de soumission | Source immutable, versions de rapport, acteur, justification, remplacement et réception | Synchronisation distante lorsque contrat disponible |

## 7. Security risks

- **Écriture KYB** : Plant/Room/Sensor processors ne contrôlent la licence qu’à la création. DELETE Sensor utilise le remove processor standard. Le rôle autorise ces chemins même avec licence pending/rejected/expired ; le lot P0 applique le guard avant la persistance/suppression. Les autres surfaces DDD et exceptions SUPER_ADMIN doivent faire l’objet d’une matrice explicite, sans connecter les modèles.
- **Filtre tenant** : réellement `enabled: false` au démarrage, puis activé pour requête authentifiée par `TenantListener`. Les CLI et DBAL ne reçoivent aucune protection automatique. La branche SQLite du filtre retourne une clause vide pour UUID invalide : risque fail-open en contexte mal configuré, modification protégée à faire valider. Les commandes actuelles de maintenance globale doivent rester réservées aux opérateurs.
- **Relations / troisième modèle** : le filtre ne reconnaît que `tenantId`, pas `Domain\Plant\Plant.organizationId`. Pas d’API Resource observée sur cette classe ; risque latent en cas d’exposition. Les contraintes FK simples ne garantissent pas l’égalité des tenants entre objets ; API IRIs filtrés et checks applicatifs ne remplacent pas les tests de relations forgées/CLI.
- **SQL capteurs** : `findLatest`, `findHistory`, `findRecentForRoom` n’exigent pas de tenant. Les endpoints contrôlent le Sensor en amont, donc fuite HTTP non démontrée ici ; interfaces fragiles pour un futur worker. Imposer un contexte explicite et tester A/B lors du chantier IoT.
- **Exports** : tenant explicite dans CTS/reporting et voter sur téléchargement à conserver. Le CTS n’a pas de contrôle de rôle propre et l’utilisateur est non typé ; matrice des permissions d’export à valider. `fputcsv()` seul n’empêche pas l’interprétation de noms/notes comme formules dans un tableur.
- **Stripe** : signature vérifiée. Le contrôleur retourne initialement 401/500 sur erreur, contrairement au contrat du dépôt (200 systématique). Le lot P0 conserve le rejet du traitement et les logs tout en accusant réception en 200. Cela exige une procédure opérationnelle de détection/reprise des erreurs puisque les retries Stripe ne seront plus déclenchés par ces réponses. Pas de store de déduplication observé ; suppression d’abonnement modifie aussi `licenseStatus`, couplage billing/KYB à clarifier.
- **Limites** : contrôles serveur présents ; count puis insert sans verrou d’organisation, concurrence à tester avant déploiement à charge. Ne pas supprimer ces contrôles. Sémantique de réactivation/archivage et modification directe de Plant.status à encadrer.
- **Documents / secrets / logs** : les fichiers `.env.local` et clés privées ne sont pas suivis dans la liste Git examinée ; aucune preuve de sécurité des secrets déployés n’est déduite de cela. Racines documentaires configurables, chemins internes non normalisés contre `..` dans ArtifactStorage ; pas d’entrée de chemin publique démontrée. Sentry filtre certains statuts mais pas explicitement les données métier dans son callback ; revue de redaction et corrélation à compléter. Ne pas recopier secrets, JWT ou documents dans les logs d’audit.
- **Simulation** : le code limite l’auto-approbation au type `ctls_dev` avec format TEST-CTLS, mais le flag fonctionne indépendamment de `appEnv`. Conserver la beta autorisée ; interdire l’ouverture publique avant réalisation de la checklist existante. Pas de modification arbitraire du flag local/prod dans cet audit.

## 8. Testing gaps

Présents : `tests/Doctrine/Tenant{Filter,Listener}Test.php`, tests API tenant/RBAC dans `tests/Controller`, hash et workflows dans `tests/Service`, tests Domain/Application DDD et subscribers, 5 fichiers CTLS et test CLI. Frontend : 7 fichiers Vitest / 17 tests avant changements, plus 1 parcours Playwright simulant l’API.

Manquants ou insuffisants :

- Suite PostgreSQL issue des **migrations**, test SQL rejetant UPDATE/DELETE PlantEvent, puis vérification hash après `clear()` et migration JSONB ; les tests API font `SchemaTool::createSchema`, donc ne testent pas les triggers de migration.
- Append concurrent avec deux connexions, premier événement, rollback métier+event et dépassement simultané des limites.
- Historique corrigé sans mutation, lien intrant/event, interdiction des raccourcis de statut court-circuitant harvest/destruction.
- CTS HTTP : mois invalides, export A/B, utilisateur sans organisation, contenu dangereux pour tableur, données postérieures à la période. Les tests CLI CTLS ne couvrent pas le contrôleur HTTP.
- Matrice de toutes les opérations en lecture seule sous licence inactive, y compris DELETE, DDD, export et rôle API ; tests de non-inférence via IDs inexistants et d’un autre tenant.
- Worker intercalant A/B avec nettoyage d’EntityManager/tenant, reprise/idempotence et observabilité, lorsqu’un vrai transport async est introduit.
- E2E réel backend+PostgreSQL+JWT, Stripe sandbox (SYNC-05), KYB rejet/notif/re-soumission, Gotenberg et Mercure ; le smoke frontend ne valide pas ces intégrations.
- Contrôle TypeScript des SFC Vue (`vue-tsc`) et analyse PHPStan absents des scripts/CI. Ajouter ces outils pour un besoin de validation explicite ; ni baseline générée aveuglément ni exclusion des nouvelles erreurs.

## 9. Architecture target

Conserver les chemins existants tant qu’un déplacement ne réduit pas une dépendance ou une responsabilité identifiable. Introduire les nouveaux contextes au rythme des opérations nécessaires : Cultivation, Traceability, Compliance Canada, Inventory, Organization, Identity, Billing, Shared. Aucune migration Entity ↔ Domain implicite.

Le prochain chantier Canada utilisera cette structure, sans créer maintenant des couches vides :

```text
src/Compliance/Canada/
  Application/       # orchestration d’un rapport pour tenant/site/période
  Domain/            # modèle de rapport, complétude, règles validées et versionnées
  Infrastructure/    # lecture Doctrine, mapping CTLS et export
```

Conserver `src/Compliance/CTLS` comme implémentation existante pendant la transition. Première extraction utile : data provider du CSV HTTP avec tenant obligatoire, read model typé portant valeurs connues/inconnues et provenance ; orchestration indépendante du contrôleur ; réutilisation du validateur/exporter existants par adaptateur si leurs contrats conviennent. Un mapping Canada ne doit pas gouverner Plant générique. La neutralisation de cellules dangereuses des **exports internes pour tableurs** est un utilitaire partagé ; elle ne doit pas altérer silencieusement un fichier réglementaire de machine à machine.

Les opérations Harvest/Destroy/Move/Correct/Transfer doivent rester explicites lorsqu’elles protègent une transaction ou une règle. Ajouter Messenger pour un export lourd seulement après mesure ; message avec tenant/site, acteur, période, version et idempotency key, reconstitution du contexte et nettoyage en `finally`, retry borné, failure transport et log corrélé. Aucun événement externe ne doit être publié avant commit sans stratégie fiable de reprise.

## 10. Migration roadmap

P0 désigne soit une correction immédiate démontrable, soit un **blocage de mise en production** nécessitant une décision/test externe. Les seconds ne sont pas automatiquement autorisés à modifier un mécanisme protégé. Chaque item garde un périmètre propre.

| ID / priorité | Problem | Impact | Proposed solution | Files concerned | Risk | Estimated complexity |
| --- | --- | --- | --- | --- | --- | --- |
| P0-01 | Guard KYB seulement en création, suppression Sensor non gardée | Écritures autorisées en lecture seule | Guard sur les mutations des trois processors, routage DELETE Sensor vers remove après guard, tests de refus et succès | `src/State/{Plant,Room,Sensor}StateProcessor.php`, `src/Entity/Sensor.php`, tests | Faible ; refus attendu de requêtes auparavant acceptées | S |
| P0-02 | PlantEvent sans barrière ORM | Mutation possible si trigger absent ; découverte trop tardive en PostgreSQL | Listener ORM preUpdate/preRemove, tests via vraie unité de travail, garder trigger et hash | `src/Infrastructure/Persistence/Doctrine/`, tests | Faible ; peut révéler un ancien appel illégal | S |
| P0-03 | CSV interne présenté comme conformité validée ; mois invalide et cellules interprétables | Mauvaise décision utilisateur, 500 évitable, formules tableur | Libellé interne/provisoire, validation calendaire/auth minimale, neutraliser cellules textuelles dangereuses dans exports internes, tests tenant/contenu | `CTSReportController`, `ReportingExportService`, `frontend/src/views/compliance/ComplianceView.vue`, utilitaire/tests | Faible ; cellules dangereuses préfixées, colonnes/route conservées | M |
| P0-04 | Webhook 401/500 contraire au contrat explicite | Retries non conformes à la règle projet | Réponse 200 après rejet/log ; conserver constructEvent et tests de non-traitement | `src/Controller/StripeController.php`, tests Stripe | Moyen ; supervision/reprise manuelle nécessaires | S |
| P0-05 — DONE | Symfony 8.0 hors support, lock avec avis de sécurité | Exposition à des défauts corrigés ; dette de support | Symfony 8.1 installé, PHP >=8.4.1, Twig/Guzzle corrigés, recettes comparées, tests locaux exécutés ; qualification Docker/CI distante non exécutée localement | Composer/lock, Docker/CI, config/tests | Validation de production distincte | M |
| P0-06 — BLOCKED | Chaînage non sérialisé ; JSONB et ordre des clés | Forks ou échec de vérification de l’historique | Fork reproduit puis verrouillé ; vérificateur renforcé sans changer le hash. Échec JSONB réel conservé comme gate ; remédiation/versionnement à décider | `PlantEventRepository`, `HashChainService`, tests PostgreSQL, `AUDIT_QUESTIONS.md` | Élevé ; intégrité historique | L |
| P0-07 — gate public | Simulation KYB de beta | Approbation fictive si ouverture publique | Exécuter checklist AGENTS/prod avant ouverture ; revue manuelle réelle et flow rejet/re-soumission | `KybService`, configuration déployée, `docs/ops/prod-checklist.md` | Élevé si ouvert publiquement ; aucune preuve d’ouverture ici | M |
| P1-01 | Source mensuelle non historique, inventaire/mapping manquants | Aucun export actif CTLS qualifiable | Atelier titulaire fédéral + matrice de règles sourcées ; data provider/read model/validation/export Canada, jeux d’essai acceptés | `src/Compliance/CTLS`, futur `Compliance/Canada`, `CTSReportController`, modèle métier | Élevé ; validation externe | L |
| P1-02 | Plant persisté avant événement ; champs modifiés sans journal | Historique incomplet / incohérent | Transaction métier+append, opérations explicites et événement correctif selon stratégie validée | `PlantStateProcessor`, `InputRecordStateProcessor`, workflows | Élevé ; contrats API et hash | L |
| P1-03 | Tenant implicite hors HTTP et relations non garanties | Réutilisation dangereuse dans CLI/worker | Audit des requêtes, tenant obligatoire DBAL et modèle plants, tests A/B et relations ; décisions protégées séparées | `SensorReadingRepository`, `Domain/Plant`, subscribers/voters/listener concernés | Élevé ; validation humaine pour éléments protégés | M |
| P1-04 | PHPStan/type-check manquants et tests migrations absents | Impossible d’affirmer zéro nouvelle erreur | Installer/configurer analyse justifiée, dette visible sans baseline masquante ; PostgreSQL jetable avec migrations et CI | `composer.json`, config PHPStan, frontend package/tsconfig, workflows, tests | Moyen ; dette préexistante à traiter explicitement | M |
| P1-05 | Collisions de noms d’artefacts et absence de version | Écrasement de documents/exports | Identifiants aléatoires, écriture atomique, empreinte et provenance ; test de simultanéité | `ArtifactStorage`, `ReportingExportService`, `ReportExport` | Moyen ; stockage existant à préserver | M |
| P1-06 | Count+insert non atomique et statut libre ; règles DDD divergentes | Dépassement limites / court-circuit métier | Définir réactivation et matrice KYB ; verrou organisation et tests concurrents ; aucune fusion des modèles | `PlanLimitsService`, processors, `Plant`, contrôleurs DDD | Moyen ; politique métier | M |
| P2-01 | Absence de lot/stock/transformation/transfert persistés | Chaîne matière partielle | Implémenter le minimum des activités validées pour CTLS, avec quantités et généalogie ; pas de catalogue de concepts anticipé | Futurs contextes Inventory/Cultivation/Traceability | Moyen/élevé | XL |
| P2-02 | Exports volumineux / N+1 / fallback IoT silencieux | Coûts mémoire, latence, observabilité faible | Mesurer, projections/itération, index tenant+date ; logs et métriques, puis async si utile | Reporting, dashboard, SensorReadingRepository, Scheduler | Moyen | M |
| P2-03 | Logs sensibles possibles et IP brute | Exposition inutile, enquêtes difficiles | Schéma organisation/acteur/corrélation, redaction testée, politique IP ; idempotence notifications/webhooks | Monitoring, Kyb/Stripe/Scheduler, PlantEvent | Moyen ; format public/IP à décider | M |
| P3-01 | Ledger seulement Plant | Extension difficile à de nouveaux objets | Introduire un premier événement transverse quand le besoin existe, adaptateur legacy et vérificateurs versionnés | Futur Traceability, PlantEvent existant | Élevé si migration précipitée | L |
| P3-02 | Soumission manuelle sans cycle intégré | Suivi opérateur dispersé | Historique rapport/révision/réception et intégration uniquement selon contrat externe confirmé | Compliance/Canada, Messenger si justifié | Moyen/élevé | L |

### Exécution du premier lot

L’audit est rédigé avant modification applicative. P0-01 à P0-04 sont les corrections sélectionnées. Les gates P0-05 à P0-07 restent explicites ; les questions d’architecture/chaînage sont consignées dans `AUDIT_QUESTIONS.md`. Aucun nouveau ledger, aucune règle canadienne déduite, aucune migration de données ou modification de filtre/voter n’est incluse.

P0-01 à P0-04 sont implémentés. Les nouveaux tests couvrent les mutations API sous chacun des cinq statuts de licence (y compris le succès actif), le refus du processor avant effet de bord, l’enregistrement effectif du listener ORM, l’append correctif sans mutation, les exports A/B, les cellules dangereuses, les périodes invalides et l’accusé webhook avec rejet de signature conservé. Pour EXPIRED/SUSPENDED, le comportement HTTP existant est un refus **401 dès UserChecker** ; les processors sont aussi testés directement pour garantir leur refus autonome. Le contrat CTS (route, nom de fichier et colonnes) est conservé ; les cellules textuelles dangereuses des CSV internes sont préfixées d’une apostrophe. Le payload officiel CTLS n’est pas changé.

### Vérifications et limites du lot

| Contrôle | Résultat |
| --- | --- |
| `composer validate` | Réussi ; manifest et lock racine inchangés |
| `composer phpstan` | Commande absente du projet ; pas déclarée réussie |
| PHPStan complémentaire | PHPStan 2.1.0 installé uniquement sous `var/audit-tools`, niveau 5 sur tout `src`, autoload existant, mémoire 512M. Comparaison avec les sources `58081ea` extraites via `git archive` : **33 diagnostics avant, 32 après, aucun ajouté**, retrait de la capture de closure inutilisée dans CTS. Aucun fichier baseline créé/modifié. Cette comparaison ne vaut pas analyse Doctrine/Symfony spécialisée ou niveau maximal |
| PHPUnit | **262 tests, 760 assertions, 2 skipped** ; succès après activation locale des extensions et configuration OpenSSL. Tests PDF/Gotenberg non qualifiés ; les skips existants sur HTTP 5xx peuvent aussi masquer un défaut applicatif et ne prouvent pas à eux seuls une panne externe |
| `npm run type-check` | Script absent, échec explicite |
| Contrôle Vue/TypeScript complémentaire | `vue-tsc` temporaire avec TypeScript local 5.9.3 : **184 diagnostics**, dont types des dépendances et code applicatif. Aucun résultat vert revendiqué. Le `q-input type="month"` déjà présent dans ComplianceView n’appartient pas à l’union TypeScript Quasar. Outillage et dette à traiter dans P1-04 |
| `npm run test:unit` | **17 tests / 7 fichiers réussis** |
| `npm run build` | Réussi ; avertissements Sass legacy existants |
| `npm run test:e2e` | **1 parcours Playwright réussi**, API simulée ; ne valide ni backend ni Stripe sandbox |
| `git diff --check` | Réussi ; aucun fichier protégé de tenant/hash ni migration modifié |

Docker n’est pas démarré et le service PostgreSQL local est arrêté. Les tests ont été dirigés explicitement vers le fichier SQLite d’audit `var/architecture-audit.sqlite`, pour éviter le `DROP SCHEMA` de `ApiTestCase` sur une base existante. Extensions SQLite et fileinfo activées **pour le processus** ; `OPENSSL_CONF` pointe vers le fichier de configuration Git installé. Aucun paramètre PHP système ni environnement de production changé. La première tentative sans pilote SQLite échouait ; la suivante sans fileinfo/configuration OpenSSL comptait 1 erreur et 3 échecs. Ces problèmes d’environnement ont été résolus pour obtenir le résultat ci-dessus.

Commandes de reproduction locales (PowerShell, dépendances PHP déjà installées) :

```powershell
$env:APP_ENV = 'test'
$env:DATABASE_URL = 'sqlite:///%kernel.project_dir%/var/architecture-audit.sqlite'
$env:OPENSSL_CONF = 'C:\Program Files\Git\usr\ssl\openssl.cnf'
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=fileinfo bin/phpunit
```

Les logs de cette session sont sous `var/architecture-audit-*` (ignorés par Git). Les outils supplémentaires sont temporaires ; leur installation ne remplace pas P1-04. La qualification de production exige encore PostgreSQL 16 avec migrations et triggers, concurrence, Gotenberg/Mercure et intégrations réelles. Aucun résultat local ne valide les règles Health Canada, les données déployées ou SYNC-05.

### Exécution du deuxième lot — P0-05 / P0-06

**P0-05 DONE** pour la migration du dépôt et les contrôles locaux. FrameworkBundle
8.0.7 → **8.1.6** ; composants principaux verrouillés en 8.1.x, contraintes directes
et `extra.symfony.require` en `8.1.*`. Flex, Mercure, contracts et polyfills gardent
leurs numérotations propres. `require.php` et `config.platform.php` sont alignés
sur **8.4.1**, minimum effectif du package cible, sans les relever à 8.4.11.
Le runtime reste `php:8.4-fpm` avec contrôle explicite `PHP_VERSION_ID >= 80401` ;
Composer provient de `composer:2` dans les deux stages. Railway utilise ce même
Dockerfile. La CI Composer prévoit PHP 8.4.1 et la dernière 8.4. Le Compose
historique ne surcharge pas PHP ; son volume PostgreSQL 13 reste hors de ce lot,
les tests de migrations ayant leur service PostgreSQL 16 séparé.

Résolution exécutée : `composer update "symfony/*" twig/twig guzzlehttp/psr7 -W`,
sans mise à jour globale. **67 packages mis à jour, 4 ajoutés** ; détail
[avant/après suivi](evidence/p0-05/packages.json). Twig 3.24.0 → 3.28.0,
Guzzle PSR-7 2.9.0 → 2.13.1, Doctrine Persistence 4.1.1 → 4.2.0 (transitif).
API Platform 4.3.3, Doctrine ORM 3.6.2, Lexik, Stripe et Sentry sont conservés.
Ajouts : polyfill deepclone requis par Symfony ; `dragonmantank/cron-expression`
3.6.0 pour le `AsCronTask` existant ; PHPStan 2.1.0 et Symfony Process 8.1.6 en dev.
Le contrôle `debug:scheduler` échouait faute de parser cron et passe après ajout,
avec la tâche LicenseExpirationScheduler à 08:00. Aucun email/job n'a été exécuté.

Comparaison du [guide officiel 8.1](https://raw.githubusercontent.com/symfony/symfony/8.1/UPGRADE-8.1.md)
avec les usages du dépôt :

| Zone | Qualification effectuée / décision |
| --- | --- |
| Console | Options existantes cohérentes, aucune sous-classe d'Input/Style affectée ; commandes bootées, tests CLI existants conservés |
| Messenger | Transport sync, aucune implémentation custom Receiver/retry concernée ; `debug:messenger` réussi |
| Serializer / ParameterBag | Pas d'usage des signatures d'exception concernées ni de `getInt/getBoolean` ; tests de normalisation/API et erreurs HTTP réussis |
| Security / role hierarchy | Hiérarchie YAML standard, pas d'implémentation custom ; tests JWT/RBAC/KYB réussis. `eraseCredentials()` vide conservé, dépréciation à suivre |
| API Platform | 4.3.3 maintenu ; container, routes, opérations HTTP et processors exercés par PHPUnit |
| Workflow | Les deux machines existantes restent séparées et leurs tests passent |
| Mercure | Bundle 0.4.3, définition du hub et intégration API Platform vérifiées ; publication vers un hub réel non qualifiée |
| Scheduler | Dépendance cron explicitée et planning effectivement chargé |

**Recipes comparées manuellement, aucune réinstallation automatique.** Sources
de [Symfony Recipes](https://github.com/symfony/recipes), révisions installées
signalées par `composer recipes` versus version courante :

- FrameworkBundle 6.4 → 8.1 : nouveaux fichiers d'aide agents, APP_SHARE_DIR,
  simplification des sessions/services, restriction optionnelle des environnements
  et closure statique. Aucun besoin d'écraser les exclusions, sessions, proxies,
  Kernel ou bootstrap JWT du projet. Container et cache se chargent sans ces changements.
- Routing 6.2 → 7.4 : ressource automatique `routing.controllers` et DEFAULT_URI.
  Imports explicites des deux répertoires de contrôleurs conservés, aucun changement
  d'origine des URL CLI introduit. Aucun paramètre request_context déprécié utilisé.
- Mercure 0.4 : seul fallback facultatif sur MERCURE_PUBLIC_URL ; URL projet déjà
  explicite, config conservée.
- Twig 6.4 : ajout de hot reload FrankenPHP au squelette ; runtime PHP-FPM/Vue,
  non applicable. Config Twig inchangée, templates lintés.
- UID 6.2 → 7.0 : la nouvelle recette ne copie plus de config ; ne pas supprimer
  la configuration existante ni changer les UUID historiques.
- Validator 5.3 → 7.0 : retrait du défaut email explicite dans le squelette ; aucun
  changement métier requis. Les autres mises à jour de recettes Doctrine/Sentry
  préexistantes sont différées, leurs bundles n'ayant pas été mis à jour.

**P0-06 BLOCKED.** Le [rapport PostgreSQL reproductible](plant-event-integration.md)
contient les payloads/hashes exacts, les preuves avant/après concurrence et les
commandes de lancement. PostgreSQL 16.15 applique les **32 migrations / 167 requêtes**
sur une base neuve, puis la base est supprimée même après échec. INSERT autorisé,
UPDATE/DELETE interdits à la fois en SQL et par l'ORM. Deux connexions indépendantes
reproduisent le fork avant correction, y compris au premier événement ; le verrou
parent `FOR NO KEY UPDATE` sérialise désormais les appels sous READ COMMITTED.
Le vérificateur rejette explicitement un `hashPrevious` incohérent ou des zéros
initiaux corrompus. `computeHash()` et toutes les migrations restent inchangés.

L'encodage JSONB réordonne les clés et invalide le hash après reload, sur un ou
trois événements. **Les deux tests restent en échec et bloquent la CI.** Aucun
rehash, payload historique, timestamp historique ni modèle métier modifié. Il faut
une décision de compatibilité/remédiation avant toute prétention d'intégrité complète.

Pour P1-02, `appendEvent` garde son API et son flush, désormais dans une transaction
avec verrou. Les tests prouvent le rollback métier+event dans une transaction externe,
et l'absence de commit partiel après erreur JSON ou SQL. Les callers doivent fermer
leur transaction après échec ; les processors déjà committés avant l'append ne sont
pas rendus atomiques rétroactivement. Le décalage +1 seconde est conservé pour l'ordre
historique : le verrou ne fournit pas d'ordre persistant en cas d'égalité des dates
avec UUIDv4. Un ordre technique distinct reste à concevoir sans réécriture historique.

| Validation du deuxième lot | Résultat effectivement exécuté |
| --- | --- |
| Composer validate avant/après | Réussi, lock conservé |
| Composer audit avant/après | **41 avis sur 13 packages → aucun avis**, aucun package abandonné |
| Composer outdated --direct avant/après | **40 → 11** ; upgrades hors périmètre laissés explicites |
| PHPUnit rapide | **267 tests, 767 assertions, 2 skipped** (PDF/Gotenberg) ; aucun ancien test retiré |
| PostgreSQL 16 réel | **15 tests, 80 assertions, 2 échecs JSONB**, zéro skip ; autres contrôles réussis |
| PHPStan global, niveau 5, même version 2.1.0 | **32 → 31 diagnostics, aucun ajouté** ; commande `composer phpstan` retourne volontairement non-zéro |
| PHPStan code PlantEvent + nouveau runner/tests | Réussi, zéro diagnostic ; commande ciblée aussi inscrite en CI |
| Frontend | **17 tests / 7 fichiers réussis**, build réussi ; aucune modification supplémentaire dans ce lot |
| Configuration | Container, YAML config/workflows/Compose et les 3 templates Twig valides ; cache prod reconstruit sans connexion métier ; Scheduler/Messenger/Mercure inspectés |

PHPStan est maintenant installé en dev avec `phpstan.neon.dist`, sans baseline ni
`ignoreErrors`. Le [relevé de dette](evidence/p0-05/phpstan-debt.json) conserve les
31 diagnostics et constate zéro ajout par comparaison nom de fichier/identifiant/message.
Il couvre notamment types Doctrine générés, commandes, nullable annotations,
catalogue Humboldt, propriétés Stripe et processors. La CI ciblée couvre les fichiers
de ce lot ; elle ne prétend pas analyser sans erreur tout le backend. Le type-check
frontend absent et les 184 diagnostics observés dans le premier lot restent P1-04.

Limites : image Docker non construite localement (Docker Desktop indisponible),
matrice GitHub Actions non exécutée à distance, pas de déploiement Railway,
pas de copie d'historique de production ni de migration sur données peuplées,
pas de qualification TimescaleDB/Gotenberg/Mercure/Stripe externe. L'avertissement
historique de migration JournalEntry sur une base peuplée reste ouvert. Logs locaux
`var/p005-*` et `var/p006-*`, preuves synthétiques suivies dans `docs/evidence/`.
