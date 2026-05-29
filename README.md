# CultivaTrace MVP

Plateforme de traçabilité agricole avec journal append-only, suivi de cycle cultural et dashboard temps réel.

## Stack technique

- Symfony 8.0 + API Platform 4.2
- PHP 8.4
- PostgreSQL 16 (TimescaleDB optionnel pour les lectures de capteurs)
- Auth JWT (LexikJWTAuthenticationBundle)
- Frontend Vue 3 + TypeScript + Pinia + Vite + Quasar
- Docker / Docker Compose

## Architecture

- `src/Entity/` : entités Doctrine (Plant, Farm, Room, Sensor, Organization, User, …)
- `src/State/` : processeurs API Platform (LicenseGuard, plan limits, soft-delete)
- `src/Service/` : services métier (KybService, StripeService, PlanLimitsService, …)
- `src/Doctrine/` : TenantFilter, ArchiveFilterExtension
- `src/Infrastructure/` : contrôleurs HTTP personnalisés, subscriber RFC 7807
- `frontend/src/stores` : stores Pinia modulaires
- `frontend/src/views/` : dashboard, plants, rooms, sensors, billing, KYB

## Modèle métier livré

- `Plant` : plant individuel avec RFID, stade courant, statut, journal d'événements append-only
- `PlantEvent` : entrée immuable du journal (hash chain SHA-256, trigger PostgreSQL anti-mutation)
- `Farm` + `Room` : hiérarchie géographique, soft-delete sur Farm via `archivedAt`
- `Sensor` : capteur IoT (MQTT / REST / simulé), seuils d'alerte, lectures TimescaleDB
- `Strain` : variété génétique interne de l'organisation
- `HarvestRecord` / `InputRecord` / `DestructionIntent` : traçabilité réglementaire
- `Organization` : tenant racine, plan d'abonnement, statut de licence KYB
- `ReportExport` : exports PDF/CSV générés via Gotenberg

## Rôles & permissions (RBAC)

L'autorisation combine trois couches : **rôle** (hiérarchie ci-dessous), **isolation tenant**
(`TENANT_ACCESS` + `tenant_filter` Doctrine), et **garde de licence** (les mutations sont
bloquées tant que la licence KYB de l'organisation n'est pas active).

Hiérarchie (chaque rôle hérite du précédent) :
`ROLE_VIEWER` → `ROLE_ORG_USER` → `ROLE_ORG_ADMIN` → `ROLE_SUPER_ADMIN`.
`ROLE_API` est un rôle disjoint réservé à l'ingestion IoT (lecture capteurs + écriture de lectures).

| Domaine | `ROLE_VIEWER` | `ROLE_ORG_USER` | `ROLE_ORG_ADMIN` | `ROLE_SUPER_ADMIN` |
|---|:---:|:---:|:---:|:---:|
| Dashboard, capteurs, alertes (lecture) | ✅ | ✅ | ✅ | ✅ |
| Plants, salles, variétés, récoltes, intrants, destructions (lecture) | ✅ | ✅ | ✅ | ✅ |
| Créer / modifier plants, intrants, lectures capteurs, acquitter alertes | ❌ | ✅ | ✅ | ✅ |
| Récolte / destruction de plants | ❌ | ✅¹ | ✅ | ✅ |
| Créer / modifier fermes, salles, variétés, capteurs | ❌ | ❌ | ✅ | ✅ |
| Paramètres org, membres, invitations | ❌ | ❌ | ✅ | ✅ |
| Rapports & exports (génération + CTS Health Canada) | ❌ | ❌ | ✅ | ✅ |
| Facturation Stripe (checkout / portail) | ❌ | ❌ | ✅ | ✅ |
| Documents de licence KYB (entité) | ❌ | ✅ | ✅ | ✅ |
| Validation KYB backoffice | ❌ | ❌ | ❌ | ✅ |

¹ La récolte est ouverte à `ROLE_ORG_USER` ; la **destruction** exige `ROLE_ORG_ADMIN` (cf. `PlantVoter`).

`ROLE_VIEWER` est le palier **lecture seule** destiné aux profils consultation (comptable,
inspecteur, investisseur) : il peut consulter les données opérationnelles et le statut
KYB/facturation, mais aucune opération mutante ne lui est accessible. La gouvernance
(rapports, gestion des membres, exports réglementaires) reste réservée aux admins.

