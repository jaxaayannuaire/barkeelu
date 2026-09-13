# Barkeelu — CHANGELOG

Toutes les évolutions importantes du projet sont documentées ici.

> Une fonctionnalité n'est présentée comme validée que lorsque son implémentation et ses tests correspondants ont été vérifiés.

## 2026-09-13 — Identity, Organizations et RBAC plateforme

### Ajouté

- Spatie Laravel Permission 8.3.0 avec guard unique `web` et Teams désactivé ;
- tables officielles Spatie, tables `organizations` et `organization_members` ;
- rôles et permissions globaux de plateforme via un seeder idempotent ;
- création atomique d'organisation avec UUID public, slug stable et OWNER initial ;
- Policies Laravel et API Sanctum pour lister, créer, consulter et modifier les organisations ;
- tests PostgreSQL du RBAC global, des Policies, des contraintes et de l'isolation.

### Limites

- les memberships Barkeelu ne sont pas des Teams Spatie ;
- aucun KYC, Campaign, Payment, Ledger, Payout ou autre domaine financier n'est implémenté ;
- aucune invitation organisationnelle complète n'est implémentée.

## 2026-09-13 — Infrastructure Redis, queue et Reverb

### Ajouté

- configuration locale Redis via PhpRedis pour le cache et les queues Laravel ;
- sonde Redis réelle avec clé dédiée, sans vidage de base Redis ;
- job de diagnostic consommé par un worker Redis one-shot ;
- Laravel Reverb et broadcasting technique avec événement de diagnostic non métier ;
- tests d'infrastructure Redis, queue Redis et configuration Reverb ;
- recette temporaire validée : événement → queue Redis → worker → Reverb.

### Limites

- PostgreSQL reste la source de vérité ; Redis et Reverb restent des composants de cache, transport et temps réel ;
- aucun domaine métier Barkeelu, transactional outbox métier ou Barkeelu Live métier n'est implémenté ;
- aucune souscription WebSocket par un client réel n'est validée dans cette recette.

## 2026-09-12 — PostgreSQL et API V1 minimale

### Ajouté

- configuration PostgreSQL locale et migrations Laravel natives ;
- migration Laravel Sanctum pour les jetons d'accès personnels ;
- API versionnée `/api/v1` avec health check, émission, consultation et révocation du jeton courant ;
- tests Feature exécutés sur la base PostgreSQL dédiée.

### Limites

- Redis, Reverb, RBAC et les domaines métier Barkeelu ne sont pas configurés dans cette recette.

## 2026-09-12 — Bootstrap Laravel 13 minimal

### Ajouté

- bootstrap Laravel 13 dans `apps/api` selon l'architecture monorepo retenue ;
- exécution réussie des tests du squelette Laravel ;
- aucune migration métier Barkeelu créée ou exécutée.

### Limites

- PostgreSQL, Sanctum et Reverb ne sont pas configurés ;
- aucune fonctionnalité métier ou financière n'est implémentée.

## 2026-09-12 — Conception pré-migrations

### Documentation

- consolidation de l'ERD V1.2 ;
- préparation des ADR critiques ;
- définition de la gouvernance `AGENTS.md` ;
- clarification Campaign Owner / Beneficiary / Representative ;
- définition conceptuelle du ledger double entrée ;
- séparation Payment / Refund / Payout ;
- définition de la persistance webhook ;
- adoption du transactional outbox ;
- confirmation de Barkeelu Live en architecture data-only.

### Limites

- aucun code Laravel validé ;
- aucune migration métier validée ;
- aucun test applicatif exécuté ;
- aucun flux Wave production validé ;
- aucune collecte réelle autorisée.
