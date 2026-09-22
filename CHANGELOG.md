# Barkeelu — CHANGELOG

## 2026-09-22 — Phase 09C3 Wave réelle contrôlée

### Validé

- micro-transaction Wave réelle : 100 XOF nominal + 4 XOF de frais plateforme + 1 XOF de provision payout Wave = 105 XOF payés ;
- Payment, Donation et Checkout `PAID` ; WebhookEvent `PROCESSED` ; ledger `POSTED` ; deux `AppliedFee` matérialisés ;
- journalisation structurée et expurgée des rejets Wave, incluant les détails de validation explicitement autorisés ;
- proxy egress Wave optionnel via `WAVE_HTTP_PROXY`, limité aux appels HTTP sortants Wave ;
- URL HTTPS derrière Cloudflare Tunnel via proxy trusted ;
- `success_url` et `error_url` Wave identiques, vers retour public cross-device `/retour`, `noindex,nofollow`, `no-store` et read-only ;
- webhook signé, retrieve serveur ou réconciliation comme seules autorités de paiement ;
- migrations PostgreSQL correctives R6/R7 pour `prevent_posted_mutation()` et `validate_posted_ledger()` sur bases déjà migrées ;
- synchronisation obligatoire après succès : Payment, Donation, ledger, `AppliedFee`, Checkout.

### Exploitation

- déployer les migrations Laravel normalement ; ne jamais désactiver les triggers ledger ;
- exécuter un worker queue ; exiger un webhook signé ;
- ne jamais exposer ou journaliser `WAVE_HTTP_PROXY` avec credentials éventuels ;
- ne jamais utiliser retour navigateur comme autorité de paiement.

### Validation

- suite complète : 221 tests, 1547 assertions, exit 0 ;
- `git diff --check` : OK.

## 2026-09-21 — Phase 09C2 Wave Business Portal

### Validé

- Webhook Tester Wave Business Portal validé sur `https://test.barkeelu.com/api/v1/webhooks/WAVE` ;
- serveur joignable, SSL valide et requêtes Wave signées acceptées ;
- signature invalide persistée `IGNORED`, sans traitement ni job métier, puis rejetée HTTP `401` ;
- healthcheck signé `PROCESSED` sans job métier, Payment, Donation, `AppliedFee` ni ledger ;
- `test.test_event` signé `PROCESSED` sans effet financier ;
- aucun nouvel échec de queue après correctif.

### Validation

- suite complète : 199 tests, 1395 assertions, exit 0 ;
- warning Wave « taux d'erreur récent élevé » lié aux anciennes tentatives ; non bloquant pour 09C2.

### Limite

- 09C3, micro-transaction Wave réelle contrôlée, reste requis ; Wave production reste non autorisée.

## 2026-09-20 — Phase 09C1 Wave locale durcie

### Ajouté

- 09C1A : `429`, `5xx`, timeout et réponse `2xx` incomplète deviennent `SENT_UNKNOWN`; erreurs déterministes `400`, `401`, `403`, `422` ou locales deviennent `NOT_SENT` ;
- 09C1B : `checkout.session.payment_failed` minimal, corrélation stricte `provider_account_id`/session checkout, et `test.test_event` signé persisté, dédupliqué puis `PROCESSED` sans effet métier ;
- 09C1C : provider canonique `WAVE`, comparaison insensible à la casse aux frontières, rejet sûr sans compte Wave actif unique ;
- 09C1D : limiter `webhook-wave`, 120 requêtes/minute par provider normalisé et IP, distinct de `throttle:auth-token` ;
- 09C1E : résolution `UNKNOWN` par GET session ou recherche Wave `client_reference` persistée, avec validation référence/montant/devise et mémorisation de session.

### Garanties

- aucun retry aveugle ni second POST checkout après `UNKNOWN` ;
- zéro ou plusieurs résultats Wave restent `UNKNOWN`; `PAID` reste irréversible ;
- `AppliedFee` et ledger passent uniquement par `PaymentService` après `PAID`; `FAILED` reste sans effet Finance ;
- recherche Wave jamais par téléphone ou montant seul.

### Validation

- suite locale : 197 tests, 1363 assertions, exit 0 ;
- aucune validation Wave Business Portal ni micro-transaction réelle ; Wave n'est pas production-ready.

## 2026-09-20 — Parcours public Donation / Checkout

### Ajouté

- ADR-017 appliqué : `CheckoutSession` devient le seul parcours public normal de création de Donation ;
- quote serveur, snapshot des frais, confirmation et création `Donation` `PENDING` par `DonationFactory` ;
- CTA campagne vers le parcours SSR de don, avec transparence montant, frais issus du `fee_snapshot` et total payable ;
- écrans SSR terminaux `PAID`, `FAILED`, `UNKNOWN`, `EXPIRED` et `CANCELLED`, avec fallback sans JavaScript ;
- abstraction `PaymentProviderGateway`, intégration technique `WaveGateway`, checkout et webhooks serveur ;
- chiffrement `payer_mobile_encrypted` sur `Payment` et purge planifiée des numéros expirés ;
- garde-fou Refund : seul un `Payment` `PAID` entre dans le flux de remboursement.

### Modifié

- endpoint direct `POST /api/v1/campaigns/{campaign}/donations` neutralisé : HTTP `410 Gone` ;
- `DonationService` et `StoreDonationRequest` supprimés ;
- `AppliedFee` et postings ledger créés uniquement après `Payment` `PAID` par `PaymentService` ;
- provision `PAYOUT_PROVISION_WORKING` conservée techniquement, inactive par défaut.

### Limites

- Wave est intégré côté code, mais validation E2E réelle, secrets runtime et activation production restent hors périmètre ;
- téléphone dans `checkout_sessions.donor_snapshot` reste à minimiser ou chiffrer lors de la phase PII dédiée.

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
