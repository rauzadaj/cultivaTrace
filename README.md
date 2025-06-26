# CultivaTrace MVP

## Description

Projet CultivaTrace - traçabilité agricole simple et conforme.

## Stack technique

- Symfony 6 + API Platform
- PostgreSQL 13
- JWT Auth avec LexikJWT
- Frontend Vue.js 3 (simple)
- Docker + Docker Compose

## Installation

1. Lancer les conteneurs Docker :
```
docker-compose up --build -d
```

2. Installer les dépendances Symfony :
```
docker exec -it cultivatrace_app composer install
```

3. Configurer la base et lancer les migrations :
```
docker exec -it cultivatrace_app php bin/console doctrine:migrations:migrate
```

4. Accéder à l'API sur http://localhost:8000/api

## Roadmap

- Authentification JWT
- Entités Parcelle et ActivitéCulturale
- CRUD API Platform
- Frontend Vue.js dashboard
- Génération PDF registre

## Contact

Pour toute question, contacte Meeko.
