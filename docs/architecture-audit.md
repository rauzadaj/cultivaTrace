git checkout -b architecture/stabilization

mkdir -p docs

cat << 'EOF' > docs/architecture-audit.md
# CultivaTrace — Architecture Audit

## Contexte

Cet audit identifie plusieurs faiblesses structurelles dans l'architecture actuelle du projet CultivaTrace.  
Les points sont classés par priorité afin de sécuriser le système et améliorer sa scalabilité.

---

# Priorité P1

## RBAC insuffisant sur l’API

Tout utilisateur authentifié peut actuellement modifier des entités critiques.

Problèmes identifiés :

- RegisterUserController crée directement des comptes applicatifs
- security.yaml protège uniquement /api
- Crop, Genetic et OperationalService exposent du CRUD sans règles de sécurité

Impact :

- suppression de données critiques possible
- altération du cœur métier

Action :

Implémenter une stratégie RBAC complète avec voters Symfony et règles security par opération.

---

## Surface API legacy

Les entités suivantes exposent encore des endpoints API :

- Plot
- CropActivity

Elles ne font plus partie de l'architecture Domain/Application/Infrastructure.

Impact :

- endpoints morts
- contrat API ambigu

Action :

supprimer ou migrer ces entités.

---

## Bootstrap couplé à une dépendance externe

Le démarrage Docker dépend de :

app:sync-seed-catalog

qui appelle un catalogue externe.

Impact :

- démarrage lent
- indisponibilité possible

Action :

découpler cette synchronisation.

---

# Priorité P2

## Mélange catalogue externe / données internes

Les données Humboldt sont injectées directement dans l’entité Genetic.

Impact :

- confusion métier
- risque d’édition des données externes

Action :

introduire une entité dédiée au catalogue externe.

---

## Stratégie frontend non scalable

Le store recharge toutes les données toutes les 15 secondes.

Impact :

- explosion réseau
- mauvaise scalabilité

Action :

implémenter pagination et endpoints agrégés.

---

## Couverture de tests insuffisante

Absence de tests couvrant :

- navigation frontend
- auth
- CRUD critiques

Action :

mettre en place une pyramide de tests.

---

## Flux d’auth encore en mode démo

L'écran de login expose :

- credentials démo
- champ API URL

Action :

durcir le flux d'authentification.

---

# Priorité P3

## Documentation

La documentation ne correspond plus exactement au code.

Action :

refaire une documentation contractuelle.
EOF


git add docs/architecture-audit.md
git commit -m "docs: add architecture audit and stabilization roadmap"
git push origin architecture/stabilization


gh issue create \
--title "Security: Implement RBAC strategy on API Platform" \
--body "Implement RBAC roles (admin, operator, viewer) and add security rules to Crop, Genetic, OperationalService and JournalEntry. Add voters, authorization tests and permission matrix documentation." \
--label security,architecture

gh issue create \
--title "Remove legacy API surface (Plot & CropActivity)" \
--body "Audit Plot and CropActivity ApiResources and remove or migrate them. Clean dead endpoints and update migrations." \
--label architecture,tech-debt

gh issue create \
--title "Decouple Docker bootstrap from seed catalog synchronization" \
--body "Remove catalog sync from entrypoint. Keep migrations/seed only and move catalog sync to async job or manual command." \
--label architecture,performance

gh issue create \
--title "Separate external seed catalog from internal Genetic repository" \
--body "Introduce ExternalSeedCatalog entity and prevent editing external supplier data." \
--label architecture,backend

gh issue create \
--title "Optimize frontend dashboard refresh strategy" \
--body "Replace full refresh every 15 seconds with section loading, pagination and aggregated endpoints." \
--label frontend,performance

gh issue create \
--title "Implement full testing pyramid" \
--body "Add backend integration tests, frontend tests and Playwright e2e covering login, dashboard and CRUD flows." \
--label tests

gh issue create \
--title "Harden authentication and onboarding flow" \
--body "Remove demo credentials, remove API base URL input, add rate limiting and stronger validation." \
--label security,frontend

gh issue create \
--title "Rewrite project documentation" \
--body "Align documentation with current project state. Update backend/frontend README and architecture docs." \
--label documentation