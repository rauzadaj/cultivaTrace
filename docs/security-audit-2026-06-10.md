# Audit de sécurité — CultivaTrace

**Date initiale :** 10 juin 2026. **Actualisation ciblée :** 13 octobre 2026, sur `main` à `7a03f298`.
**Périmètre :** backend Symfony 8 / API Platform 4, frontend Vue/Quasar et configuration Docker/Railway. Les constats ci-dessous sont issus d'une revue statique ; les comportements de déploiement et les garanties réglementaires demandent une validation distincte.
**Méthode :** revue des chemins de code cités sur quatre axes — authentification et sessions, autorisation et isolation multi-tenant, injections et gestion de fichiers, secrets et infrastructure. L'actualisation du 13 octobre vérifie les assertions et le plan d'action contre le code de `main` ; ce document ne constitue pas un test d'intrusion.

---

## Synthèse

La revue statique confirme plusieurs protections : filtre tenant au niveau ORM, hachage et rotation des refresh tokens, contrôles des uploads KYB, en-têtes de sécurité et vérification de la signature du webhook Stripe. Aucun constat de sévérité critique n'a été établi dans le périmètre examiné.

Deux constats de **sévérité élevée** méritent une correction rapide : des JWT émis à l'inscription et au renouvellement qui échappent au contrôle de révocation du subscriber, et une différence de réponse dans le flux d'acceptation d'invitation. La route de logout résout aujourd'hui le bon contrôleur ; sa définition dupliquée expose à une régression future.

| Sévérité | Nombre |
|---|---|
| Critique | 0 |
| Élevée | 2 |
| Moyenne | 8 |
| Faible | 7 |

---

## Sévérité élevée

### H1 — Les JWT d'inscription et de renouvellement échappent au contrôle de révocation

- **Fichiers :** `src/Infrastructure/Http/Controller/RegisterOrganizationController.php:99-104`, `src/Infrastructure/Http/Controller/RefreshTokenController.php:56-61`, `src/Security/JwtDecodedSubscriber.php:35-37`.
- Les JWT créés lors de l'inscription d'une organisation **et du renouvellement** contiennent `tenantId`, `roles`, `sid`, `iat` mais pas `userId`, contrairement à celui émis au login (`AuthenticationSuccessHandler.php:48-54`).
- `JwtDecodedSubscriber` est **fail-open** : si `sid`, `userId` ou `tenantId` manquent dans le payload, il `return` sans appeler `markAsInvalid()`. Ces JWT contournent donc son contrôle du refresh token actif pendant leurs 15 minutes de validité. `UserChecker` contrôle séparément le statut actif de l'utilisateur et la suspension de son organisation à l'authentification ; ne pas présenter ces deux contrôles comme totalement absents.
- **Correctifs :**
  1. Ajouter `'userId' => $user->getId()` aux deux payloads, dans `RegisterOrganizationController` et `RefreshTokenController`.
  2. Rendre le subscriber fail-closed pour les claims attendus, après avoir vérifié tous les chemins d'émission de JWT.

### H3 — Énumération de comptes via l'acceptation d'invitation

- **Fichiers :** `src/Service/Auth/OrganizationInvitationService.php:78-85`, `src/Infrastructure/Http/Controller/AcceptOrganizationInvitationController.php:56-59`
- Le service lève des exceptions distinctes (« Invitation is invalid or expired. » vs « An account already exists for this email address. ») et le contrôleur renvoie le message brut au client. Avec un jeton d'invitation valide, cette différence révèle si l'adresse invitée possède déjà un compte ; le jeton nécessaire limite la portée du constat.
- **Correctif :** renvoyer un message générique identique pour tous les cas d'échec côté client ; journaliser le détail côté serveur.

---

## Sévérité moyenne

### M1 — Injection de formules CSV dans les exports
- **Chemin restant :** `src/Compliance/CTLS/Export/CtlsCsvExporter.php` écrit les valeurs de la ligne officielle avec `fputcsv()` sans neutralisation des formules. Le champ `Licence ID` provient notamment de l'option opérateur `--licence-id` de la commande `app:export:ctls`.
- **Chemins déjà corrigés sur `main` :** `ReportingExportService` protège les champs texte du rapport d'événements avec `SpreadsheetCell::text()` ; `CTSReportController` protège les noms de variétés et de salles du rapport `GET /api/compliance/ctsreport` avec la même fonction. Ne pas compter ces deux chemins comme vulnérables sur la révision vérifiée.
- **Correctif :** valider ou neutraliser les entrées textuelles du CSV CTLS officiel avant `fputcsv()`, avec un test sur les préfixes de formule. Une apostrophe change la valeur exportée : vérifier l'acceptation de ce format par Health Canada avant de l'appliquer à un identifiant réglementaire.

