# CultivaTrace MVP

Plateforme de traçabilité agricole avec journal append-only, suivi de cycle cultural et dashboard temps réel.

## Stack technique

- Symfony 8.0.7 + API Platform 4.2
- PHP 8.4
- PostgreSQL 13
- Auth JWT (LexikJWTAuthenticationBundle)
- Frontend Vue 3 + TypeScript + Pinia + Vite + Vuetify
- Docker / Docker Compose

## Architecture

- `src/Domain/Cultivation` : coeur métier (`Crop`, `JournalEntry`, `Genetic`, enums, value objects)
- `src/Application/Cultivation` : services applicatifs et read models d’analyse
- `src/Domain/Operations` : catalogue métier des services opérationnels exposés en CRUD
- `src/Infrastructure` : contrôleurs HTTP, subscriber d’erreurs, adaptateurs Doctrine
- `frontend/src/stores` : stores Pinia modulaires
- `frontend/src/components/dashboard` : dashboard temps réel, menu SaaS Material et écrans CRUD par section

## Modèle métier livré

- `Crop` : lot cultural avec batch code unique, stade courant, dates de semis/récolte, rendement final
- `JournalEntry` : journal append-only exposé en lecture/écriture de création uniquement
- Les mutations `UPDATE` et `DELETE` du journal sont bloquées par Doctrine et par trigger PostgreSQL
- `Genetic` : variété génétique avec métadonnées flexibles stockées en JSON/JSONB
- `OperationalService` : service opérationnel éditable côté console avec ordre d’affichage, icône et tonalité
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

Si vous migrez depuis un runtime plus ancien, forcez le rebuild des services PHP/Nginx :

```bash
docker compose up -d --build --force-recreate app web
```

Le service `app` attend maintenant PostgreSQL, applique automatiquement les migrations Doctrine en `dev`, puis recharge le dataset de démonstration si le bootstrap auto reste activé.

Variables utiles dans `docker-compose.yml` :

- `APP_AUTO_BOOTSTRAP=1` : initialise automatiquement le schéma local au démarrage.
- `APP_BOOTSTRAP_SEED_DEMO=1` : rejoue le seed de démonstration idempotent au démarrage du conteneur `app`.

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

En environnement Docker local, cette étape est désormais automatique tant que `APP_AUTO_BOOTSTRAP=1`.

5. Injecter les données de démonstration locales

```bash
docker exec -it cultivatrace_app php bin/console app:seed-demo-data
```

Cette commande est volontairement limitée aux environnements `dev` et `test`.
En réexécution, elle réinitialise explicitement le compte de démo `demo@cultivatrace.local` avec le mot de passe `demo123` pour garantir un bootstrap local déterministe.
En environnement Docker local, cette étape est aussi automatique tant que `APP_BOOTSTRAP_SEED_DEMO=1`.

6. Vérifier la version Symfony / PHP dans le conteneur (optionnel)

```bash
docker compose exec -it cultivatrace_app php bin/console about
```

7. API disponible sur :

- `http://localhost:8000/api`
- `http://localhost:8000/api/analytics/cycle-average`
- `http://localhost:8000/api/operational_services`
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
4. Après authentification, la redirection vers `/dashboard/overview` est automatique
5. Le menu latéral permet ensuite d'accéder à `Overview`, `Lots`, `Services` et `Analytics`
6. Chaque section expose désormais des écrans de gestion:
   - `Lots` : création, édition, suppression et transitions de cycle
   - `Services` : CRUD complet du catalogue opérationnel
   - `Analytics` : CRUD des variétés génétiques et lecture des moyennes de cycle
   - `Overview` : ajout append-only d’entrées de journal

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

## Catalogue graines verifie

CultivaTrace embarque maintenant une sync de catalogue graines vers `Genetic` et un export JSON versionne.

Commande :

```bash
php bin/console app:sync-seed-catalog
```

Mode export seul :

```bash
php bin/console app:sync-seed-catalog --no-upsert
```

Snapshot versionne :

- `catalog/seed-catalog/humboldt-california-canada.json`

Source amont unique retenue :

- `https://humboldtseedcompany.com`

Pourquoi cette source :

- le meme domaine publie les fiches varietales, les images produit, les descriptions et la lineage/parentals
- le domaine expose aussi les pages de distribution marche US/Canada, ce qui permet de rester sur une seule origine de donnees
- le `page-sitemap.xml` du domaine sert de source exhaustive pour les pages produit actuellement publiees

Portee legale exacte :

- il n'existe pas de registre gouvernemental exhaustif des noms de varietes autorisees en Californie ou au Canada
- le catalogue est donc exhaustif a l'echelle de la source retenue et borne aux varietes commercialisees par ce fournisseur pour ces marches
- Californie : la conformite depend du fait que les graines ou plantes proviennent d'une source licenciee, pas d'une whitelist de strain names
- Canada : la conformite depend de l'achat de graines/semis legaux et des limites de culture applicables localement

References officielles verifiees :

- Canada : `https://www.canada.ca/en/health-canada/services/drugs-medication/cannabis/personal-use/growing-cannabis-home-safely.html`
- Californie, culture a domicile : `https://cannabis.ca.gov/consumers/whats-legal/`
- Californie, transfert aux consommateurs via nursery/licensed goods : `https://govt.westlaw.com/calregs/Document/IB654858A4D8C4A179AA3A8A7DAA20E5F`

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

- Le backend Docker et le runtime local sont désormais alignés sur PHP 8.4.
- Le noyau backend tourne maintenant sur Symfony 8.0.7.
- DoctrineBundle a été migré en 3.2 pour ouvrir la compatibilité Symfony 8.
- Le conteneur PHP réapplique automatiquement les permissions de `var/` au démarrage pour garder `cache/` et `log/` inscriptibles après recréation des volumes Docker.
- Le frontend cible un dashboard opérationnel temps réel et des quick actions terrain.
- Le frontend embarque désormais un routeur avec garde d’authentification et redirection automatique vers `/auth` sur `401`.
- Le dashboard adopte maintenant une interface admin Material dense, plus proche d'une console SaaS d'exploitation avec navigation latérale, logo unifié et écrans CRUD par section.
- Le serveur Vite proxifie `/api` vers `http://localhost:8000` pour éviter le CORS en démo locale.
- Le journal cultural est protégé en append-only au niveau ORM et base PostgreSQL.
- Le composant `symfony/workflow` orchestre désormais les transitions `seedling -> veg -> flower -> harvest`.
- Chaque transition de cycle ajoute une entrée append-only de type `stage_transition` dans le journal du lot.

## Roadmap (prochaine tranche)

- Opérations métier dédiées pour les transitions de cycle et la récolte
- Corrélation avancée entre mesures environnementales et rendement
- Authentification frontend enrichie et vues opérateur
