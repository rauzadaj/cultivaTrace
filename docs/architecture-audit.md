# CultivaTrace Architecture Audit

## Scope

This audit consolidates the current structural gaps identified across the Symfony backend, Vue frontend, delivery flow, and operational tooling. The goal is to convert these findings into trackable GitHub issues and an actionable roadmap.

## Findings

### P1 - API authorization is too coarse

Current access control only distinguishes public authentication endpoints from the rest of `/api`. Core resources still expose write operations without per-operation authorization rules.

Impact:
- Any authenticated user can mutate critical business objects.
- The current model is incompatible with production multi-user SaaS constraints.

Recommended action:
- Introduce explicit roles such as `ROLE_ADMIN`, `ROLE_OPERATOR`, and `ROLE_VIEWER`.
- Add `security` rules per API Platform operation.
- Implement voters for domain-sensitive mutations.

### P1 - Legacy API resources are still exposed

`Plot` and `CropActivity` remain exposed as API resources even though the active codebase now follows a Domain/Application/Infrastructure split elsewhere.

Impact:
- Dead or ambiguous endpoints stay publicly reachable.
- The public contract no longer reflects the intended domain model.

Recommended action:
- Remove these endpoints or migrate them into the current domain architecture.
- Clean associated persistence and documentation drift.

### P1 - Application startup depends on an external catalog source

Local bootstrap still relies on vendor catalog synchronization during environment preparation.

Impact:
- Startup time depends on external network availability.
- A non-critical integration can delay app readiness.

Recommended action:
- Keep bootstrap idempotent for schema and demo seed only.
- Move catalog synchronization to a dedicated command or async process.

### P2 - External seed catalog and internal genetics are conflated

Vendor catalog entries are synchronized directly into the internal `Genetic` repository.

Impact:
- Internal and external reference data share the same CRUD surface.
- Supplier-sourced data can be manually altered as if it were internal master data.

Recommended action:
- Introduce a dedicated external catalog model.
- Define a controlled mapping between vendor catalog entries and internal genetics.

Target architecture:
- `ExternalCatalog` remains a separate bounded context from the internal `GeneticRegistry`.
- A mapping layer links external catalog entries to internal validated genetics.
- Internal records are the authoritative sheets used for business operations.
- External records are ingested for enrichment and sourcing, but are never sovereign business data.

### P2 - Frontend refresh strategy is not scalable

The dashboard still refreshes broad data sets on a timer rather than using targeted loading strategies.

Impact:
- Network cost grows with journal, catalog, and crop volume.
- UI responsiveness degrades as the dataset scales.

Recommended action:
- Replace full refresh patterns with section-level loading.
- Add pagination, filtered endpoints, and aggregated dashboard endpoints.

### P2 - Test coverage does not protect critical flows

There are useful unit tests, but there is still no meaningful end-to-end safety net for authentication, routing, CRUD workflows, and dashboard runtime integrity.

Impact:
- Regressions can reach production undetected.
- Local validation remains heavily manual.

Recommended action:
- Add backend integration tests.
- Add frontend unit/component tests.
- Add Playwright smoke coverage for the main product journey.

### P2 - Authentication and onboarding remain demo-oriented

The project still carries demo-centric authentication behaviors and lacks production-grade anti-abuse controls.

Impact:
- The auth flow is not suitable for a production tenant environment.
- Registration can be abused or misused.

Recommended action:
- Remove demo shortcuts from the standard UX.
- Add rate limiting and stronger registration constraints.
- Clarify production versus demo behavior.

### P3 - Documentation is drifting from the actual product

The documentation is functional but not yet contractual. Some sections already diverge from the live dashboard and current technical stack.

Impact:
- Onboarding becomes slower.
- Documentation cannot be treated as a reliable source of truth.

Recommended action:
- Rebuild documentation around architecture, operations, security, and product surfaces.

## Roadmap

1. Secure the API with RBAC and operation-level authorization.
2. Remove or migrate legacy API resources.
3. Decouple runtime bootstrap from external catalog synchronization.
4. Split external catalog data from internal genetics.
5. Rework dashboard data loading and scalability.
6. Build a full testing pyramid.
7. Harden authentication and onboarding.
8. Rewrite project documentation as a contractual source.

## Execution Prompts

### 1. RBAC hardening

```text
Audit and implement a complete RBAC strategy for CultivaTrace. Add role separation for admin, operator, and viewer. Protect Crop, Genetic, OperationalService, and JournalEntry with per-operation API Platform security rules and Symfony voters. Cover custom controllers as well. Add authorization tests and update the README with a permission matrix.
```

### 2. Legacy API cleanup

```text
Audit the remaining legacy ApiResource entities in src/Entity and remove or migrate them into the current DDD architecture. Specifically assess Plot and CropActivity, remove dead endpoints, update persistence if needed, and align the public API documentation with the remaining supported resources.
```

### 3. Bootstrap decoupling

```text
Refactor the local bootstrap flow so the application becomes available without waiting for external catalog synchronization. Keep migrations and demo seed idempotent during startup, move vendor catalog sync to an explicit async or manual step, add healthchecks, and document the startup contract.
```

### 4. External catalog domain split

```text
Introduce a dedicated ExternalCatalog bounded context instead of persisting supplier entries directly into Genetic. Keep ExternalCatalog separate from the internal GeneticRegistry, add an explicit mapping layer between the two, make internal validated records the only authoritative business sheets, and keep external ingested records non-sovereign and non-editable from the admin UI. Migrate existing data safely.
```

### 5. Dashboard performance redesign

```text
Optimize the CultivaTrace dashboard data flow. Replace broad timed refreshes with section-level loading, pagination, filtered reads, and dedicated aggregated backend endpoints. Reduce redundant fetching after mutations and add tests or metrics proving the improvement.
```

### 6. Testing pyramid

```text
Add a real testing pyramid to CultivaTrace. Introduce backend integration tests for auth, CRUD, workflow, and error handling; frontend tests for routing, guards, and dashboard sections; and Playwright smoke coverage for login, navigation, and core CRUD paths. Remove placeholder smoke tests.
```

### 7. Authentication hardening

```text
Turn the current authentication flow into a production-grade onboarding flow. Remove demo shortcuts from the normal login experience, add rate limiting and stronger validation to registration, clarify demo versus production behavior, and update both backend and frontend documentation accordingly.
```

### 8. Documentation rewrite

```text
Rewrite the CultivaTrace documentation so it matches the actual product and architecture. Update README files, document dashboard sections, workflows, bootstrap behavior, catalog synchronization, security model, and operational setup. Keep the result concise but contractual.
```

## GitHub Issue Mapping

The roadmap items above are intended to map one-to-one to GitHub issues:

1. Security: implement RBAC strategy on API Platform
2. Remove legacy API surface (Plot and CropActivity)
3. Decouple Docker bootstrap from seed catalog synchronization
4. Separate external seed catalog from internal Genetic repository
5. Optimize frontend dashboard refresh strategy
6. Implement full testing pyramid
7. Harden authentication and onboarding flow
8. Rewrite project documentation