## Flows utilisateur MVP

### 1. Inscription et KYB

```
POST /api/register/organization       Créer l'organisation et son premier admin
  → reçoit JWT + refreshToken

POST /api/kyb/submit                  Soumettre les documents de licence
GET  /api/kyb/status                  Consulter le statut (pending / active / rejected)
```

Le statut KYB passe en `active` après vérification (Health Canada / METRC / simulation dev).
Tant que la licence n'est pas `active`, les mutations Plant/Farm sont bloquées (403).

### 2. Authentification

```
POST /api/auth/login                  Login JWT (email + password)
  → { token, refreshToken, expiresIn }

POST /api/auth/token/refresh          Renouveler le token JWT
POST /api/auth/change-password        Changer le mot de passe (JWT requis)
GET  /api/auth/verify-email           Vérifier l'email (token dans query param)
```

### 3. Billing (Stripe)

```
POST /api/billing/checkout            Créer une session Stripe Checkout
POST /api/billing/checkout/confirm    Confirmer une session après paiement
GET  /api/billing/status              Statut abonnement + plan actuel
GET  /api/billing/portal              Lien vers le portail client Stripe
POST /api/billing/webhook             Webhook Stripe (signature HMAC obligatoire)
```

Événements Stripe traités : `checkout.session.completed`, `customer.subscription.deleted`,
`invoice.payment_failed`, `invoice.payment_succeeded`.

### 4. Gestion culturale

```
GET  /api/plants                      Liste des plants actifs (archivés exclus par défaut)
POST /api/plants                      Créer un plant (vérifie licence + quota plan)
GET  /api/plants/{id}                 Détail d'un plant
PATCH /api/plants/{id}                Modifier stade / statut / salle
GET  /api/plants/{id}/events          Journal d'événements

GET|POST        /api/farms            Fermes (soft-delete via DELETE → archivedAt)
DELETE          /api/farms/{id}       Soft-delete (archivedAt IS NULL dans les collections)
GET|POST|PATCH  /api/rooms            Salles de culture
GET|POST|PATCH  /api/sensors          Capteurs IoT
POST            /api/sensors/{id}/reading         Ingestion d'une lecture (ROLE_ORG_USER ou ROLE_API)
GET             /api/sensors/{id}/readings        Historique agrégé TimescaleDB (?period=7d|30d|90d|365d)
```

### 5. Rapports

Génération et exports réservés à `ROLE_ORG_ADMIN` (voir la matrice de permissions).

```
GET  /api/plants/{id}/report              Rapport PDF d'un plant (PLANT_VIEW + tenant)
POST /api/reporting/harvest-summary       Générer un résumé de récolte (admin)
POST /api/reporting/audit-export          Générer un export d'audit CSV (admin)
GET  /api/reporting/exports               Lister les exports de l'organisation (admin)
GET  /api/reporting/exports/{id}          Statut d'un export (admin + tenant)
GET  /api/reporting/exports/{id}/download Télécharger un export (admin + tenant)
GET  /api/compliance/ctsreport?month=YYYY-MM  Rapport CTS Health Canada (admin)
```

## Prérequis

- Docker Desktop (ou Docker Engine + Compose)
- Node.js 18+ (pour le frontend local)
- npm

## Lancer le backend (API Symfony)

1. Démarrer les conteneurs

```bash
docker compose up --build -d
```

Le service `app` attend PostgreSQL, applique automatiquement les migrations Doctrine,
puis démarre php-fpm + nginx.

Variables utiles dans `docker-compose.yml` :

- `APP_AUTO_BOOTSTRAP=1` : initialise automatiquement le schéma local au démarrage.
- `APP_BOOTSTRAP_SEED_DEMO=1` : rejoue le seed de démonstration idempotent.

2. Générer la paire de clés JWT locale (première installation)

```bash
docker exec -it cultivatrace_app php bin/generate-jwt-keys.php
```

3. Lancer les migrations manuellement si nécessaire

```bash
docker exec -it cultivatrace_app php bin/console doctrine:migrations:migrate
```

4. Injecter les données de démonstration locales

```bash
docker exec -it cultivatrace_app php bin/console app:seed-demo-data
```

Commande limitée aux environnements `dev` et `test`.

5. API disponible sur `http://localhost:8000/api`

