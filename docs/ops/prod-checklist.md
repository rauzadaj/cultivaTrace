# Checklist prod — CultivaTrace beta

À cocher manuellement avant d'ouvrir l'accès aux premiers utilisateurs.

---

## 1. Variables d'environnement Railway

### Sécurité applicative
- [ ] `APP_SECRET` — générer avec `openssl rand -hex 32`, valeur unique, jamais partagée
- [ ] `JWT_PASSPHRASE` — générer avec `openssl rand -hex 32`, valeur unique
- [ ] `APP_ENV=prod`
- [ ] `DATABASE_URL` pointe vers l'endpoint PostgreSQL poolé Railway
- [ ] `DATABASE_URL_DIRECT` pointe vers l'endpoint PostgreSQL direct Railway

### Email transactionnel
- [ ] `MAILER_DSN` — configurer Postmark, Resend ou Mailgun (pas smtp localhost)
  - Postmark : `smtp://[API_TOKEN]:@smtp.postmarkapp.com:587`
  - Resend : `resend+smtp://resend:[API_KEY]@smtp.resend.com:465`
- [ ] Tester l'envoi d'un email de vérification en sandbox avant go-live
- [ ] `STRIPE_ALERT_FROM_EMAIL` — adresse vérifiée dans ton provider email (SPF/DKIM)
- [ ] `KYB_ALERT_FROM_EMAIL` — idem
- [ ] `LICENSE_ALERT_FROM_EMAIL` — idem

### Stripe
- [ ] Basculer de `sk_test_` vers `sk_live_` dans `STRIPE_SECRET_KEY`
- [ ] Créer un webhook endpoint dans le dashboard Stripe live pointant vers :
      `https://[TON_DOMAINE]/api/billing/webhook`
- [ ] Copier le `whsec_` live dans `STRIPE_WEBHOOK_SECRET`
- [ ] Vérifier que `STRIPE_PRICE_STARTER`, `STRIPE_PRICE_PRO`, `STRIPE_PRICE_BUSINESS`
      pointent vers des price IDs live (pas test)
- [ ] Faire un paiement test en mode live avec une vraie carte pour valider le webhook

### Monitoring
- [ ] Créer un projet Sentry (sentry.io — free tier suffisant pour beta)
- [ ] Copier le DSN dans `SENTRY_DSN` sur Railway
- [ ] Vérifier qu'une erreur test remonte dans Sentry (déclencher manuellement une exception)

### KYB
- [ ] `KYB_ENABLE_DEV_SIMULATION=0` en prod (sauf si beta fermée acceptée avec simulation)
      ⚠️ Voir section KYB dans AGENTS.md avant de changer ce flag
- [ ] `KYB_STORAGE_ROOT` pointe vers un volume persistant Railway
- [ ] `REPORT_EXPORT_STORAGE_ROOT` pointe vers un volume persistant Railway

### Mercure
- [ ] `MERCURE_JWT_SECRET` — valeur forte, différente du JWT_PASSPHRASE
- [ ] `MERCURE_URL` et `MERCURE_PUBLIC_URL` pointent vers l'instance Railway correcte

---

## 2. Backups base de données

- [ ] Railway PostgreSQL — vérifier que les backups automatiques sont activés
      (Dashboard Railway → ton service DB → Settings → Backups)
- [ ] Fréquence recommandée : daily minimum
- [ ] Tester une restauration sur un environnement de staging avant go-live
- [ ] Suivre le runbook [postgresql-railway-runbook.md](./postgresql-railway-runbook.md)

---

## 3. DNS et HTTPS

- [ ] Domaine custom configuré sur Railway
- [ ] Certificat TLS actif (Railway le gère automatiquement via Let's Encrypt)
- [ ] `FRONTEND_URL` mis à jour avec le domaine prod (pas localhost)
- [ ] Vérifier que HSTS est bien envoyé sur HTTPS (header `Strict-Transport-Security`)
      Le code le fait conditionnellement si `$request->isSecure()` — vérifier que Railway
      passe correctement le flag HTTPS à Symfony (X-Forwarded-Proto)

---

## 4. Vérifications applicatives post-déploiement

- [ ] `GET /api/health` répond 200
- [ ] Login → refresh token → appel API authentifié → fonctionne
- [ ] Inscription d'une organisation → email de vérification reçu
- [ ] Stripe checkout → paiement → webhook → plan mis à jour
- [ ] `php bin/console doctrine:schema:validate` sur la base prod (via Railway SSH)
- [ ] Sentry reçoit bien les events (déclencher une erreur test)

Pour les commandes Doctrine en prod, exporter d'abord `DATABASE_URL="$DATABASE_URL_DIRECT"`.

---

## 5. Accès et secrets

- [ ] Variables Railway en mode "Secret" (pas visibles dans les logs)
- [ ] Révoquer tous les tokens JWT de test avant go-live
      (`DELETE FROM refresh_token` via Railway console si nécessaire)
- [ ] Vérifier que `.env`, `.env.local` ne sont pas dans le build Docker
      (le `.dockerignore` doit les exclure — vérifier)

---

## 6. X-Forwarded-Proto (important pour HSTS)

Railway termine le TLS au niveau du proxy. Symfony doit faire confiance au header
`X-Forwarded-Proto` pour détecter HTTPS correctement, sinon HSTS ne sera jamais envoyé.

Dans `config/packages/framework.yaml`, vérifier ou ajouter :
```yaml
framework:
    trusted_proxies: '127.0.0.1,REMOTE_ADDR'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-port']
```

Si absent, ajouter et déployer.

---

## Date de validation

- Checklist complétée par : _______________
- Date : _______________
- Environnement Railway vérifié : _______________
