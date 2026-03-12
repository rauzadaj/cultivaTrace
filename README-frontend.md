# Frontend CultivaTrace

Frontend Vue 3 + TypeScript pour le dashboard de suivi cultural en temps réel.

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
- `src/stores/useCropStore.ts` : chargement dashboard, polling, quick actions
- `src/router/index.ts` : routes publiques / protégées et garde d’authentification
- `src/views/AuthView.vue` : écran de connexion / création de compte
- `src/components/dashboard/CropDashboard.vue` : vue principale temps réel
- `src/components/dashboard/QuickActionButtons.vue` : saisie terrain rapide
- `src/components/AppErrorBoundary.vue` : fallback UI global

## Contrat frontend

- Consommation Hydra sur `/api/crops` et `/api/journal_entries`
- Consommation JSON sur `/api/analytics/cycle-average`
- Typage strict dans `src/types/api.ts`

## Démo locale

- L’URL API par défaut est `/api`
- Le serveur Vite proxifie `/api` vers `http://localhost:8000`
- L’entrée se fait par `http://localhost:5173/auth`
- La connexion utilise `POST /api/login`
- La création de compte utilise `POST /api/register`
- Toute réponse `401` côté API purge la session locale et redirige vers `/auth`
- La vue principale suit désormais une direction visuelle admin Material dense avec sidebar, table d'opérations, services et listes d'actions terrain
