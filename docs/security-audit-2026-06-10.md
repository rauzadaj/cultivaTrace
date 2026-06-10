# Audit de sécurité — CultivaTrace

**Date :** 10 juin 2026
**Périmètre :** backend Symfony 8 / API Platform 4 (PHP 8.4), frontend Vue/Quasar, configuration Docker/Railway, CI.
**Méthode :** revue de code statique complète sur 4 axes — authentification & sessions, autorisation & isolation multi-tenant, injections & gestion de fichiers, secrets & infrastructure. Chaque constat majeur a été vérifié manuellement dans le code.

---

## Synthèse

Le socle de sécurité est **solide** : isolation multi-tenant au niveau ORM, tokens correctement hachés et tournés, SQL systématiquement paramétré, uploads validés par contenu réel, en-têtes de sécurité stricts, webhook Stripe signé. Aucune vulnérabilité critique exploitable à distance n'a été identifiée.

Trois constats de **sévérité élevée** méritent une correction rapide : un bypass du contrôle de révocation pour les JWT émis à l'inscription, une route de logout dupliquée masquant un contrôleur cassé, et une énumération de comptes via le flux d'invitation.

| Sévérité | Nombre |
|---|---|
| Critique | 0 |
| Élevée | 3 |
| Moyenne | 9 |
| Faible | 7 |

---

## Sévérité élevée

### H1 — Les JWT émis à l'inscription échappent au contrôle de révocation

- **Fichiers :** `src/Infrastructure/Http/Controller/RegisterOrganizationController.php:99-104`, `src/Security/JwtDecodedSubscriber.php:35-37`
- Le JWT créé lors de l'inscription d'une organisation contient `tenantId`, `roles`, `sid`, `iat` mais **pas `userId`**, contrairement à celui émis au login (`AuthenticationSuccessHandler.php:48-54`).
- `JwtDecodedSubscriber` est **fail-open** : si `sid`, `userId` ou `tenantId` manquent dans le payload, il `return` sans appeler `markAsInvalid()`. Les tokens d'inscription contournent donc la vérification « refresh token actif / utilisateur actif / organisation non suspendue » pendant leurs 15 minutes de validité.
- **Correctifs :**
  1. Ajouter `'userId' => $user->getId()` au payload dans `RegisterOrganizationController`.
  2. Inverser la logique du subscriber en fail-closed : si les claims attendus manquent, `markAsInvalid()` (tous les tokens légitimes les contiennent désormais).

### H2 — Route `/api/auth/logout` dupliquée masquant un contrôleur cassé

- **Fichiers :** `src/Controller/Auth/LogoutController.php:35`, `src/Infrastructure/Http/Controller/LogoutController.php:21`, `config/routes.yaml`
- Deux contrôleurs déclarent la même route et le même nom `api_auth_logout`. L'import `infrastructure_controllers` étant chargé après `controllers`, la version Infrastructure (qui révoque toutes les sessions — comportement sain) écrase l'autre.
- Le contrôleur masqué est cassé : il passe une **chaîne** à `RefreshTokenService::revoke()` qui exige une entité `RefreshToken` (`strict_types=1` → `TypeError`, donc 500 et token non révoqué). Si l'ordre d'import des routes change un jour, le logout casse silencieusement.
- **Correctif :** supprimer `src/Controller/Auth/LogoutController.php` (code mort dangereux).

### H3 — Énumération de comptes via l'acceptation d'invitation

- **Fichiers :** `src/Service/Auth/OrganizationInvitationService.php:78-85`, `src/Infrastructure/Http/Controller/AcceptOrganizationInvitationController.php:56-59`
- Le service lève des exceptions distinctes (« Invitation is invalid or expired. » vs « An account already exists for this email address. ») et le contrôleur renvoie le message brut au client : un attaquant peut déterminer quelles adresses e-mail possèdent un compte.
- **Correctif :** renvoyer un message générique identique pour tous les cas d'échec côté client ; journaliser le détail côté serveur.

---

## Sévérité moyenne

### M1 — Injection de formules CSV dans les exports
`src/Service/ReportingExportService.php:174-194`, `src/Compliance/CTLS/Export/CtlsCsvExporter.php:19-37` — les cellules (notes, rfidTag, eventType…) ne sont pas neutralisées si elles commencent par `=`, `+`, `-`, `@`, `\t`. Préfixer ces valeurs d'une apostrophe avant `fputcsv()`.

### M2 — `LicenseDocument` exposé sans groupes de sérialisation
`src/Entity/LicenseDocument.php` — l'`#[ApiResource]` ne définit pas de `normalizationContext` ; tous les champs sont sérialisés, y compris `filePath` (détails d'infrastructure) et `rejectionReason` (notes internes). Ajouter des `#[Groups]` et un contexte de normalisation.

### M3 — Pas de rate limiting sur l'upload KYB
`src/Controller/KybController.php:38-96` — taille (10 Mo) et MIME validés, mais aucune limite de fréquence : épuisement disque et consommation des quotas d'API de vérification (METRC / Santé Canada) possibles. Ajouter un limiteur dédié (ex. 5 uploads/heure).

### M4 — Comparaisons d'UUID non strictes dans les state processors
`src/State/FarmStateProcessor.php:62,87`, `SensorStateProcessor.php:54`, `StrainStateProcessor.php:57`, `InputRecordStateProcessor.php:53,58` — usage de `!=` sur des objets Uuid. Fonctionnel aujourd'hui, mais fragile ; utiliser `->equals()` ou comparer les chaînes avec `!==`.

### M5 — Filtre d'archives absent sur les opérations item
`src/Doctrine/Extension/ArchiveFilterExtension.php:23-46` — implémente uniquement `QueryCollectionExtensionInterface` : une ferme archivée reste lisible via `GET /api/farms/{id}` (dans son tenant). Implémenter aussi `QueryItemExtensionInterface`.

