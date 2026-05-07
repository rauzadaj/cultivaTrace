# Cultivation Domain — Deferred (MVP)

## Decision

CultivaTrace MVP adopts a **plant-centric** model.  Every regulatory unit is a
`Plant` entity with a direct lifecycle (`stage`, `rfidTag`, `room`).

The batch/crop aggregate (`Crop`, `Genetic`, `JournalEntry`) and its supporting
infrastructure (`CropLifecycleManager`, `CropLifecycleTransitionController`,
`CropTenantSubscriber`, `DoctrineCropCycleAnalyticsGateway`) are **retained in
the codebase but intentionally deferred**.  They are not exposed in the public
API, not linked from the frontend, and not documented in the API reference.

Classes in this domain carry `@internal` and `@todo(mvp-deferred)` PHPDoc tags
as a machine-readable signal.

## Why the code stays

Deleting the domain would destroy the schema migrations, workflow configuration,
and design work already done.  Keeping it dormant allows a zero-cost promotion
later without a greenfield rebuild.

## API Platform surface suppressed

| Entity         | Change                         |
|----------------|--------------------------------|
| `JournalEntry` | `operations: []` — no routes   |
| `Genetic`      | `operations: []` — no routes   |
| `Crop`         | No `#[ApiResource]` (unchanged) |

The route `POST /api/crops/{id}/transitions/{transition}` remains registered in
Symfony's router but is not documented, not authenticated through any public
scope, and not reachable from the frontend.

## Promotion criteria

Promote this domain to production when ALL of the following are met:

1. At least one paying customer requests batch/lot tracking explicitly.
2. A frontend `CropView` and `CropForm` are implemented and reviewed.
3. KYB + billing guards are extended to cover the new write endpoints.
4. A migration is written to backfill `crop.tenant_id` for any seeded data.
5. The `@internal` / `@todo(mvp-deferred)` markers are removed from all classes.
