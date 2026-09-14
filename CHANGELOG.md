# Barkeelu — CHANGELOG

## 2026-09-14 — Refunds, payouts et reconciliation

### Ajouté

- tables PostgreSQL `refunds`, `payouts`, `reconciliation_runs` et `reconciliation_items` ;
- Refunds partiels idempotents, avec réservation atomique et origine ledger `CAMPAIGN_PAYABLE` ou `UNAPPLIED_FUNDS` ;
- Payouts avec séparation operator/approver, interdiction d'auto-approbation, snapshot de destination et réservation `CAMPAIGN_PAYABLE` vers `PAYOUT_RESERVED` ;
- exécution et libération des payouts exclusivement par postings ledger ;
- rapprochement MVP couvrant les six résultats ADR-014, résolution auditée et correction financière par reversal ;
- preuves de concurrence PostgreSQL réelle pour les Refunds et les réservations Payout.

### Limites

- Wave production, orchestration bancaire complète et comptabilité légale restent hors périmètre ;
- un état fournisseur ambigu reste `UNKNOWN` jusqu'à vérification ou reconciliation, sans retry aveugle.

## 2026-09-13 — Donations, paiements et webhooks

### Ajouté

- tables PostgreSQL provider_accounts, donations, payments et webhook_events ;
- relation Donation 1:N Payment, clés d’idempotence et contraintes PostgreSQL ;
- persistance, déduplication et traitement asynchrone des webhooks ;
- double succès fournisseur conservé vers UNAPPLIED_FUNDS ;
- test de concurrence PostgreSQL réelle sur deux processus Laravel distincts.

### Limites

- Wave production n’est pas activé ;
- Refund, Payout et Reconciliation complète restent hors périmètre.

Toutes les évolutions importantes du projet sont documentées ici.

> Une fonctionnalité n'est présentée comme validée que lorsque son implémentation et ses tests correspondants ont été vérifiés.

## 2026-09-13 — Fondation financière 06A

### Ajouté

- tables PostgreSQL `ledger_accounts`, `ledger_transactions`, `ledger_entries`, `fee_policies`, `applied_fees` et `outbox_events` ;
- validation différée PostgreSQL du double-entry au commit, protection d'immutabilité des écritures `POSTED` et des frais appliqués ;
- services de posting idempotent par `business_key` / `content_hash`, reversal et Outbox transactionnelle ;
- seeders idempotents du plan de comptes XOF et des politiques `PLATFORM_FEE` (400 bps) et `PAYOUT_PROVISION_WORKING` (100 bps, inactive) ;
- calculateur de frais en arithmétique entière avec règle d'arrondi half-up ;
- processeur Outbox one-shot avec verrouillage PostgreSQL `FOR UPDATE SKIP LOCKED` ;
- tests PostgreSQL directs de contraintes, atomicité, reversal, idempotence et concurrence sur deux processus distincts.

### Limites

- le ledger est interne et technique, sans portée de comptabilité légale ou fiscale ;
- aucun Donation, Payment, ProviderAccount, Webhook métier, Wave, Refund, Payout ni Reconciliation n'est implémenté ;
- aucune route publique de posting ledger n'est exposée.

## 2026-09-13 — Cœur Campaign

### Ajouté

- table `campaigns`, owner User XOR Organization, bénéficiaire obligatoire, projections `BIGINT` et contraintes PostgreSQL ;
- cycle de vie contrôlé, permissions owner/modération et API publique ou Sanctum ;
- visibilités PUBLIC, UNLISTED et PRIVATE ; projections non modifiables par API.

### Limites

- TARGETED reste réservé ; Program, Project, Category, Donation, Payment, Wave, Refund, Payout et Barkeelu Live métier restent différés.

## 2026-09-13 — Bénéficiaires, représentants et KYC

### Ajouté

- tables `beneficiaries`, `beneficiary_representatives`, `kyc_profiles` et `kyc_documents` ;
- contraintes XOR PostgreSQL, représentants historisés, stockage KYC privé et empreintes SHA-256 ;
- services, Policies, API Sanctum et tests PostgreSQL associés.

## 2026-09-13 — Identity, Organizations et RBAC plateforme

### Ajouté

- Spatie Laravel Permission avec guard `web`, Teams désactivé, organisations et memberships Barkeelu ;
- RBAC plateforme, Policies et API Sanctum testés sur PostgreSQL.

## 2026-09-13 — Infrastructure Redis, queue et Reverb

### Ajouté

- Redis PhpRedis pour cache et queues, sondes réelles et worker one-shot ;
- Laravel Reverb et broadcasting technique non métier.

## 2026-09-12 — PostgreSQL et API V1 minimale

### Ajouté

- PostgreSQL, Laravel Sanctum, API `/api/v1`, health check et tests d'authentification/rate limiting.
