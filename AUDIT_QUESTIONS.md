# AUDIT QUESTIONS

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
