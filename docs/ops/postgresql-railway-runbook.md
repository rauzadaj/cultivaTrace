# Runbook PostgreSQL Railway

Ce document fixe la stratégie minimale de production pour CultivaTrace sur Railway.

## 1. Connexions

- `DATABASE_URL`
  Doit pointer vers l'endpoint PostgreSQL poolé Railway pour le trafic applicatif HTTP.
- `DATABASE_URL_DIRECT`
  Doit pointer vers l'endpoint PostgreSQL direct Railway pour les opérations longues ou sensibles :
  - migrations Doctrine
  - `doctrine:schema:validate`
  - restaurations
  - imports/exports administratifs

Principe :

- l'application web et les workers utilisent le pooler pour limiter le nombre de connexions ouvertes ;
- les commandes d'administration utilisent la connexion directe pour éviter les timeouts de transaction ou les limitations du pooler.

## 2. Variables attendues

- `DATABASE_URL`
- `DATABASE_URL_DIRECT`

Exemple de convention :

```text
DATABASE_URL=postgresql://USER:PASSWORD@POOLER_HOST:PORT/DB_NAME?serverVersion=16&charset=utf8
DATABASE_URL_DIRECT=postgresql://USER:PASSWORD@PRIMARY_HOST:PORT/DB_NAME?serverVersion=16&charset=utf8
```

Ne jamais commiter ces DSN.

## 3. Usage applicatif

- runtime web :
  - Symfony boot avec `DATABASE_URL`
- opérations manuelles :
  - exporter `DATABASE_URL="$DATABASE_URL_DIRECT"` avant `php bin/console doctrine:migrations:migrate`
  - exporter `DATABASE_URL="$DATABASE_URL_DIRECT"` avant `php bin/console doctrine:schema:validate`

Exemple Railway SSH :

```bash
export DATABASE_URL="$DATABASE_URL_DIRECT"
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

## 4. Politique de sauvegarde

- backups automatiques Railway activés au minimum en quotidien
- au moins un test de restauration sur staging avant tout go-live
- conserver une fenêtre de restauration compatible avec le support client beta

## 5. Procédure de restauration

1. Identifier l'instant de restauration cible dans Railway.
2. Restaurer vers une base de staging, jamais directement sur la prod au premier essai.
3. Pointer un environnement de validation sur cette base restaurée.
4. Vérifier :
   - `php bin/console doctrine:migrations:status`
   - `php bin/console doctrine:schema:validate`
   - `GET /api/health`
   - login JWT
   - lecture d'un tenant réel de test
5. Seulement après validation fonctionnelle, planifier la restauration prod.

## 6. Vérifications post-restauration

- les triggers append-only existent toujours sur `plant_event` et `journal_entry`
- le dernier lot de migrations est marqué comme exécuté
- aucune variable sensible n'a été écrasée côté Railway
- les workers / cron jobs utilisent bien la base restaurée attendue
