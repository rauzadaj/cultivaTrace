# AUDIT QUESTIONS

## Portage de la PR sur main `869e592`

Les sections du 14 septembre ci-dessous décrivent la base locale initiale `58081ea`.
Le main distant possède un historique Git indépendant et des évolutions supplémentaires.
La PR porte les corrections utiles sur `869e592` en conservant son HMAC-SHA256,
ses restrictions d'accès, son listener append-only, PHPStan niveau 6 et sa baseline
préexistante inchangée. L'analyse supplémentaire `composer phpstan:p0` n'utilise
aucune baseline. Les nouveaux résultats et limites sont dans
[docs/pr-integration.md](docs/pr-integration.md). P0-06 reste BLOCKED : le reload
JSONB échoue aussi avec le HMAC actuel de main. Aucune donnée historique réécrite.

## Lot P0-05 / P0-06 — résultats du 14 septembre 2026

- **P0-05 DONE** : Symfony FrameworkBundle 8.0.7 → 8.1.6, plateforme PHP minimale 8.4.1, lock adopté. Twig/Guzzle corrigés ; audit Composer : 41 avis sur 13 packages → zéro. Recipes comparées sans écrasement des configurations. PHPStan est suivi dans Git. Tests rapides/frontend réussis. Docker/CI distante et déploiement Railway non exécutés ; le statut DONE porte sur le dépôt et sa qualification locale.
- **P0-06 BLOCKED — TRACE-02 confirmé** : PostgreSQL 16.15, 32 migrations réelles appliquées, puis append/flush/clear/reload. JSONB change l'ordre des clés du payload et le hash recalculé diffère du hash stocké, sur une chaîne d'un ou de trois événements. Les deux tests restent bloquants en CI, sans skip. Payload original/rechargé, hashes exacts et preuves : [rapport PlantEvent](docs/plant-event-integration.md), [preuves JSONB](docs/evidence/p0-06/jsonb-1.json). Décider du format/versionnement et de la remédiation de l'historique avant correction ; aucun rehash, payload ou timestamp historique modifié ici.
- **TRACE-01 — concurrence corrigée dans le périmètre autorisé** : bifurcation reproduite avec deux processus/connexions PostgreSQL distincts, aussi sans événement initial. `FOR NO KEY UPDATE` sur le Plant, sous transaction READ COMMITTED, impose maintenant l'attente puis la relecture du prédécesseur. SQL scoped par plant et tenant ; aucun TenantFilter/Listener/Voter modifié. Une isolation à snapshot est rejetée explicitement. La date ne sérialise pas les écritures. Le décalage +1 seconde reste nécessaire à l'ordre historique tant qu'un ordre technique distinct n'est pas validé ; aucune migration de cet ordre dans ce lot.
- **TRACE-03 — vérificateur corrigé, enveloppe inchangée** : deux corruptions de hashPrevious passaient le vérificateur initial ; le premier prédécesseur est maintenant comparé aux 64 zéros et les suivants au hashSelf précédent. Tests hashSelf/payload/prédécesseur réussis ; `computeHash()` strictement inchangé. Les questions d'enveloppe, d'IP et de correction transverse restent ouvertes.
- **P1-02 — préparation PARTIAL** : append garde son API et flush dans `wrapInTransaction`. Rollback de mutation métier+append externe, échec JSON avant flush et contrainte SQL pendant flush testés ; EntityManager fermé en cas d'échec. Un caller doit rollback la transaction externe puis recréer le manager. Le flush n'empêche pas un seul commit métier ; il faut surtout déplacer la frontière transactionnelle avant les mutations déjà persistées. Workflows et processors non convertis en masse.
- **P1-04 — préparation PARTIAL** : `composer phpstan` analyse tout src au niveau 5 et échoue encore sur 31 diagnostics préexistants (32 avant ce lot, zéro ajouté). [Dette détaillée](docs/evidence/p0-05/phpstan-debt.json), aucune baseline ni ignoreErrors. Analyse ciblée code PlantEvent + nouveaux tests/runner réussie et ajoutée en CI. Le type-check frontend et la dette globale restent à traiter.
- **Limite migrations** : la base neuve PostgreSQL passe ; cela ne clôt pas l'incident JournalEntry sur une base peuplée décrit plus bas. Aucune migration existante modifiée, aucune migration de données ajoutée. La base réelle de production et TimescaleDB n'ont pas été qualifiés.

