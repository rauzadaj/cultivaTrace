# CultivaTrace MVP

Plateforme de traçabilité agricole avec journal append-only, suivi de cycle cultural et dashboard temps réel.

## Stack technique

- Symfony 8.0.7 + API Platform 4.2
- PHP 8.4
- PostgreSQL 13
- Auth JWT (LexikJWTAuthenticationBundle)
- Frontend Vue 3 + TypeScript + Pinia + Vite + Quasar
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

Le service `app` attend maintenant PostgreSQL, applique automatiquement les migrations Doctrine en `dev`, puis recharge le dataset de démonstration si le bootstrap auto reste activé. La disponibilité locale de l'application ne dépend plus de la synchronisation du catalogue graines fournisseur.

Variables utiles dans `docker-compose.yml` :

- `APP_AUTO_BOOTSTRAP=1` : initialise automatiquement le schéma local au démarrage.
- `APP_BOOTSTRAP_SEED_DEMO=1` : rejoue le seed de démonstration idempotent au démarrage du conteneur `app`.

Healthchecks Docker :

- `app` est sain dès que PostgreSQL est joignable avec la configuration applicative.
- `web` est sain dès que `GET /api/health` répond avec un backend prêt.

Variables à définir hors Git :

- Le fichier `.env` versionné ne contient que des placeholders sûrs.
- Pour le développement local, créez vos vraies valeurs dans `.env.local` ou injectez-les via votre shell.
- En environnement Railway, injectez les secrets dans le dashboard du service et non dans un fichier commité.
- Si une valeur sensible a déjà été exposée dans un historique Git ou un ancien environnement, considérez-la comme compromise et faites une rotation avant déploiement.
- Variables minimales à configurer dans Railway :
  - `APP_SECRET`
  - `DATABASE_URL`
  - `JWT_SECRET_KEY`
  - `JWT_PUBLIC_KEY`
  - `JWT_SECRET_KEY_BASE64`
  - `JWT_PUBLIC_KEY_BASE64`
  - `JWT_PASSPHRASE`
  - `MERCURE_URL`
  - `MERCURE_PUBLIC_URL`
  - `MERCURE_JWT_SECRET`
  - `GOTENBERG_URL`
  - `STRIPE_SECRET_KEY`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_PRICE_STARTER`
  - `STRIPE_PRICE_PRO`
  - `STRIPE_PRICE_BUSINESS`
  - `FRONTEND_URL`
  - `MAILER_DSN`
  - `LICENSE_ALERT_FROM_EMAIL`
  - `STRIPE_ALERT_FROM_EMAIL`
- En déploiement Railway standard, `JWT_SECRET_KEY` et `JWT_PUBLIC_KEY` pointent vers `/var/www/html/var/jwt/*.pem`, tandis que `JWT_SECRET_KEY_BASE64` et `JWT_PUBLIC_KEY_BASE64` servent à matérialiser ces fichiers au démarrage via `docker-entrypoint.sh`.
- Si les fichiers PEM sont absents en `prod` et qu’aucune variable base64 n’est fournie, le conteneur échoue immédiatement au démarrage.

2. Installer les dépendances Symfony (si nécessaire)

```bash
docker exec -it cultivatrace_app composer install
```

3. Générer la paire de clés JWT locale (si nécessaire)

```bash
docker exec -it cultivatrace_app php bin/generate-jwt-keys.php
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
- Le login JWT est exposé sur `POST /api/auth/login`.
- La création de compte est exposée sur `POST /api/register`.
- Les clés privées générées localement restent hors Git dans `var/jwt/`.

## Lancer le frontend (Vue / Quasar)

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
   - `Catalog` : bibliothèque visuelle des graines synchronisées avec image, génétique, description et lien source

Si `Catalog` affiche `0 entries`, le bootstrap local n'a pas encore synchronisé le catalogue graines. C'est désormais attendu : la sync du catalogue fournisseur est une opération manuelle explicite et ne bloque plus la disponibilité de l'application.

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

CultivaTrace expose une sync manuelle du catalogue graines vers un stockage externe dédié et un export JSON versionné. Les fiches fournisseur ne sont plus persistées directement dans `Genetic`, qui reste réservé aux variétés internes utilisées par les workflows métier.

Commande :

```bash
php bin/console app:sync-seed-catalog
```

Depuis Docker :

```bash
docker exec -it cultivatrace_app php bin/console app:sync-seed-catalog
```

Mode export seul :

```bash
php bin/console app:sync-seed-catalog --no-upsert
```

Snapshot versionne :

- `catalog/seed-catalog/humboldt-california-canada.json`

Statut observable :

- la commande retourne un code de sortie explicite en cas d'échec réseau ou d'upstream vide ;
- le snapshot JSON embarque `generatedAt`, utile pour vérifier la dernière sync réussie ;
- l'état de disponibilité de l'app reste observable via `GET /api/health`, indépendamment du catalogue fournisseur.

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
- `frontend/` : application Vue 3 (Vite + Pinia + Quasar)
- `docker/` : configuration Nginx
- `docker-compose.yml` : orchestration locale

## URLs locales

- API Platform : `http://localhost:8000/api`
- Frontend Vite : `http://localhost:5173`
- PostgreSQL : `localhost:5432`

## Notes de développement

- Le backend Docker et le runtime local sont désormais alignés sur PHP 8.4.
- Les workflows GitHub Actions backend sont eux aussi alignés sur PHP 8.4 pour rester compatibles avec les contraintes Composer du projet.
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