### M2 — `LicenseDocument` exposé sans groupes de sérialisation
`src/Entity/LicenseDocument.php` — l'`#[ApiResource]` ne définit pas de `normalizationContext` ; tous les champs sont sérialisés, y compris `filePath` (détails d'infrastructure) et `rejectionReason` (notes internes). Ajouter des `#[Groups]` et un contexte de normalisation.

### M10 — Route `/api/auth/logout` dupliquée, contrôleur masqué cassé

- **Fichiers :** `src/Controller/Auth/LogoutController.php:35`, `src/Infrastructure/Http/Controller/LogoutController.php:21`, `config/routes.yaml`.
- Deux contrôleurs déclarent la même route et le même nom `api_auth_logout`. `debug:router api_auth_logout --show-controllers` sur `main` à `7a03f298` résout `App\Infrastructure\Http\Controller\LogoutController`, qui révoque toutes les sessions. Ce constat est un risque de régression, pas un échec du logout actuellement résolu.
- Le contrôleur masqué passe une **chaîne** à `RefreshTokenService::revoke()` qui exige une entité `RefreshToken` (`strict_types=1` → `TypeError`). Si le routage change, le logout peut échouer en HTTP 500.
- **Correctif :** supprimer le contrôleur masqué `src/Controller/Auth/LogoutController.php` et conserver un test du contrôleur résolu et de la révocation.

### M4 — Comparaisons d'UUID non strictes dans les state processors
`src/State/FarmStateProcessor.php:62,87`, `SensorStateProcessor.php:56,61`, `StrainStateProcessor.php:57`, `InputRecordStateProcessor.php:53,58` — usage de `!=` sur des objets Uuid. Fonctionnel aujourd'hui, mais fragile ; utiliser `->equals()` ou comparer les chaînes avec `!==`.

### M6 — Secrets par défaut dans la configuration
`config/packages/mercure.yaml:4` (`CONFIGURE_MERCURE_SECRET_IN_ENV`), `docker-compose.yml` (`JWT_PASSPHRASE:-changeit`, `MERCURE_JWT_SECRET:-change-me-in-local-dev`) — si la variable d'environnement manque, un secret prévisible est utilisé. Vérifier les valeurs effectivement injectées au déploiement et rejeter les placeholders en production ; réserver les fallbacks au développement local.

### M7 — JWT d'accès stocké en `localStorage`
`frontend/src/services/authSession.ts` — toute XSS permet l'exfiltration du token. Le CSP strict et l'absence de `v-html` réduisent le risque ; à terme, envisager des cookies `HttpOnly` + `SameSite` pour le refresh token. À documenter comme risque accepté sinon.

### M8 — Topics Mercure scopés au tenant, pas au rôle
`src/Service/MercureService.php:60-80`, `src/Controller/MercureTokenController.php:37-39` — l'abonnement couvre `/tenants/{id}/*` : un `ROLE_VIEWER` reçoit les évènements temps réel de tout le tenant, sans granularité salle/plante. Contenu dans le tenant, mais à affiner si des restrictions par rôle sont attendues.

### M9 — CSP nginx avec `style-src 'unsafe-inline'`
`docker/nginx/default.conf:11`, `docker/nginx/railway.conf.template:16` — affaiblit la CSP côté frontend. Passer à des nonces ou des feuilles externes. Vérifier aussi `trusted_proxies` (`config/packages/framework.yaml:6`) en déploiement derrière proxy.

---

## Constats écartés ou dépendants d'une décision produit

### M3 — Rate limiting KYB déjà présent
- **Fichiers :** `src/Infrastructure/Http/EventSubscriber/ApiRateLimitSubscriber.php:47-60,64-105`, `config/packages/rate_limiter.yaml:19-22,35-38`, `src/Controller/KybController.php:38-96`.
- `POST /api/kyb/upload` est soumis au limiteur dédié `api_kyb_write` : **30 requêtes par fenêtre fixe de 15 minutes et par IP**, quota partagé avec les validations administratives KYB. Les requêtes acceptées restent soumises au limiteur global `api_global` : **300 requêtes par minute et par IP**, en fenêtre glissante. Un dépassement renvoie HTTP 429 avant l'exécution du contrôleur ; les tests existants couvrent le rejet d'un upload lorsque le quota dédié est épuisé (`tests/Infrastructure/Http/EventSubscriber/ApiRateLimitSubscriberTest.php:20-55,113-114`).
- **Évaluation complémentaire :** avec des fichiers de 10 Mo maximum, le quota dédié autorise théoriquement jusqu'à 300 Mo téléversés par fenêtre et par IP. Évaluer ce seuil au regard du stockage disponible et des quotas des services de vérification, puis envisager un seuil plus bas ou des quotas par organisation/volume si nécessaire. Ce point n'est pas une vulnérabilité confirmée et ne figure pas dans le décompte.