## Audit architecture — 14 septembre 2026

Constats initiaux de l'audit, conservés pour référence ; les décisions et résultats du lot suivant figurent ci-dessus.

- **ARCH-01 — coexistence** : `Entity/Plant`, `Domain/Cultivation/Crop` et `Domain/Plant/Plant` (table `plants`, organizationId) sont distincts. Quel modèle porte les futurs lots et stocks ? Aucune liaison, fusion ni migration n'est implémentée sans validation humaine. Voir `docs/architecture-evolution.md`.
- **TRACE-01 — concurrence / format protégé** : `PlantEventRepository::appendEvent()` lit puis insère sans verrou ; deux transactions peuvent partager le même prédécesseur. Valider la sérialisation par plant et l'ordre distinct de occurredAt avant de modifier le chaînage. Tester premier append concurrent, rollback et tenant A/B sous PostgreSQL.
- **TRACE-02 — JSONB / historique** : `Version20260503100000` convertit le payload en JSONB, alors que le hash dépend de l'ordre des clés JSON. Vérifier en lecture seule des événements multi-clés rechargés et la migration sur une copie isolée avant déploiement. Ne pas rehash ni corriger silencieusement l'historique ; choix de format/versionnement et remédiation à valider humainement.
- **TRACE-03 — périmètre de preuve** : hash actuel limité à ID/payload/date Unix/prédécesseur ; `verify()` ne compare pas séparément hashPrevious stocké. Définir l'enveloppe couverte, le lien de correction et la politique d'IP (actuellement brute et sérialisée) avant évolution. Le lot immédiat ajoute seulement une barrière ORM append-only, sans changer l'algorithme.
- **CANADA-01 — qualification** : faire valider par un titulaire fédéral canadien le site/licence, la version du template, le fuseau et les bornes de période, classes/mouvements, unités/arrondis, zéro versus inconnu, quarantaine/destruction, inventaires, corrections et cas sans activité. Le CSV HTTP reste interne/provisoire et le convertisseur CTLS conserve son refus de l'activité sans mapping. Aucune règle réglementaire nouvelle n'est déduite du modèle actuel.
- **TENANT-01 — contexte hors HTTP** : définir la politique du modèle `organizationId`, des requêtes DBAL capteurs et des jobs globaux ; valider toute correction du fail-open SQLite pour UUID invalide dans TenantFilter. Aucun filtre, TenantListener ou voter n'est modifié par cet audit.
- **BILLING-01 — exploitation** : le contrat AGENTS impose HTTP 200 même après erreur webhook. La correction conserve la vérification de signature et les logs ; définir la procédure de reprise/alerting et de déduplication puisque l'accusé HTTP n'atteste pas le traitement réussi. Clarifier séparément le couplage annulation Stripe → licenseStatus et les droits d'écriture billing des ORG_USER.
- **QUALITY-01 — Symfony 8.1** : résolution isolée réussie avec PHP plateforme 8.4.11 ; 8.1.6 demande >=8.4.1 et la plateforme racine est 8.4.0. Adoption du lock reportée jusqu'aux contrôles runtime/migrations PostgreSQL et analyse statique reproductibles. PHPStan et le script frontend type-check sont absents dans la branche auditée ; ne pas supposer l'existence d'une baseline.

- P3-C migration rename: si `Version_SensorReading` a déjà été exécutée en production, il faudra mettre à jour manuellement la table `doctrine_migration_versions` pour refléter `Version20260427102000`.
- ENV verification: `php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration` échoue sur `DoctrineMigrations\Version20260327100000` avec `journal_entry is append-only; UPDATE operations are forbidden`. La migration tente une opération incompatible avec la règle append-only sur `journal_entry` et doit être revue avant validation du schéma.
- ENV verification: `php bin/phpunit` ne finit pas en vert après restauration de l’environnement. Les échecs restants observés au 28 avril 2026 sont des régressions applicatives déjà présentes dans la branche :
  - plusieurs tests appellent `ApiTestCase::createRoom()` avec une string alors que la signature attend désormais `App\Enum\RoomType`
  - `tests/Unit/Domain/Plant/PlantWorkflowTest.php` demande le service `workflow.plant_lifecycle`, introuvable dans le conteneur de test
  - plusieurs tests attendent `403` mais l’application retourne `422` sur des écritures interdites avec licence `pending`
  - `tests/Controller/PlantApiTest.php` échoue avec `Filter 'tenant_filter' is not enabled`
