# CannaSaaS — Instructions Agents (Jalon 2)

**Lis ce fichier en entier avant de coder quoi que ce soit.**

## Stack (définitif)

- Backend : **Symfony 6.4 LTS + API Platform 3** — pas NestJS, pas Node.js
- Frontend : **Vue 3 + Quasar Framework** — pas Vuetify, pas Next.js
- DB : PostgreSQL 16 + TimescaleDB (Jalon 3)
- Auth : LexikJWTAuthenticationBundle (JWT stateless)
- PDF : **GotenbergBundle** (sensiolabs/gotenberg-bundle) — pas wkhtmltopdf, pas dompdf
- Temps réel : Mercure Hub SSE (Jalon 3)
- Queue : Symfony Messenger
- Monorepo : `apps/backend/` + `apps/frontend/` + `infra/`

## Jalon actuel : Jalon 2 — Cœur du produit

### Fichiers livrés dans ce jalon

Backend :
- `Entity/Strain.php` — génétiques avec cannabisType (hemp|marijuana) + THC%
- `Entity/InputRecord.php` — intrants avec LoQ Health Canada + quarantaine auto
- `Entity/HarvestRecord.php` — données de récolte (1-to-1 avec Plant)
- `Entity/DestructionIntent.php` — workflow destruction 7j + ratio 50%
- `Repository/PlantEventRepository.php` — APPEND-ONLY + hash-chain auto
- `Controller/HarvestController.php` — transaction atomique harvest
- `Controller/DestructionController.php` — workflow destruction 2 étapes
- `Controller/PlantReportController.php` — PDF via GotenbergBundle
- `Controller/CTSReportController.php` — CSV Health Canada CTS
- `Security/Voter/PlantVoter.php` — RBAC plants
- `templates/pdf/plant_report.html.twig` — template PDF

## Règle #1 — Multi-tenant (SYNC-01 validé ✅)

**TenantListener priority = -10** (après le firewall JWT en priorité 8)

- Toute entité métier a un champ `tenantId` (UUID)
- Le `TenantFilter` est activé automatiquement sur chaque requête authentifiée
- Le `tenantId` vient du JWT — jamais du body de la requête
- Ne jamais exposer `tenantId` dans les réponses API

## Règle #2 — Audit Trail APPEND-ONLY

- Jamais de `UPDATE` ni de `DELETE` sur `PlantEvent`
- Toujours utiliser `PlantEventRepository::appendEvent()`
- Le hash-chaining est calculé automatiquement dans `appendEvent()`

## Règle #3 — Transactions obligatoires

Ces opérations doivent être dans une transaction `beginTransaction/commit/rollback` :
- `POST /api/plants/{id}/harvest` — HarvestRecord + Plant update + PlantEvent
- `POST /api/destructions/{id}/confirm` — DestructionIntent + Plant update + PlantEvent

## Règle #4 — Règles réglementaires non-contournables

- Destruction : délai légal minimum 7 jours (`canBeConfirmed()`)
- Destruction : ratio non-cannabis >= 50% (`isNonCannabisRatioValid()`)
- Destruction : photos obligatoires
- LoQ pesticides : `computeTestResult()` appelé automatiquement après saisie
- LoQ fail → quarantaine automatique (`quarantinedAt = now`)
- Règle 7 jours Health Canada : si testResult = fail, alerte à J0, J3, J6, rapport à J7

## Règle #5 — PDF

Utiliser **GotenbergBundle** (sensiolabs/gotenberg-bundle) — pas de client HTTP custom.

```php
// Injection dans le controller :
public function __construct(private readonly GotenbergPdfInterface $gotenberg) {}

// Génération :
$pdf = $this->gotenberg->html()->content('pdf/template.html.twig', $context)->generate();
```

## Règle #6 — Types TypeScript

Un seul fichier : `apps/frontend/src/types/api.ts`
Ne jamais définir de types inline dans les composants.

## Règle #7 — Appels API Frontend

Tous les appels HTTP passent par `apps/frontend/src/services/api.ts`.
Un 401 déclenche le logout automatique.

## Règle #8 — UI Framework

**Quasar** (pas Vuetify). Composants préfixe Q. Touch targets >= 48px.

## Règle #9 — Ce que tu ne fais JAMAIS sans validation humaine

- Modifier le schéma d'une entité existante (Plot→Room, CropActivity, User)
- Changer la logique de hash-chaining dans `HashChainService`
- Modifier `TenantFilter`, `TenantListener`, ou les Voters RBAC
- Décider du format des rapports réglementaires (CTS, BfArM)
- Contourner les règles de destruction (7j, 50%, photos)
- Contourner la logique LoQ et la quarantaine automatique

## Règle #10 — Commits

Format : `feat(TICKET-ID): description courte`

## Points de synchronisation

- **SYNC-01** ✅ Validé — Isolation multi-tenant vérifiée
- **SYNC-02** — Après migrations jalon 2 : valider les 4 nouvelles tables et colonnes LoQ
- **SYNC-03** — Après rapport PDF : validation par contact BfArM ou Health Canada
- **SYNC-04** — Après IoT Mercure (Jalon 3) : valider en staging

## Services Docker requis (Jalon 2)

Ajouter dans `docker-compose.yml` :

```yaml
gotenberg:
  image: gotenberg/gotenberg:8
  ports:
    - "3000:3000"
  restart: unless-stopped
```

Variable `.env` :
```
GOTENBERG_URL=http://gotenberg:3000
```
