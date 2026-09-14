# Intégration des lots P0 sur main

La première branche `codex/p0-security-symfony81-plantevent` conserve les deux
commits qualifiés sur `58081ea`. GitHub a refusé la PR de cette branche : son
historique et celui du main distant n'ont aucun ancêtre commun.

La branche de PR **`codex/p0-security-symfony81-main`** part donc de **`869e592`**.
Les deux lots y sont portés avec résolution explicite des conflits. Aucun merge
artificiel d'historiques, retour en arrière de main ou écrasement de ses fichiers
métier n'est utilisé.

## Évolutions récentes conservées

- `HashChainService::computeHash()` conserve exactement le **HMAC-SHA256** de main
  et son enveloppe (notes, photos et IP comprises). La pseudonymisation HMAC de
  l'IP reste inchangée. Seule la comparaison explicite du hashPrevious est ajoutée
  au vérificateur, avec `hash_equals` conservé.
- Le verrou Plant remplace le verrou conditionnel du dernier événement : celui-ci
  ne protégeait pas une chaîne vide. L'append relit le prédécesseur sous verrou
  parent et transaction READ COMMITTED ; signatures d'injection actuelles gardées.
- Le listener `App\EventSubscriber\AppendOnlyPlantEventSubscriber` de main est
  réutilisé ; le listener équivalent du premier lot n'est pas dupliqué.
- L'export CTS reste réservé aux administrateurs d'organisation. Les tests ajoutés
  sont adaptés à cette restriction et au constructeur actuel du processor Plant.
- Les nouveaux plans, limites, contrôles KYB, modèles, exports PDF et corrections
  de sécurité de main sont conservés. TenantFilter, TenantListener, voters et
  migrations n'ont aucun diff avec la base de cette PR.
- PHPStan **2.2.1**, ses extensions Symfony/Doctrine, le niveau **6** et la baseline
  déjà présents sur main sont conservés. La baseline n'est ni augmentée ni modifiée.
  `composer phpstan:p0` ajoute une analyse distincte niveau 5, sans baseline, du
  code PlantEvent et des nouveaux tests/runner. La limite mémoire de l'analyse
  globale est explicitée à 512M après un échec local à 128M.
- Le type-check frontend de main reste en place. Les caches GitHub Actions v4 et
  le contrôle PHPStan global de la CI sont conservés.

## Qualification effectuée sur la base portée

| Contrôle | Résultat |
| --- | --- |
| Composer validate / audit | Réussis ; aucun avis de sécurité |
| Symfony | FrameworkBundle 8.1.6 ; plateforme PHP 8.4.1 |
| PHPUnit rapide | **317 tests, 845 assertions, 1 skip** PDF/Gotenberg |
| PostgreSQL 16.15 | **37 migrations, 177 requêtes SQL** ; **15 tests, 80 assertions, 2 échecs JSONB**, aucun skip |
| PHPStan global niveau 6 | Réussi avec la baseline inchangée de main |
| PHPStan P0 sans baseline | Réussi, zéro diagnostic |
| Frontend | **29 tests / 10 fichiers réussis**, build et `npm run type-check` réussis |

Les preuves nouvelles sont dans `docs/evidence/pr-main/`. Le défaut JSONB reste
présent avec le **HMAC de main**, et pas uniquement avec le SHA-256 de l'ancien
checkout. Le test d'un payload à plusieurs clés échoue après flush/clear/reload,
aussi bien sur un que sur trois événements. Les deux tests restent bloquants dans
la CI. INSERT, interdictions SQL/ORM UPDATE/DELETE, concurrence avec/sans premier
événement, rollback et refus d'isolation incompatible passent.

**P0-06 reste BLOCKED** jusqu'à décision et validation de la compatibilité JSONB.
Aucun hash, payload ou timestamp historique n'a été réécrit. Les rapports initiaux
`architecture-evolution.md`, `plant-event-integration.md` et les preuves p0-05/p0-06
sont conservés comme instantanés de `58081ea` ; leurs anciennes versions/chiffres
ne doivent pas être confondus avec cette qualification.

La PR est publiée en brouillon pour revue. Aucune fusion, aucun déploiement Railway
et aucune qualification de données de production ne sont réalisés dans ce portage.