### M6 — Secrets par défaut dans la configuration
`config/packages/mercure.yaml:4` (`change-me-in-production`), `docker-compose.yml` (`JWT_PASSPHRASE:-changeit`, `MERCURE_JWT_SECRET:-change-me-in-local-dev`) — si la variable d'environnement manque, un secret prévisible est utilisé. `docker-entrypoint.sh` valide la présence des variables en prod (bonne mitigation), mais il faut aussi rejeter les valeurs égales aux placeholders, et réserver les fallbacks à `when@dev`.

### M7 — JWT d'accès stocké en `localStorage`
`frontend/src/services/authSession.ts` — toute XSS permet l'exfiltration du token. Le CSP strict et l'absence de `v-html` réduisent le risque ; à terme, envisager des cookies `HttpOnly` + `SameSite` pour le refresh token. À documenter comme risque accepté sinon.

### M8 — Topics Mercure scopés au tenant, pas au rôle
`src/Service/MercureService.php:60-80`, `src/Controller/MercureTokenController.php:37-39` — l'abonnement couvre `/tenants/{id}/*` : un `ROLE_VIEWER` reçoit les évènements temps réel de tout le tenant, sans granularité salle/plante. Contenu dans le tenant, mais à affiner si des restrictions par rôle sont attendues.

### M9 — CSP nginx avec `style-src 'unsafe-inline'`
`docker/nginx/default.conf:11`, `docker/nginx/railway.conf.template:16` — affaiblit la CSP côté frontend. Passer à des nonces ou des feuilles externes. Vérifier aussi `trusted_proxies` (`config/packages/framework.yaml:6`) en déploiement derrière proxy.

---

## Sévérité faible

- **F1** — Énumération par timing sur `GET /api/auth/verify-email` (`VerifyEmailController.php:34-38`) : messages identiques (bien), mais la requête e-mail précède la vérification du token — écart de timing mesurable.
- **F2** — Rate limiting d'inscription keyé sur l'IP uniquement (`RegisterUserController.php:39`) : contournable via proxies, pénalise les NAT partagés.
- **F3** — Bornes de valeurs capteurs fail-open pour les types inconnus (`SensorReadingController.php:122-138`).
- **F4** — `PlantVoter` ne vérifie pas le tenant (couvert par `TenantAwareVoter` chaîné dans toutes les opérations, mais défense en profondeur souhaitable).
- **F5** — `DestructionController` sans assertion explicite d'appartenance avant l'appel au workflow (le TenantFilter couvre le chargement de l'entité).
- **F6** — Pas de scrubbing PII explicite dans `SentryBeforeSend` (e-mails, numéros de licence).
- **F7** — Conteneur démarré root avant PHP-FPM (`Dockerfile`) ; pas de filesystem read-only.

---

## Points forts confirmés

- **Isolation multi-tenant au niveau ORM** : `TenantFilter` Doctrine injecté sur toutes les requêtes via `TenantListener`, doublé de voters (`TenantAwareVoter`) et d'expressions `security` explicites sur chaque opération API Platform. Aucune escalade de privilèges trouvée ; l'invitation ne permet pas d'attribuer `SUPER_ADMIN`.
- **Tokens** : `random_bytes(32)` (256 bits), hachage SHA-256 + `hash_equals`, rotation des refresh tokens avec invalidation du cache, révocation de toutes les sessions au changement de mot de passe, TTL JWT 15 min, clés RSA 4096 protégées par passphrase et jamais commitées.
- **Anti-brute force** : verrouillage 5 échecs / 15 min par e-mail + rate limiter sur `/api/auth/login` ; messages d'erreur de login génériques.
- **Injections** : 100 % du SQL paramétré (aucune concaténation trouvée), aucun `unserialize()`/`eval`, pas de `|raw` Twig sur données utilisateur, Gotenberg n'accepte jamais d'URL utilisateur (pas de SSRF).
- **Uploads KYB** : MIME vérifié par `finfo` (contenu réel), 10 Mo max, anti path-traversal par `realpath()` + détection de symlinks dans `ArtifactStorage`.
- **Stripe** : signature de webhook vérifiée via `Webhook::constructEvent`.
- **En-têtes de sécurité** (côté Symfony) : CSP `default-src 'none'`, `X-Frame-Options: DENY`, `nosniff`, HSTS 1 an, `Permissions-Policy` restrictive — avec tests.
- **Divers** : pagination plafonnée à 100, erreurs 500 génériques en prod, validation des variables d'environnement requises au démarrage en prod, dépendances de sécurité à jour (lexik/jwt 3.2, stripe-php 19.4).

---

## Plan d'action recommandé

| Priorité | Action | Réf. |
|---|---|---|
| 1 | Ajouter `userId` au JWT d'inscription + rendre `JwtDecodedSubscriber` fail-closed | H1 |
| 1 | Supprimer le `LogoutController` mort de `src/Controller/Auth/` | H2 |
| 1 | Message générique sur l'acceptation d'invitation | H3 |
| 2 | Neutraliser les formules dans les exports CSV | M1 |
| 2 | `#[Groups]` sur `LicenseDocument` | M2 |
| 2 | Rate limiting sur l'upload KYB | M3 |
| 2 | Rejeter les secrets placeholder au démarrage prod | M6 |
| 3 | Comparaisons UUID strictes, filtre d'archives sur les items, durcissements divers | M4, M5, M7-M9, F1-F7 |

Avant ouverture publique : vérifier `KYB_ENABLE_DEV_SIMULATION=0` en production (défaut sain confirmé dans `.env.example`), et envisager un test d'intrusion externe ciblant l'isolation tenant et les flux d'authentification.
