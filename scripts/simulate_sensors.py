#!/usr/bin/env python3
"""
scripts/simulate_sensors.py

Simule des capteurs IoT en envoyant des lectures vers l'API CannaSaaS.
Utilisé en développement à la place de vrais capteurs physiques.

Usage :
  python3 scripts/simulate_sensors.py

Prérequis :
  pip install requests

Configuration :
  Modifier les variables API_URL et EMAIL/PASSWORD ci-dessous,
  ou les passer en variables d'environnement.
"""

import os
import time
import math
import random
import requests
from datetime import datetime

# ── Configuration ────────────────────────────────────────────────────────────

API_URL  = os.getenv('API_URL',  'http://localhost:8000')
EMAIL    = os.getenv('API_EMAIL', 'demo@cultivatrace.local')
PASSWORD = os.getenv('API_PASSWORD', 'demo123')

# Intervalle entre chaque envoi de lecture (secondes)
SEND_INTERVAL = 30

# ── Auth ─────────────────────────────────────────────────────────────────────

def get_token():
    resp = requests.post(
        f'{API_URL}/api/auth/login',
        json={'email': EMAIL, 'password': PASSWORD},
        headers={'Content-Type': 'application/json'},
        timeout=10,
    )
    resp.raise_for_status()
    token = resp.json()['token']
    print(f'[AUTH] Token obtenu pour {EMAIL}')
    return token

# ── Récupérer les capteurs ────────────────────────────────────────────────────

def get_sensors(token):
    resp = requests.get(
        f'{API_URL}/api/sensors',
        headers={
            'Authorization': f'Bearer {token}',
            'Accept': 'application/ld+json',
        },
        timeout=10,
    )
    resp.raise_for_status()
    sensors = resp.json().get('hydra:member', resp.json().get('member', []))
    print(f'[SENSORS] {len(sensors)} capteurs trouvés')
    return sensors

# ── Générer une valeur simulée réaliste ──────────────────────────────────────

def generate_value(sensor_type: str, tick: int) -> float:
    hour = datetime.now().hour
    # Variation sinusoïdale pour simuler cycles jour/nuit
    cycle = math.sin(hour / 12 * math.pi)

    base, variance = {
        'temperature': (23.0, 2.5),
        'humidity':    (55.0, 8.0),
        'co2':         (800.0, 150.0),
        'ph':          (6.2,  0.3),
        'ec':          (1.8,  0.2),
    }.get(sensor_type, (50.0, 5.0))

    noise = random.uniform(-variance * 0.4, variance * 0.4)
    value = base + cycle * variance * 0.5 + noise

    # Clamp
    clamp = {
        'temperature': (15.0, 35.0),
        'humidity':    (20.0, 90.0),
        'co2':         (300.0, 2000.0),
        'ph':          (4.0,  8.0),
        'ec':          (0.5,  3.5),
    }.get(sensor_type, (0.0, 100.0))

    return round(max(clamp[0], min(clamp[1], value)), 2)

# ── Envoyer une lecture ───────────────────────────────────────────────────────

def send_reading(sensor_id: str, value: float, token: str) -> bool:
    try:
        resp = requests.post(
            f'{API_URL}/api/sensors/{sensor_id}/reading',
            json={'value': value},
            headers={
                'Authorization': f'Bearer {token}',
                'Content-Type': 'application/ld+json',
            },
            timeout=10,
        )
        if resp.status_code == 201:
            data = resp.json()
            alerted = data.get('alerted', False)
            vpd     = data.get('vpd')
            if vpd:
                print(f'  → VPD calculé : {vpd["vpd"]} kPa [{vpd["status"]}]')
            return True
        else:
            print(f'  ✗ Erreur {resp.status_code}: {resp.text[:100]}')
            return False
    except requests.RequestException as e:
        print(f'  ✗ Connexion échouée : {e}')
        return False

# ── Boucle principale ─────────────────────────────────────────────────────────

def main():
    print(f'🌿 CannaSaaS — Simulateur de capteurs IoT')
    print(f'   API : {API_URL}')
    print(f'   Intervalle : {SEND_INTERVAL}s\n')

    token   = get_token()
    sensors = get_sensors(token)

    if not sensors:
        print('[ERREUR] Aucun capteur trouvé. Lance SensorFixtures d\'abord.')
        print('  docker compose exec app php bin/console doctrine:fixtures:load --append')
        return

    tick = 0
    while True:
        print(f'\n[TICK {tick}] {datetime.now().strftime("%H:%M:%S")}')

        for sensor in sensors:
            sensor_id   = sensor['id']
            sensor_type = sensor['type']
            value       = generate_value(sensor_type, tick)

            print(f'  Capteur {sensor_type:12s} {sensor_id[:8]}… → {value}')
            send_reading(sensor_id, value, token)

        tick += 1
        print(f'  Prochain envoi dans {SEND_INTERVAL}s...')
        time.sleep(SEND_INTERVAL)

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print('\n\nSimulation arrêtée.')
