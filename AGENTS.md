# CannaSaaS — Instructions Agents (Jalon 4)

**Lis ce fichier en entier avant de coder quoi que ce soit.**

## Stack (définitif)

- Backend : Symfony 6.4 LTS + API Platform 3
- Frontend : Vue 3 + Quasar Framework
- DB : PostgreSQL 16 + TimescaleDB (sensor_reading)
- Auth : LexikJWTAuthenticationBundle
- PDF : GotenbergBundle
- Temps réel : Mercure Hub SSE (port 9000)
- Paiement : Stripe PHP SDK (stripe/stripe-php)
- Monorepo : src/ (backend) + frontend/src/ (frontend)

## Jalons complétés

- Jalon 1 ✅ — Multi-tenant, SYNC-01 validé
- Jalon 2 ✅ — Plant, audit trail, harvest, destruction, PDF, CTS
- Jalon 3 ✅ — IoT, Mercure SSE, VPD, SYNC-04 validé
- Jalon 4 🔄 — KYB, Stripe, limites plan, dashboard KPIs

## Fichiers livrés dans ce jalon

Backend :
- src/Entity/LicenseDocument.php
- src/Service/KybService.php
- src/Service/StripeService.php
- src/Service/PlanLimitsService.php
- src/Service/PlanLimitExceededException.php
- src/Controller/KybController.php
- src/Controller/StripeController.php
- src/Controller/DashboardController.php
- src/Scheduler/LicenseExpirationScheduler.php

Frontend :
- frontend/src/views/auth/KybView.vue
- frontend/src/views/billing/BillingView.vue

## Règle #1 — Multi-tenant (SYNC-01 validé ✅)

TenantListener priority = -10.
Toute entité métier a tenantId. TenantFilter actif sur toutes les requêtes.

## Règle #2 — Audit Trail APPEND-ONLY

Jamais de UPDATE ni DELETE sur PlantEvent.
Toujours PlantEventRepository::appendEvent().

## Règle #3 — KYB — Règles non-contournables

- Un utilisateur sans licence active (pending/rejected/expired/suspended)
  ne peut pas créer de plants, salles ou capteurs
- En dev : KybService simule une approbation automatique
- En prod : METRC via API, Health Canada en manuel, BfArM en manuel
- Le status pending donne un accès lecture seule uniquement

## Règle #4 — Stripe — Règles non-contournables

- Ne jamais stocker une carte bancaire ou données Stripe sensibles en base
- stripeCustomerId est la seule donnée Stripe stockée sur Organization
- Toujours vérifier la signature du webhook (Webhook::constructEvent)
- Le webhook /api/billing/webhook doit toujours retourner 200
  même en cas d'erreur — pour éviter les retries Stripe infinis

## Règle #5 — Plan Limits

- PlanLimitsService::checkPlantLimit() appelé dans PlantStateProcessor
  avant toute création de plant
- HTTP 402 (Payment Required) si limite atteinte — avec toArray()
- Ne jamais vérifier les limites côté frontend uniquement

## Règle #6 — stripeCustomerId sur Organization

L'entité Organization doit avoir ce champ.
Si absent → ajouter + migration avant de déployer StripeService.

## Règle #7 — Ce que tu ne fais JAMAIS sans validation humaine

- Modifier TenantFilter, TenantListener, Voters RBAC
- Changer la logique hash-chaining
- Bypasser la vérification de signature Stripe webhook
- Bypasser les limites de plan côté serveur

## Règle #8 — Commits

Format : feat(TICKET-ID): description courte

## Points de synchronisation

- SYNC-01 ✅ — Isolation multi-tenant
- SYNC-02 ✅ — Migrations jalon 2
- SYNC-03 ✅ — PDF généré (validation réglementaire externe à faire)
- SYNC-04 ✅ — IoT dashboard temps réel
- SYNC-05 🔄 — Jalon 4 : tester le flow Stripe complet en sandbox
  (checkout → paiement test → webhook → plan mis à jour)