### M5 — Lecture directe des archives : comportement documenté
`src/Doctrine/Extension/ArchiveFilterExtension.php` exclut les archives des collections mais documente leur accès par `GET` direct ou requête administrative. Cette lecture reste dans le tenant. Décider si une ferme archivée doit devenir inaccessible même par son identifiant ; implémenter `QueryItemExtensionInterface` seulement si cette règle est retenue. Ce point ne figure pas dans le décompte.

---

## Sévérité faible

- **F1** — Énumération par timing sur `GET /api/auth/verify-email` (`VerifyEmailController.php:34-38`) : messages identiques (bien), mais la requête e-mail précède la vérification du token — écart de timing mesurable.
- **F2** — Rate limiting d'inscription keyé sur l'IP uniquement (`RegisterUserController.php:39`) : contournable via proxies, pénalise les NAT partagés.
- **F3** — Bornes de valeurs capteurs fail-open pour les types inconnus (`SensorReadingController.php:122-138`).
- **F4** — `PlantVoter` ne vérifie pas le tenant lui-même. Vérifier chaque point d'appel et sa protection tenant avant de s'appuyer sur ce voter seul ; l'ajout d'un contrôle dans un voter RBAC exige une validation humaine.
- **F5** — `DestructionController` sans assertion explicite d'appartenance avant l'appel au workflow (le TenantFilter couvre le chargement de l'entité).
- **F6** — Pas de scrubbing PII explicite dans `SentryBeforeSend` (e-mails, numéros de licence).
- **F7** — Conteneur démarré root avant PHP-FPM (`Dockerfile`) ; pas de filesystem read-only.

---

## Points forts confirmés

- **Isolation multi-tenant au niveau ORM** : `TenantFilter` Doctrine et `TenantListener` fournissent le filtrage transversal ; les opérations examinées utilisent aussi des contrôles d'autorisation. Aucun franchissement de tenant n'a été établi dans les chemins revus. La coexistence entre `src/Entity` et `src/Domain/Cultivation/Model` n'a pas été modifiée ni évaluée comme une migration.
- **Tokens** : génération aléatoire des refresh tokens, hachage SHA-256 et rotation avec invalidation du cache dans les chemins examinés. Le contrôle de révocation des JWT porte toutefois la réserve H1.
- **Anti-brute force** : verrouillage 5 échecs / 15 min par e-mail + rate limiter sur `/api/auth/login` ; messages d'erreur de login génériques.
- **Injections** : les requêtes SQL examinées utilisent des paramètres. La revue statique ne permet pas d'affirmer que tous les chemins SQL ou toutes les entrées de Gotenberg sont sûrs ; le CSV CTLS conserve la réserve M1.
- **Uploads KYB** : MIME vérifié par `finfo` (contenu réel), 10 Mo max, anti path-traversal par `realpath()` + détection de symlinks dans `ArtifactStorage`, limiteur dédié `api_kyb_write` (30 requêtes / 15 min / IP) complété par le limiteur global (300 requêtes / min / IP).
- **Stripe** : signature de webhook vérifiée via `Webhook::constructEvent`.
- **En-têtes de sécurité** (côté Symfony) : CSP `default-src 'none'`, `X-Frame-Options: DENY`, `nosniff`, HSTS 1 an, `Permissions-Policy` restrictive — avec tests.
- **Divers** : pagination plafonnée à 100, erreurs 500 génériques en prod, validation des variables d'environnement requises au démarrage en prod, dépendances de sécurité à jour (lexik/jwt 3.2, stripe-php 19.4).

---

## Plan d'action recommandé

| Priorité | Action | Réf. |
|---|---|---|
| 1 | Ajouter `userId` aux JWT d'inscription et de renouvellement, puis rendre `JwtDecodedSubscriber` fail-closed | H1 |
| 1 | Message générique sur l'acceptation d'invitation | H3 |
| 2 | Supprimer le contrôleur de logout masqué et tester le routage et la révocation | M10 |
| 2 | Sécuriser la sortie de `CtlsCsvExporter` et valider la compatibilité du CSV officiel avec Health Canada ; maintenir les tests des deux autres exports déjà protégés | M1 |
| 2 | `#[Groups]` sur `LicenseDocument` | M2 |
| 2 | Rejeter les secrets placeholder au démarrage prod | M6 |
| 3 | Évaluer le seuil KYB existant (30 requêtes / 15 min / IP) selon le stockage et les quotas externes ; ajuster si nécessaire | M3 (durcissement à évaluer) |
| 3 | Comparaisons UUID strictes et durcissements divers ; statuer sur l'accès direct aux archives avant tout changement | M4, M5 (à décider), M7-M9, F1-F7 |

Avant ouverture publique : vérifier `KYB_ENABLE_DEV_SIMULATION=0` en production (défaut sain confirmé dans `.env.example`), et envisager un test d'intrusion externe ciblant l'isolation tenant et les flux d'authentification.
