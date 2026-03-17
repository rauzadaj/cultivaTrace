# CannaSaaS — Instructions Agents

**Lis ce fichier en entier avant de coder quoi que ce soit.**

## Stack

- Backend : Symfony 6.4 LTS + API Platform 3 + Doctrine ORM
- Frontend : Vue 3 (Composition API) + Vuetify 3 + Pinia + Axios
- DB : PostgreSQL 16 + TimescaleDB (IoT)
- Auth : LexikJWTAuthenticationBundle — JWT stateless
- PDF : Gotenberg (service Docker)
- Temps réel : Mercure Hub (SSE) — pas de WebSocket
- Queue : Symfony Messenger
- Structure : monorepo — `apps/backend/` + `apps/frontend/` + `infra/`

## Règle #1 — Multi-tenant

**Toute entité métier a un champ `tenantId` (UUID).** Aucune exception.

- Le `TenantFilter` Doctrine est activé automatiquement sur chaque requête authentifiée
- Le `tenantId` vient du JWT — jamais du body de la requête
- Ne jamais exposer `tenantId` dans les réponses API
- Ne jamais faire de `findAll()` sans filtre tenant
- Fichier : `apps/backend/src/Doctrine/TenantFilter.php`

## Règle #2 — Audit Trail (PlantEvent)

**La table `plant_event` est APPEND-ONLY.**

- Jamais de `UPDATE` ni de `DELETE` sur `PlantEvent`
- Chaque event a un `hashSelf` = SHA-256(id + payload + occurredAt + hashPrevious)
- Utiliser `PlantEventRepository::appendEvent()` — jamais `EntityManager::persist()` direct
- Fichier : `apps/backend/src/Entity/PlantEvent.php`

## Règle #3 — Types TypeScript

**Un seul fichier de types : `apps/frontend/src/types/api.ts`**

- Ne jamais définir de types inline dans les composants
- Ne jamais dupliquer un type qui existe déjà dans ce fichier
- Si un type manque, l'ajouter dans `api.ts` et signaler la modification

## Règle #4 — Appels API Frontend

**Tous les appels HTTP passent par `apps/frontend/src/services/api.ts`**

- Jamais d'Axios direct dans un composant ou un store
- Le service injecte le JWT automatiquement via intercepteur
- Un 401 déclenche le logout automatique via `useAuthStore`

## Règle #5 — Ce que tu ne fais JAMAIS sans validation humaine

- Modifier le schéma d'une entité existante (`Plot`, `CropActivity`, `User`)
- Changer la logique de hash-chaining dans `HashChainService`
- Modifier `TenantFilter` ou les Voters RBAC
- Décider du format des rapports réglementaires (CTS, BfArM, ANSM)
- Intégrer une API tierce non listée dans ce fichier

## Règle #6 — Commits

Format obligatoire : `feat(TICKET-ID): description courte`

Exemples :
- `feat(BE-001): add Organization entity and TenantFilter`
- `feat(FE-003): add plant list view with grid and filters`
- `fix(BE-003): correct hash chain on first event (no previous)`

## Entités existantes — ne pas supprimer

| Fichier actuel | Nouveau nom | Action |
|---|---|---|
| `Plot.php` | → `Room.php` | Migrer (ajouter tenantId, capacityMax, type, farmId) |
| `CropActivity.php` | → `PlantEvent.php` | Migrer (lier à Plant, pas à Plot) |
| `User.php` | Inchangé | Ajouter tenantId + roles |

## Points de synchronisation — STOP et attendre validation humaine

- **SYNC-01** : Après `BE-001` (TenantFilter) — tester isolation avant tout développement métier
- **SYNC-02** : Après `BE-003` (Plant + PlantEvent) — valider schéma avant les rapports PDF
- **SYNC-03** : Après `BE-006` (rapport PDF) — validation par contact réglementaire externe
- **SYNC-04** : Après `INF-003` (IoT Mercure) — valider en staging avant beta

## Agents et périmètres

| Agent | Stack | Dossier | Ne touche pas à |
|---|---|---|---|
| BACKEND | Symfony / Doctrine | `apps/backend/src/` | `apps/frontend/` |
| FRONTEND | Vue / Vuetify / Pinia | `apps/frontend/src/` | `apps/backend/` |
| INFRA | Docker / Terraform | `infra/` | `apps/` |
