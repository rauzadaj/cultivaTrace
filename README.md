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

3. Lancer les migrations (si nécessaire)

```bash
docker exec -it cultivatrace_app php bin/console doctrine:migrations:migrate
```

4. API disponible sur :

- `http://localhost:8000/api`
- `http://localhost:8000/api/analytics/cycle-average`

Note :
- Un `401 JWT Token not found` sur `/api` est normal si vous n’êtes pas authentifié.

## Lancer le frontend (Vue / Vuetify)

Depuis le dossier `frontend` :

```bash
npm install
npm run dev
```

Frontend disponible sur :

- `http://localhost:5173`

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
- Le journal cultural est protégé en append-only au niveau ORM et base PostgreSQL.
- L’intégration Symfony Workflow est prévue au niveau du cycle de culture, mais le package `symfony/workflow` n’a pas pu être installé automatiquement dans cet environnement faute d’accès réseau au registre Composer.

## Roadmap (prochaine tranche)

- Wiring complet du composant Symfony Workflow pour les transitions `seedling -> veg -> flower -> harvest`
- Opérations métier dédiées pour les transitions de cycle et la récolte
- Corrélation avancée entre mesures environnementales et rendement
- Authentification frontend enrichie et vues opérateur
