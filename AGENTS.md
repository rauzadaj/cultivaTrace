# CannaSaaS — Instructions Agents (Jalon 3)

**Lis ce fichier en entier avant de coder quoi que ce soit.**

## Stack (définitif)

- Backend : **Symfony 6.4 LTS + API Platform 3**
- Frontend : **Vue 3 + Quasar Framework**
- DB : PostgreSQL 16
- Séries temporelles IoT : table `sensor_reading` (TimescaleDB ou PG classique)
- Auth : LexikJWTAuthenticationBundle
- PDF : GotenbergBundle (sensiolabs/gotenberg-bundle)
- Temps réel : **Mercure Hub SSE** (symfony/mercure-bundle)
- Queue : Symfony Messenger
- Monorepo : `src/` (backend) + `frontend/src/` (frontend)

## Jalons complétés

- **Jalon 1** ✅ — Multi-tenant, entités de base, SYNC-01 validé
- **Jalon 2** ✅ — Plant CRUD, audit trail, harvest, destruction, PDF, CTS
- **Jalon 3** 🔄 — IoT capteurs, Mercure SSE, VPD, alertes

## Fichiers livrés dans ce jalon

Backend :
- `src/Entity/Sensor.php` — capteur IoT (temperature, humidity, co2, ph, ec)
- `src/Repository/SensorReadingRepository.php` — DBAL natif, pas ORM
- `src/Service/VpdService.php` — calcul VPD côté serveur
- `src/Service/AlertService.php` — alertes seuils avec déduplication Redis
- `src/Controller/SensorReadingController.php` — POST /api/sensors/{id}/reading
- `src/Controller/SensorHistoryController.php` — GET /api/sensors/{id}/readings
- `src/DataFixtures/SensorFixtures.php` — 90j données simulées
- `config/packages/mercure.yaml`

Frontend :
- `frontend/src/composables/useMercure.ts` — SSE temps réel
- `frontend/src/stores/sensors.ts` — store Pinia capteurs

Scripts :
- `scripts/simulate_sensors.py` — simulateur capteurs (remplace vrais capteurs)

## Règle #1 — Multi-tenant (SYNC-01 validé ✅)

**TenantListener priority = -10**
Toute entité métier a un champ `tenantId`. TenantFilter actif sur toutes les requêtes.

## Règle #2 — Audit Trail APPEND-ONLY

Jamais de UPDATE ni DELETE sur PlantEvent.
Toujours utiliser PlantEventRepository::appendEvent().

## Règle #3 — sensor_reading est une table DBAL, pas une entité ORM

Ne jamais créer d'entité Doctrine pour sensor_reading.
Toujours utiliser SensorReadingRepository (DBAL natif).

## Règle #4 — Mercure SSE

Topic format : `cannas/{tenantId}/sensors` et `cannas/{tenantId}/rooms/{roomId}`
Ne jamais publier des données d'un autre tenant sur le même topic.
Utiliser HubInterface injecté dans les controllers — pas de client HTTP direct.

## Règle #5 — VPD

VpdService::compute() prend température (°C) et humidité (%).
Le calcul est fait côté serveur dans SensorReadingController.
Ne jamais calculer le VPD côté frontend.

## Règle #6 — Alertes

AlertService utilise le cache Symfony (Redis en prod) pour la déduplication.
1 alerte max par capteur par cooldown (défaut 60 min).
Ne jamais envoyer d'email directement depuis un controller — passer par AlertService.

## Règle #7 — Types TypeScript

Un seul fichier : `frontend/src/types/api.ts`
SensorUpdate est défini dans useMercure.ts — ne pas le dupliquer.

## Règle #8 — Ce que tu ne fais JAMAIS sans validation humaine

- Modifier TenantFilter, TenantListener, Voters RBAC
- Changer la logique hash-chaining
- Publier sur un topic Mercure sans le prefixe cannas/{tenantId}/
- Modifier le format des rapports réglementaires

## Règle #9 — Commits

Format : `feat(TICKET-ID): description courte`

## Points de synchronisation

- **SYNC-01** ✅ Validé
- **SYNC-02** ✅ Validé
- **SYNC-03** ✅ Premier PDF généré (validation réglementaire externe à faire en beta)
- **SYNC-04** 🔄 Après IoT : dashboard temps réel < 5s en staging

## Services Docker requis (Jalon 3)

```yaml
mercure:
  image: dunglas/mercure
  environment:
    SERVER_NAME: ':80'
    MERCURE_PUBLISHER_JWT_KEY: '${MERCURE_JWT_SECRET}'
    MERCURE_SUBSCRIBER_JWT_KEY: '${MERCURE_JWT_SECRET}'
  command: /usr/bin/caddy run --config /etc/caddy/Caddyfile.dev
  ports:
    - "80:80"

mosquitto:
  image: eclipse-mosquitto:2
  ports:
    - "1883:1883"
  volumes:
    - ./docker/mosquitto/mosquitto.conf:/mosquitto/config/mosquitto.conf
```

Variables `.env` à ajouter :
```
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure
MERCURE_JWT_SECRET=cannas_mercure_secret_change_in_prod
VITE_MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure
```
