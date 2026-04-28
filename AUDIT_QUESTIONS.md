# AUDIT QUESTIONS

- P3-C migration rename: si `Version_SensorReading` a déjà été exécutée en production, il faudra mettre à jour manuellement la table `doctrine_migration_versions` pour refléter `Version20260427102000`.
- ENV verification: `php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration` échoue sur `DoctrineMigrations\Version20260327100000` avec `journal_entry is append-only; UPDATE operations are forbidden`. La migration tente une opération incompatible avec la règle append-only sur `journal_entry` et doit être revue avant validation du schéma.
- ENV verification: `php bin/phpunit` ne finit pas en vert après restauration de l’environnement. Les échecs restants observés au 28 avril 2026 sont des régressions applicatives déjà présentes dans la branche :
  - plusieurs tests appellent `ApiTestCase::createRoom()` avec une string alors que la signature attend désormais `App\Enum\RoomType`
  - `tests/Unit/Domain/Plant/PlantWorkflowTest.php` demande le service `workflow.plant_lifecycle`, introuvable dans le conteneur de test
  - plusieurs tests attendent `403` mais l’application retourne `422` sur des écritures interdites avec licence `pending`
  - `tests/Controller/PlantApiTest.php` échoue avec `Filter 'tenant_filter' is not enabled`
