# CultivaTrace MVP

Plateforme de traçabilité agricole avec journal append-only, suivi de cycle cultural et dashboard temps réel.

## Stack technique

- Symfony 6.4 + API Platform
- PostgreSQL 13
- Auth JWT (LexikJWTAuthenticationBundle)
- Frontend Vue 3 + TypeScript + Pinia + Vite + Vuetify
- Docker / Docker Compose

## Architecture

- `src/Domain/Cultivation` : coeur métier (`Crop`, `JournalEntry`, `Genetic`, enums, value objects)
- `src/Application/Cultivation` : services applicatifs et read models d’analyse
- `src/Infrastructure` : contrôleurs HTTP, subscriber d’erreurs, adaptateurs Doctrine
- `frontend/src/stores` : stores Pinia modulaires
- `frontend/src/components/dashboard` : dashboard temps réel et quick actions mobile-first

## Modèle métier livré

- `Crop` : lot cultural avec batch code unique, stade courant, dates de semis/récolte, rendement final
- `JournalEntry` : journal append-only exposé en lecture/écriture de création uniquement
- Les mutations `UPDATE` et `DELETE` du journal sont bloquées par Doctrine et par trigger PostgreSQL
- `Genetic` : variété génétique avec métadonnées flexibles stockées en JSON/JSONB
- `PhLevel` et `NutrientConcentration` : value objects métier pour les mesures critiques
- `GET /api/analytics/cycle-average` : agrégation de durée moyenne de cycle par variété
- `POST /api/crops/{id}/transitions/{transition}` : transitions de cycle pilotées par Symfony Workflow

## Prérequis

- Docker Desktop (ou Docker Engine + Compose)
- Node.js 18+ (pour le frontend local)
- npm

## Lancer le backend (API Symfony)

1. Démarrer les conteneurs

```bash
docker compose up --build -d
```

2. Installer les dépendances Symfony (si nécessaire)

```bash
docker exec -it cultivatrace_app composer install
```

3. Générer la paire de clés JWT locale (si nécessaire)

```bash
docker exec -it cultivatrace_app php bin/console lexik:jwt:generate-keypair --overwrite
```

4. Lancer les migrations (si nécessaire)

```bash
docker exec -it cultivatrace_app php bin/console doctrine:migrations:migrate
```

5. Injecter les données de démonstration locales

```bash
docker exec -it cultivatrace_app php bin/console app:seed-demo-data
```

Cette commande est volontairement limitée aux environnements `dev` et `test`.
En réexécution, elle réinitialise explicitement le compte de démo `demo@cultivatrace.local` avec le mot de passe `demo123` pour garantir un bootstrap local déterministe.

6. API disponible sur :

- `http://localhost:8000/api`
- `http://localhost:8000/api/analytics/cycle-average`
- `http://localhost:8000/api/crops/{id}/transitions/start_vegetative`
- `http://localhost:8000/api/crops/{id}/transitions/start_flowering`
- `http://localhost:8000/api/crops/{id}/transitions/harvest`

Note :
- Un `401 JWT Token not found` sur `/api` est normal si vous n’êtes pas authentifié.
- Le login JWT est exposé sur `POST /api/login`.
- La création de compte est exposée sur `POST /api/register`.
- Les clés privées générées localement restent ignorées par Git via `config/jwt/*.pem`.

## Lancer le frontend (Vue / Vuetify)

Depuis le dossier `frontend` :

```bash
npm install
npm run dev
```

Frontend disponible sur :

- `http://localhost:5173`

Flux de démo local :

1. Ouvrir `http://localhost:5173/auth`
2. Se connecter avec `demo@cultivatrace.local` / `demo123`, ou créer un compte via l’onglet `Sign up`
3. Laisser `API base URL` sur `/api`
4. Après authentification, la redirection vers `/dashboard` est automatique

## Tests

### Backend / qualité Composer

```bash
composer validate --strict
```

### Backend / unit tests

```bash
vendor/bin/phpunit
```

### Backend / migrations

```bash
php bin/console doctrine:migrations:migrate
```

### Backend / workflow

```bash
php bin/console debug:config framework workflows
```

### Frontend (build)

```bash
cd frontend
npm run build
```

## Structure rapide

- `src/` : code Symfony (Domain / Application / Infrastructure)
- `config/` : configuration Symfony / routes / packages
- `frontend/` : application Vue 3 (Vite + Pinia + Vuetify)
- `docker/` : configuration Nginx
- `docker-compose.yml` : orchestration locale

## URLs locales

- API Platform : `http://localhost:8000/api`
- Frontend Vite : `http://localhost:5173`
- PostgreSQL : `localhost:5432`

## Notes de développement

- Le backend Docker utilise PHP 8.3 (aligné avec les dépendances verrouillées).
- Le runtime de test local exécute aussi correctement PHPUnit sous PHP 8.4.
- Le frontend cible un dashboard opérationnel temps réel et des quick actions terrain.
- Le frontend embarque désormais un routeur avec garde d’authentification et redirection automatique vers `/auth` sur `401`.
- Le dashboard adopte maintenant une interface admin Material avec navigation latérale, cartes de supervision et panneaux opérationnels temps réel.
- Le serveur Vite proxifie `/api` vers `http://localhost:8000` pour éviter le CORS en démo locale.
- Le journal cultural est protégé en append-only au niveau ORM et base PostgreSQL.
- Le composant `symfony/workflow` orchestre désormais les transitions `seedling -> veg -> flower -> harvest`.
- Chaque transition de cycle ajoute une entrée append-only de type `stage_transition` dans le journal du lot.

## Roadmap (prochaine tranche)

- Opérations métier dédiées pour les transitions de cycle et la récolte
- Corrélation avancée entre mesures environnementales et rendement
- Authentification frontend enrichie et vues opérateur
