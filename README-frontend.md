# Frontend CultivaTrace

Frontend Vue 3 + TypeScript pour le dashboard de suivi cultural en temps réel.

Le backend consommé par ce frontend tourne désormais sur Symfony 8.0.7 / PHP 8.4.

## Installation

1. Se placer dans le dossier `frontend`

```bash
cd frontend
```

2. Installer les dépendances

```bash
npm install
```

3. Lancer le serveur de développement

```bash
npm run dev
```

4. Générer le bundle de production

```bash
npm run build
```

## Structure

- `src/stores/useUserStore.ts` : contexte opérateur, token, base URL API
- `src/stores/useCropStore.ts` : chargement dashboard, polling, quick actions, CRUD lots/génétiques/services
- `src/router/index.ts` : routes publiques / protégées et garde d’authentification
- `src/views/AuthView.vue` : écran de connexion / création de compte
- `src/components/dashboard/CropDashboard.vue` : shell principal avec sidebar/logo/topbar
- `src/components/dashboard/OverviewSection.vue` : overview + ajout d’entrées journal append-only
- `src/components/dashboard/LotsSection.vue` : CRUD lots + transitions de cycle
- `src/components/dashboard/ServicesSection.vue` : CRUD services opérationnels
- `src/components/dashboard/AnalyticsSection.vue` : CRUD génétiques + lecture analytics
- `src/components/dashboard/CatalogSection.vue` : catalogue visuel des graines avec image, génétique, description et lien source
- `src/components/dashboard/QuickActionButtons.vue` : saisie terrain rapide
- `src/components/AppErrorBoundary.vue` : fallback UI global

## Contrat frontend

- Consommation Hydra sur `/api/crops` et `/api/journal_entries`
- Consommation Hydra sur `/api/genetics` et `/api/operational_services`
- Consommation JSON sur `/api/analytics/cycle-average`
- Typage strict dans `src/types/api.ts`

## Démo locale

- L’URL API par défaut est `/api`
- Le serveur Vite proxifie `/api` vers `http://localhost:8000`
- L’entrée se fait par `http://localhost:5173/auth`
- La connexion utilise `POST /api/login`
- La création de compte utilise `POST /api/register`
- Toute réponse `401` côté API purge la session locale et redirige vers `/auth`
- La vue principale suit désormais une direction visuelle admin Material dense avec logo unifié, sidebar, table d'opérations, services et listes d'actions terrain
- Le menu latéral route maintenant vers `Overview`, `Lots`, `Services`, `Analytics` et `Catalog`
- Chaque route du menu est maintenant exploitable avec des écrans de création, mise à jour et suppression adaptés au domaine métier
- `Catalog` expose les graines synchronisées depuis le catalogue vérifié Humboldt avec cartes image + lineage + description
