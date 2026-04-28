# Audit formulaires frontend — 2026-03-20

Périmètre inspecté: `frontend/src/`.

## Résumé par ressource

1. **Créer une salle (POST /api/rooms)**: **manquant complètement** côté UI.
2. **Créer un capteur (POST /api/sensors)**: **manquant complètement** côté UI.
3. **Créer une ferme (POST /api/farms)**: **manquant complètement** côté UI.
4. **Modifier les seuils d'alerte d'un capteur (PATCH /api/sensors/{id})**: **manquant complètement** côté UI.

## Détails constatés

### 1) Créer une salle (Room)
- Le service API expose bien `roomsApi.create()` (`POST /rooms`).
- Aucune vue/composant de formulaire "nouvelle salle" trouvée.
- Aucun bouton "Créer une salle" ou équivalent trouvé.

### 2) Créer un capteur (Sensor)
- Le service API expose bien `sensorsApi.create()` (`POST /sensors`).
- Le routeur contient bien une page `/sensors`, mais elle délègue uniquement à `RoomDashboard`.
- Le dashboard affiche les capteurs et états live, sans formulaire de création.
- Aucun bouton de création capteur détecté.

### 3) Créer une ferme (Farm)
- Aucun `farmsApi` ni appel `/farms` dans `frontend/src/`.
- Le type `Farm` existe uniquement dans les types.
- Aucune vue/composant ni bouton de création ferme.

### 4) Modifier les seuils d'alerte d'un capteur (PATCH /api/sensors/{id})
- Le service API expose bien `sensorsApi.update()` (`PATCH /sensors/{id}`).
- Aucun formulaire d'édition des seuils `sensor.thresholds` trouvé.
- Les seuils sont affichés en lecture seule dans `SensorCard`.
- Aucun bouton "modifier" des seuils détecté.

## Note
- Seul le flux de création explicitement présent dans l'UI est la création de **plant** (dialog "Nouveau plant"), ce qui confirme l'absence des 4 formulaires audités.