- TEST-T1 verification on 28 April 2026: `php bin/console doctrine:migrations:migrate --no-interaction` ne bloque plus sur le trigger append-only de `journal_entry`, mais échoue maintenant sur `DoctrineMigrations\Version20260330103000` avec `Cannot enforce crop tenant isolation: rows with NULL tenant_id remain.` Analyse:
  - la base locale `cultivatrace` contient `3` lignes `crop` avec `tenant_id IS NULL` et `5` lignes `journal_entry` avec `tenant_id IS NULL`
  - la table `organization` est vide (`0` ligne), donc aucun tenant fiable n’existe pour backfiller ces enregistrements
  - les `batch_code` observés (`ALP-2401-A`, `ALP-2309-H`, `SOL-2402-F`) proviennent de `src/Command/SeedDemoDataCommand.php`, ce qui suggère des données DDD de démonstration devenues orphelines
  - faire passer cette migration maintenant demanderait soit une suppression/destruction de ces données orphelines, soit la création/invention d’un tenant de rattachement. Les deux options ont un impact métier/data et nécessitent une validation humaine
- TEST-T4 verification on 28 April 2026: après T1/T2/T3/T5 et la correction enum `DestructionStatus`, `php bin/phpunit` échoue encore avec `12` failures. Répartition exacte :
  - `AlertControllerTest::testAcknowledgeIsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/alerts/{id}/acknowledge`
    rôle: `ROLE_ORG_USER`
    payload: aucun
    résultat actuel: `422`
    source identifiée: `src/Controller/AcknowledgeAlertController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `src/Infrastructure/Http/EventSubscriber/ApiExceptionSubscriber.php` convertit toute `DomainException` en `422`
  - `FarmApiTest::testPostFarmReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/farms`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"name":"Farm Pending"}`
    résultat actuel: `422`
    source identifiée: `src/State/FarmStateProcessor.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `FarmApiTest::testPatchFarmReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `PATCH /api/farms/{id}`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"name":"Blocked Farm Update"}`
    résultat actuel: `422`
    source identifiée: même chaîne `FarmStateProcessor` -> `LicenseGuard` -> `ApiExceptionSubscriber`
  - `HarvestControllerTest::testHarvestReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/plants/{id}/harvest`
    rôle: `ROLE_ORG_USER`
    payload: `{"grossWeightG":120.50,"netWeightG":95.00,"harvestedAt":"2026-03-18"}`
    résultat actuel: `422`
    source identifiée: `src/Controller/HarvestController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `HarvestControllerTest::testDestroyReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/plants/{id}/destroy`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"reason":"Mold contamination"}`
    résultat actuel: `422`
    source identifiée: `src/Controller/DestructionController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `HarvestControllerTest::testConfirmDestroyReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/destructions/{id}/confirm`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"totalWeightG":100,"nonCannabisRatio":0.60,"photoUrls":["https://example.test/photo.jpg"]}`
    résultat actuel: `422`
    source identifiée: `src/Controller/DestructionController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `InputRecordApiTest::testPostInputRecordReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/input_records`
    rôle: `ROLE_ORG_USER`
    payload: `{"plant":"/api/plants/{id}","inputType":"water","productName":"Reverse Osmosis","quantity":"2.500","unit":"L","appliedAt":"2026-03-18"}`
    résultat actuel: `422`
    source identifiée: `src/State/InputRecordStateProcessor.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `OrganizationAdminControllerTest::testPendingLicenseCannotUpdateOrganizationSettings`
    endpoint: `PATCH /api/organization/settings`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"name":"Blocked Update"}`
    résultat actuel: `422`
    source identifiée: `src/Controller/OrganizationAdminController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `OrganizationAdminControllerTest::testPendingLicenseCannotInviteMember`
    endpoint: `POST /api/organization/invitations`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"email":"blocked-member@cultivatrace.local","role":"ROLE_ORG_USER"}`
    résultat actuel: `422`
    source identifiée: `src/Controller/OrganizationAdminController.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `StrainApiTest::testPostStrainReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/strains`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"name":"Strain Pending","genetics":"hybrid","cannabisType":"marijuana"}`
    résultat actuel: `422`
    source identifiée: `src/State/StrainStateProcessor.php` appelle `LicenseGuard::assertLicenseApproved()`, puis `ApiExceptionSubscriber` mappe `LicenseNotApprovedException` vers `422`
  - `PlantApiTest::testPostPlantsReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `POST /api/plants`
    rôle: `ROLE_ORG_USER`
    payload: `{"room":"/api/rooms/{id}","strain":"/api/strains/{id}","rfidTag":"PLANT-PENDING-001","germinatedAt":"2026-03-01","stage":"germination"}`
    résultat actuel: `201`
    source identifiée: `src/State/PlantStateProcessor.php` n’appelle pas `LicenseGuard::assertLicenseApproved()` du tout. Corriger ce test demanderait une modification du code applicatif métier, hors périmètre autorisé par le prompt courant
  - `StrainApiTest::testPatchStrainReturnsForbiddenWhenTenantLicenseIsPending`
    endpoint: `PATCH /api/strains/{id}`
    rôle: `ROLE_ORG_ADMIN`
    payload: `{"notes":"Blocked strain update"}`
    résultat actuel: `422` avec le détail `genetics: The value you selected is not a valid choice.`
    analyse: ce cas ne semble pas passer par `LicenseGuard` en premier. La validation/denormalization API Platform produit déjà un `422` avant le comportement attendu à `403`, ou le payload de test ne correspond plus au schéma courant de `Strain`. Corriger proprement ce point demande une revue applicative du flux de validation/sécurité, hors périmètre tests/migrations
- TEST-T6 verification on 28 April 2026: après correction de `ApiExceptionSubscriber`, les réponses d'erreur API n'utilisent plus `\u0022` et renvoient désormais `Tenant license status \"pending\" does not allow write operations.` dans le JSON brut. `php bin/phpunit` échoue toutefois encore avec `10` failures, toutes sur la même cause :
  - `FarmApiTest::testPostFarmReturnsForbiddenWhenTenantLicenseIsPending`
  - `FarmApiTest::testPatchFarmReturnsForbiddenWhenTenantLicenseIsPending`
  - `HarvestControllerTest::testHarvestReturnsForbiddenWhenTenantLicenseIsPending`
  - `HarvestControllerTest::testDestroyReturnsForbiddenWhenTenantLicenseIsPending`
  - `HarvestControllerTest::testConfirmDestroyReturnsForbiddenWhenTenantLicenseIsPending`
  - `InputRecordApiTest::testPostInputRecordReturnsForbiddenWhenTenantLicenseIsPending`
  - `OrganizationAdminControllerTest::testPendingLicenseCannotUpdateOrganizationSettings`
  - `OrganizationAdminControllerTest::testPendingLicenseCannotInviteMember`
  - `StrainApiTest::testPostStrainReturnsForbiddenWhenTenantLicenseIsPending`
  - `StrainApiTest::testPatchStrainReturnsForbiddenWhenTenantLicenseIsPending`
  Analyse:
  - status reçu: `403` sur les 10 tests, donc la régression applicative `422` est bien corrigée
  - message reçu dans le JSON brut: `Tenant license status \"pending\" does not allow write operations.`
  - sous-chaîne attendue par les tests: `status "pending"`
  - en JSON valide, un guillemet présent à l'intérieur d'une string doit apparaître échappé dans la représentation brute (`\"`). Il n'est donc pas possible d'obtenir à la fois une réponse JSON valide et la sous-chaîne brute `status "pending"` dans `Response::getContent()`
  - si l'intention produit doit rester de tester le message humain, il faut décoder le JSON dans les tests puis comparer `detail`; sinon il faut changer le message applicatif pour éviter les guillemets autour de `pending`
