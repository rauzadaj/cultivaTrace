# Codex et MCP CultivaTrace

Le serveur MCP du projet est dans `tools/mcp`. Il reprend la version TypeScript
validée avec cinq outils, puis ajoute l'inspection des workflows Symfony,
PHPUnit et PHPStan. Le serveur n'expose aucune commande shell arbitraire.

## Préparation locale

Depuis la racine du dépôt :

```bash
cd tools/mcp
npm ci
npm run build
cd ../..
codex mcp list
```

Les outils Symfony lancent `php bin/console` depuis la racine. Ils utilisent
`APP_ENV=test` et une URL SQLite locale seulement si ces variables ne sont pas
déjà définies. Ils inspectent la configuration et le mapping sans accéder à
une base par ces commandes. Installer les dépendances Composer pour les utiliser.
Le PHP CLI du poste doit fournir les extensions requises par `composer.lock`,
notamment `sodium` ; ne pas ignorer ces exigences pour un déploiement.

La configuration projet est `.codex/config.toml`. Les quatre agents sont dans
`.codex/agents/`. Codex charge ces fichiers à l'ouverture d'une nouvelle
session dans un projet approuvé.

## Outils

| Outil | Usage |
| --- | --- |
| `cultivatrace_project_info` | Contexte du projet |
| `cultivatrace_audit_questions` | Questions d'architecture consignées |
| `cultivatrace_symfony_routes` | Routes Symfony runtime |
| `cultivatrace_doctrine_mapping` | Entités Doctrine mappées |
| `cultivatrace_doctrine_entity` | Métadonnées d'une entité précise |
| `cultivatrace_symfony_workflows` | Places et transitions des machines d'état runtime |
| `cultivatrace_phpunit` | Tests ciblés ou suite complète, après approbation |
| `cultivatrace_phpstan` | Analyse statique avec la configuration projet |

L'outil Workflow interroge `debug:config framework workflows --format=json` :
la commande `debug:workflow` mentionnée dans l'échange initial n'existe pas
dans cette installation Symfony 8.1.

## Agents et ordre de travail

1. `cultivatrace_ddd_architect` établit les frontières et invariants.
2. `cultivatrace_symfony_developer` implémente le périmètre validé.
3. `cultivatrace_qa` et `cultivatrace_security` vérifient indépendamment les tests et la sécurité.
4. L'agent principal résout les constats bloquants et synthétise les preuves.

La limite de trois sous-agents simultanés permet à l'agent principal de rester
actif. Les règles de `AGENTS.md` restent prioritaires, notamment la coexistence
Entity/Domain et les modifications qui exigent une validation humaine.