Note :
- Un `401 JWT Token not found` sur `/api` est normal sans authentification.
- Le login JWT : `POST /api/auth/login`
- L'inscription organisation : `POST /api/register/organization`
- Les clés privées JWT restent hors Git dans `var/jwt/`.

## Variables d'environnement (production Railway)

Définir dans le dashboard Railway — ne jamais commiter dans Git :

| Variable | Rôle |
|----------|------|
| `APP_SECRET` | Clé CSRF Symfony (32 chars random) |
| `DATABASE_URL` | Endpoint poolé PgBouncer |
| `DATABASE_URL_DIRECT` | Endpoint direct (migrations uniquement) |
| `JWT_PASSPHRASE` | Passphrase clé privée JWT |
| `MERCURE_JWT_SECRET` | Secret hub Mercure |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe (`sk_live_*`) |
| `STRIPE_WEBHOOK_SECRET` | Secret de validation webhook (`whsec_*`) |
| `STRIPE_PRICE_STARTER/PRO/BUSINESS` | IDs de prix Stripe (`price_*`) |
| `MAILER_DSN` | DSN SMTP transactionnel |
| `FRONTEND_URL` | URL publique du frontend (CORS + emails) |
| `KYB_METRC_API_KEY` | Clé API METRC (si activé) |
| `SENTRY_DSN` | DSN Sentry (optionnel) |

Le conteneur `docker-entrypoint.sh` valide `APP_SECRET`, `DATABASE_URL`, `JWT_PASSPHRASE`
et `MERCURE_JWT_SECRET` au démarrage en `APP_ENV=prod` et s'arrête avec un message explicite
si l'une d'elles est absente.

## Lancer le frontend (Vue / Quasar)

```bash
cd frontend
npm install
npm run dev
```

Frontend disponible sur `http://localhost:5173`.

Flux de démo local :

1. Ouvrir `http://localhost:5173/auth/register` et créer une organisation
2. Compléter le KYB simulé sur `/kyb`
3. Depuis `/dashboard/overview`, créer une ferme, une salle, puis des plants
4. Les limites de plan (quota plants/farms) sont vérifiées côté serveur et affichées inline

## Tests

```bash
# Backend
APP_ENV=test php bin/phpunit

# Frontend
cd frontend
npm run test:unit   # vitest (composants, stores, router, services)
npm run test:e2e    # Playwright (parcours smoke + journeys)
npm run build       # build Vite (transpilation type-aware)
```

## Structure

```
src/          Symfony (Entity / State / Service / Doctrine / Infrastructure)
config/       Configuration Symfony / routes / packages
frontend/     Vue 3 (Vite + Pinia + Quasar)
migrations/   Migrations Doctrine numérotées
docker/       Templates Nginx
```

## Catalogue graines

Sync manuelle vers stockage externe :

```bash
php bin/console app:sync-seed-catalog
php bin/console app:sync-seed-catalog --no-upsert  # export seul
```

Snapshot versionné : `catalog/seed-catalog/humboldt-california-canada.json`

Les entrées fournisseur sont stockées dans le contexte borné `ExternalCatalogEntry`
(jamais directement dans `Genetic`). Pour relier une entrée externe à une génétique
interne validée, un service de mapping propose des liens `pending` (match par
code/nom normalisé) qu'un relecteur approuve ou rejette :

```bash
php bin/console app:catalog:propose-mappings   # crée les GeneticCatalogMapping en statut pending
```

L'approbation / le rejet (`CatalogMappingService::approve|reject`) enregistre le
relecteur et l'horodatage ; les génétiques internes restent souveraines et ne sont
jamais mutées par l'ingestion catalogue.

## Notes de développement

- Backend aligné sur PHP 8.4, Symfony 8.0, Doctrine ORM 3.
- `sensor_reading` utilise TimescaleDB si disponible (hypertable + rétention 90j + compression 7j) ; fallback PostgreSQL natif sinon.
- Le journal `PlantEvent` est protégé append-only au niveau ORM et par trigger PostgreSQL.
- Toutes les erreurs API suivent RFC 7807 (`application/problem+json`).
- Les plants archivés et les fermes archivées sont exclus des collections par défaut via `ArchiveFilterExtension`.
- Le serveur Vite proxifie `/api` vers `http://localhost:8000` pour éviter le CORS en dev.
