# Barkeelu — CHANGELOG

Toutes les évolutions importantes du projet sont documentées ici.

> Une fonctionnalité n'est présentée comme validée que lorsque son implémentation et ses tests correspondants ont été vérifiés.

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
