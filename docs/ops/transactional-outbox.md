# File transactionnelle des effets externes

La migration `Version20261118000000` crée `effect_outbox`. Appliquer les migrations avant de déployer le code qui écrit dans cette table. Les créations KYB, les lectures capteur et les webhooks de facturation échouent si elle manque, afin de ne pas valider un état métier sans son effet associé.

Le conteneur applicatif démarre le consommateur après la migration et le préchauffage du cache. Définir `OUTBOX_WORKER_ENABLED=0` pour le désactiver. Un processus dédié peut utiliser la même image et les mêmes variables d'environnement que l'API :

```sh
php bin/console app:outbox:dispatch --watch --limit=100
```

Le conteneur accepte cette commande comme argument de démarrage. Pour une exécution planifiée hors conteneur, utiliser `php bin/console app:outbox:dispatch --limit=100` chaque minute. Plusieurs processus peuvent travailler en parallèle sur PostgreSQL grâce à `FOR UPDATE SKIP LOCKED`. Les effets échoués sont rejoués avec un délai croissant ; une tâche réclamée par un processus interrompu redevient disponible après cinq minutes.

Surveiller les lignes où `delivered_at IS NULL`, `attempts` et `last_error`. Le transport est **au moins une fois** : après une panne survenue entre l'envoi externe et l'accusé en base, un envoi peut être rejoué. Les événements Mercure portent un identifiant stable, et les emails un `Message-ID` stable. Les synchronisations Stripe relisent l'état courant avant de modifier l'organisation.
