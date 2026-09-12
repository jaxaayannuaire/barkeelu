# Barkeelu — ERD V1.2 consolidé

Date : 12 septembre 2026
Statut : **Référence de conception avant migrations**
Projet : **Barkeelu.com**
Dépôt : `https://github.com/jaxaayannuaire/barkeelu`
Racine locale : `D:\projets_web\barkeelu\v1`

---

# 1. Objet

Ce document remplace comme référence de conception :

- `BARKEELU_ERD_V1.md`
- `BARKEELU_ERD_REVUE_V1_1.md`

Il consolide également les décisions issues de :

- `AUDIT_BARKEELU_PRE_MIGRATIONS_V1.md`
- `BARKEELU_DECISIONS_P0_TECH_LEAD_V1.md`

Il constitue la base de travail avant :

- les ADR ;
- `AGENTS.md` ;
- l’initialisation du dépôt ;
- les migrations Laravel/PostgreSQL ;
- les premières recettes Codex CLI.

Ce document décrit le **modèle logique cible**. Il ne signifie pas que les tables sont déjà implémentées.

---

# 2. Principes structurants

Barkeelu est un moteur de :

```text
Confiance
+
Fundraising
+
Transparence
+
Communauté
+
API
+
Impact
```

Principes obligatoires :

- Laravel 13 ;
- PHP 8.3 ;
- PostgreSQL ;
- Redis ;
- Laravel Sanctum ;
- Laravel Reverb ;
- API REST versionnée `/api/v1` ;
- événements métier ;
- queues ;
- transactional outbox ;
- ledger interne en double entrée ;
- idempotence ;
- auditabilité ;
- séparation des responsabilités ;
- KYC et documents privés ;
- Barkeelu Live en architecture **data-only** ;
- aucun flux vidéo transporté par Barkeelu dans l’architecture normale.

---

# 3. Vue globale

```text
USER
 ├── DONOR
 ├── SOCIAL_INFLUENCER
 ├── ORGANIZATION_MEMBER
 └── BENEFICIARY_REPRESENTATIVE
             │
             ▼
        ORGANIZATION
             │
             ├── PROGRAM
             │     └── PROJECT
             │           └── CAMPAIGN
             │
             └──────────────┐
                            ▼
                         CAMPAIGN
                            │
              ┌─────────────┼────────────────┐
              │             │                │
              ▼             ▼                ▼
        BENEFICIARY      DONATION          CONTENT
                              │
                              ▼
                           PAYMENT
                              │
                    ┌─────────┴─────────┐
                    ▼                   ▼
              WEBHOOK_EVENT          REFUND

CAMPAIGN
   │
   ├── PAYOUT
   │      └── PAYOUT_APPROVAL
   │
   ├── PROOF
   ├── REFERRAL
   ├── CAMPAIGN_INVITATION
   └── LIVE_SESSION
          ├── LIVE_DESTINATION
          ├── LIVE_OVERLAY
          ├── OVERLAY_TOKEN
          ├── LIVE_EVENT
          ├── LIVE_STATISTIC
          └── LIVE_TRACKING

PAYMENT / REFUND / PAYOUT / FEES
             │
             ▼
      LEDGER_TRANSACTION
             │
             ▼
        LEDGER_ENTRY
             │
             ▼
       LEDGER_ACCOUNT

PROVIDER ACCOUNT
   ├── WEBHOOK_EVENT
   ├── PAYMENT
   ├── REFUND
   ├── PAYOUT
   └── RECONCILIATION_RUN
          └── RECONCILIATION_ITEM
```

---

# 4. Identité

## 4.1 users

Compte utilisateur central.

```text
users
- id
- uuid
- name
- email nullable
- phone nullable
- password
- status
- locale
- timezone
- email_verified_at nullable
- phone_verified_at nullable
- is_platform_admin
- created_at
- updated_at
```

Contraintes recommandées :

- `uuid` unique ;
- email unique lorsqu’il est renseigné ;
- téléphone unique lorsqu’il est renseigné selon politique produit ;
- au moins un moyen d’identification exploitable selon le workflow d’inscription.

---

# 5. Organisations

## 5.1 organizations

Types initiaux :

```text
NGO
ASSOCIATION
DAHIRA
FOUNDATION
COMPANY
RSE_NETWORK
MOSQUE
CHURCH
HOSPITAL
SCHOOL
GOVERNMENT
SOCIAL_SERVICE
COMMUNITY_GROUP
OTHER
```

Structure :

```text
organizations
- id
- uuid
- type
- legal_name
- display_name
- slug
- description nullable
- country
- address nullable
- phone nullable
- email nullable
- website nullable
- logo_path nullable
- status
- verification_status
- created_at
- updated_at
- deleted_at nullable
```

---

## 5.2 organization_members

Relation métier User ↔ Organization.

```text
organization_members
- id
- organization_id
- user_id
- role
- status
- invited_by nullable
- joined_at nullable
- created_at
- updated_at
```

Contrainte :

```text
UNIQUE (organization_id, user_id)
```

L’appartenance à une organisation ne donne pas automatiquement le droit :

- de créer une campagne ;
- de changer un bénéficiaire ;
- d’approuver un payout ;
- de valider un KYC.

Les permissions fines sont gérées par le RBAC.

---

# 6. Bénéficiaires et représentants

## 6.1 beneficiaries

Le bénéficiaire réel des fonds est distinct du Campaign Owner.

Types :

```text
INDIVIDUAL
ORGANIZATION
COMMUNITY
OTHER
```

Structure :

```text
beneficiaries
- id
- uuid
- type
- user_id nullable
- organization_id nullable
- display_name
- legal_name nullable
- country
- status
- verification_status
- created_at
- updated_at
```

Un bénéficiaire peut exister sans compte Barkeelu.

Il n’y a pas de XOR obligatoire entre `user_id` et `organization_id` au niveau du bénéficiaire.

---

## 6.2 beneficiary_representatives

Personnes autorisées à agir pour un bénéficiaire.

```text
beneficiary_representatives
- id
- beneficiary_id
- user_id
- role
- status
- valid_from
- valid_until nullable
- verified_at nullable
- created_at
- updated_at
```

Contraintes et règles :

- relation auditable ;
- expiration possible ;
- un représentant ne peut pas automatiquement approuver ses propres opérations financières ;
- modification critique du représentant peut invalider l’éligibilité payout.

---

# 7. KYC

## 7.1 kyc_profiles

Sujet KYC explicite.

```text
kyc_profiles
- id
- uuid
- user_id nullable
- organization_id nullable
- beneficiary_id nullable
- status
- risk_level
- submitted_at nullable
- verified_at nullable
- expires_at nullable
- created_at
- updated_at
```

Contrainte :

```text
exactement un sujet parmi :
user_id
organization_id
beneficiary_id
```

---

## 7.2 kyc_documents

```text
kyc_documents
- id
- kyc_profile_id
- document_type
- storage_key
- file_hash
- status
- issued_at nullable
- expires_at nullable
- metadata
- created_at
- updated_at
```

Règles :

- stockage privé ;
- URLs temporaires si nécessaire ;
- journalisation des accès sensibles ;
- pas de secret ou pièce KYC recopiée directement dans les logs ;
- rétention à définir par politique dédiée.

---

# 8. Risk & Trust

## 8.1 risk_assessments

```text
risk_assessments
- id
- subject_type
- subject_id
- risk_level
- score nullable
- reasons
- assessed_by nullable
- assessed_at
- metadata
```

---

## 8.2 moderation_cases

```text
moderation_cases
- id
- subject_type
- subject_id
- type
- status
- priority
- assigned_to nullable
- resolution nullable
- created_at
- resolved_at nullable
```

---

## 8.3 audit_logs

```text
audit_logs
- id
- actor_id nullable
- action
- subject_type
- subject_id
- request_id
- session_id nullable
- before_data nullable
- after_data nullable
- ip_address nullable
- user_agent nullable
- created_at
```

Aucun soft delete.

Les champs sensibles doivent être expurgés.

---

# 9. Programmes et projets

## 9.1 programs

Structure légère dès le socle.

```text
programs
- id
- uuid
- organization_id
- title
- slug
- description nullable
- status
- start_date nullable
- end_date nullable
- metadata
- created_at
- updated_at
```

---

## 9.2 projects

```text
projects
- id
- uuid
- organization_id
- program_id nullable
- title
- slug
- description nullable
- status
- start_date nullable
- end_date nullable
- location nullable
- beneficiary_summary nullable
- metadata
- created_at
- updated_at
```

Une campagne peut exister sans Program/Project.

Les futurs domaines :

```text
Funder
Funding
Grant
Impact
```

restent hors MVP initial.

---

# 10. Campagnes

## 10.1 campaigns

```text
campaigns
- id
- uuid
- owner_user_id nullable
- owner_organization_id nullable
- created_by_user_id
- beneficiary_id
- program_id nullable
- project_id nullable
- category_id nullable
- title
- slug
- description
- goal_amount
- currency
- status
- fundraising_status
- payout_status
- visibility
- verification_status
- trust_score nullable
- transparency_score nullable
- featured
- published_at nullable
- start_at nullable
- end_at nullable
- closed_at nullable
- metadata
- created_at
- updated_at
- deleted_at nullable
```

Contrainte :

```text
exactement un owner :
owner_user_id XOR owner_organization_id
```

`created_by_user_id` est obligatoire pour l’audit.

---

## 10.2 campaign status

```text
DRAFT
SUBMITTED
UNDER_REVIEW
REJECTED
PUBLISHED
PAUSED
SUSPENDED
ENDED
CLOSED
CANCELLED
```

`GOAL_REACHED` n’est pas un statut principal.

Il est dérivé.

---

## 10.3 fundraising_status

```text
NOT_STARTED
OPEN
PAUSED
CLOSED
```

Contrôle l’acceptation de nouveaux dons.

---

## 10.4 payout_status

```text
NOT_ELIGIBLE
PENDING_REVIEW
ELIGIBLE
RESERVED
PROCESSING
BLOCKED
PARTIALLY_PAID
COMPLETED
```

Collecter et retirer sont deux droits distincts.

---

## 10.5 visibility

```text
PUBLIC
UNLISTED
PRIVATE
TARGETED
```

Les campagnes PRIVATE/TARGETED nécessitent des contrôles explicites sur :

- API ;
- WebSocket ;
- cache ;
- invitations ;
- liens ;
- QR ;
- Live.

---

# 11. Projections de campagne

Ne pas considérer ces champs comme source comptable.

```text
gross_collected_nominal
refunded_nominal
net_collected_nominal
available_for_payout
reserved_for_payout
paid_out_amount
donation_count
distinct_donor_count
```

Définitions :

```text
gross_collected_nominal
= principal nominal affecté aux donations confirmées

refunded_nominal
= principal nominal remboursé

net_collected_nominal
= gross_collected_nominal - refunded_nominal
```

`available_for_payout` doit être reconstruit ou vérifié à partir du ledger et des règles métier.

Aucun cache n’autorise directement un payout.

---

# 12. Donateurs

## 12.1 donors

```text
donors
- id
- user_id nullable
- display_name nullable
- anonymous_default
- country nullable
- metadata
- created_at
- updated_at
```

Un don peut être effectué sans compte utilisateur selon politique produit.

---

# 13. Fundraisers / Ambassadors / Influencers

## 13.1 fundraisers

```text
fundraisers
- id
- user_id
- campaign_id
- status
- target_amount nullable
- raised_amount_cached
- referral_code
- created_at
- updated_at
```

---

## 13.2 ambassadors

```text
ambassadors
- id
- user_id
- status
- referral_code
- commission_policy_id nullable
- created_at
- updated_at
```

---

## 13.3 social_influencers

```text
social_influencers
- id
- user_id
- display_name
- bio nullable
- status
- verification_status
- audience_size nullable
- category nullable
- created_at
- updated_at
```

---

## 13.4 social_accounts

```text
social_accounts
- id
- influencer_id
- platform
- username
- profile_url
- follower_count nullable
- verified
- metadata
- created_at
- updated_at
```

---

# 14. Donations

## 14.1 donations

```text
donations
- id
- uuid
- campaign_id
- donor_id nullable
- fundraiser_id nullable
- ambassador_id nullable
- nominal_amount
- platform_fee_amount
- payout_provision_amount
- total_due_amount
- currency
- status
- anonymous
- donor_message nullable
- confirmed_at nullable
- created_at
- updated_at
```

Statuts initiaux :

```text
PENDING
CONFIRMED
PARTIALLY_REFUNDED
REFUNDED
FAILED
CANCELLED
```

Important :

```text
Donation 1 ── N Payment
```

Aucun `payment_id` dans `donations`.

---

# 15. Politique de frais

## 15.1 fee_policies

```text
fee_policies
- id
- code
- fee_type
- rate_bps nullable
- fixed_amount nullable
- currency nullable
- effective_from
- effective_to nullable
- active
- created_at
- updated_at
```

---

## 15.2 applied_fees

```text
applied_fees
- id
- fee_policy_id nullable
- source_type
- source_id
- fee_type
- rate_bps nullable
- basis_amount
- amount
- currency
- payer
- beneficiary
- created_at
```

---

## 15.3 Règles commerciales actuelles

Décision :

```text
Barkeelu platform fee = 400 bps = 4 %
```

Hypothèse à confirmer contractuellement :

```text
Payout provision = 100 bps = 1 %
```

Formule de travail :

```text
platform_fee
= round(nominal × 4 %)

payout_provision
= round(nominal × 1 %)

total_due
= nominal + platform_fee + payout_provision
```

Exemple :

```text
Nominal              10 000
Barkeelu 4 %            400
Provision payout 1 %    100
----------------------------
Total donateur        10 500
```

La provision payout reste distincte du revenu Barkeelu.

Son traitement définitif dépendra du contrat Wave et de la politique commerciale validée.

---

# 16. Comptes fournisseur

## 16.1 provider_accounts

Permet d’éviter de confondre provider, compte et environnement.

```text
provider_accounts
- id
- provider
- account_reference
- environment
- status
- configuration_encrypted
- created_at
- updated_at
```

Exemples `environment` :

```text
TEST
PRODUCTION
```

Les secrets ne sont jamais stockés en clair dans Git.

---

# 17. Payments

## 17.1 payments

```text
payments
- id
- uuid
- donation_id
- provider_account_id
- provider_transaction_id nullable
- method
- requested_amount
- paid_amount nullable
- currency
- status
- idempotency_key
- initiated_at
- confirmed_at nullable
- failed_at nullable
- metadata
- created_at
- updated_at
```

Statuts de travail :

```text
PENDING
PROCESSING
PAID
FAILED
CANCELLED
EXPIRED
UNKNOWN
```

Contraintes :

```text
UNIQUE(idempotency_key)
```

Et, selon le périmètre fournisseur :

```text
UNIQUE(provider_account_id, provider_transaction_id)
```

lorsque `provider_transaction_id` est non nul.

---

# 18. Paiements multiples réellement réussis

Deux tentatives peuvent réellement être encaissées.

Règle :

- chaque succès fournisseur est enregistré ;
- la donation n’est affectée qu’une seule fois ;
- le succès supplémentaire devient fonds non affectés.

Compte :

```text
UNAPPLIED_FUNDS
```

Flux :

```text
2e paiement PAID
→ écriture ledger
→ UNAPPLIED_FUNDS
→ Reconciliation / Review
→ Refund ou affectation explicitement autorisée
```

Aucune contrainte du type :

```text
UNIQUE paid payment per donation
```

---

# 19. Idempotence

Séparer :

```text
API request idempotency
Provider attempt
Webhook event
Domain operation
Ledger posting
Refund request
Payout request
```

Chaque domaine possède sa propre clé.

Une même clé réutilisée avec :

- un montant différent ;
- une devise différente ;
- une ressource critique différente ;

doit être rejetée et auditée.

---

# 20. Webhook Events

## 20.1 webhook_events

```text
webhook_events
- id
- uuid
- provider_account_id
- external_event_id nullable
- event_type
- payload
- payload_hash
- signature_valid
- processing_status
- attempts
- received_at
- processed_at nullable
- error_message nullable
```

Contrainte lorsque possible :

```text
UNIQUE(provider_account_id, external_event_id)
```

---

## 20.2 Flux webhook

```text
Provider
   ↓
Raw HTTP body
   ↓
Signature verification
   ↓
WebhookEvent persisté
   ↓
ACK provider
   ↓
Queue
   ↓
Domain processing
   ↓
Payment / Donation
   ↓
Ledger
   ↓
OutboxEvent
```

Le système doit tolérer :

- doublons ;
- arrivée désordonnée ;
- retry ;
- retard ;
- interruption worker.

---

# 21. Ledger

Le ledger Barkeelu est un **ledger technique interne**.

Il ne remplace pas la comptabilité fiscale/légale de Jaxaay Group.

---

## 21.1 ledger_accounts

```text
ledger_accounts
- id
- code
- name
- account_class
- currency
- owner_type nullable
- owner_id nullable
- active
- created_at
```

Comptes de travail :

### Assets / Clearing

```text
PAYMENT_CLEARING
SETTLEMENT_CLEARING
PROVIDER_FUNDS
```

### Liabilities

```text
CAMPAIGN_PAYABLE
PAYOUT_PROVISION_RESERVE
REFUND_PAYABLE
UNAPPLIED_FUNDS
```

### Revenue

```text
PLATFORM_FEE_REVENUE
```

### Expenses

```text
PAYMENT_PROVIDER_FEE_EXPENSE
PAYOUT_PROVIDER_FEE_EXPENSE
```

---

## 21.2 ledger_transactions

```text
ledger_transactions
- id
- uuid
- business_key
- reference
- transaction_type
- source_type
- source_id
- currency
- status
- posted_at nullable
- reversed_transaction_id nullable
- metadata
- created_at
```

Pas de soft delete.

---

## 21.3 ledger_entries

```text
ledger_entries
- id
- ledger_transaction_id
- ledger_account_id
- direction
- amount
- currency
- created_at
```

Directions :

```text
DEBIT
CREDIT
```

---

# 22. Invariants ledger

Obligatoires :

- montant strictement positif sur les lignes ;
- minimum deux lignes ;
- devise cohérente ;
- total DEBIT = total CREDIT ;
- transaction postée immuable ;
- pas d’UPDATE/DELETE après posting ;
- correction par reversal ;
- business key idempotente par effet financier ;
- source métier identifiable.

Le mécanisme d’intégrité PostgreSQL précis sera défini par ADR :

```text
posting function
ou
deferred constraint trigger
```

avec tests de concurrence sur PostgreSQL réel.

---

# 23. Exemple ledger — donation encaissée

Exemple conceptuel :

```text
Nominal                 10 000
Platform fee               400
Payout provision           100
Total payé              10 500
```

Écriture :

```text
DEBIT  PROVIDER_FUNDS              10 500
CREDIT CAMPAIGN_PAYABLE            10 000
CREDIT PLATFORM_FEE_REVENUE           400
CREDIT PAYOUT_PROVISION_RESERVE       100
```

Cette écriture devra être adaptée aux mécanismes réels :

- checkout ;
- settlement ;
- fees ;
- balance ;
- payout Wave.

---

# 24. Transactional Outbox

## 24.1 outbox_events

```text
outbox_events
- id
- uuid
- aggregate_type
- aggregate_id
- event_type
- payload
- status
- attempts
- available_at
- processed_at nullable
- error_message nullable
- created_at
```

Flux :

```text
DB transaction
├── business state
├── ledger
└── outbox event
        ↓
commit
        ↓
worker
        ↓
Redis / Reverb / notifications / projections
```

Redis n’est jamais source de vérité.

---

# 25. Refunds

## 25.1 refunds

```text
refunds
- id
- uuid
- donation_id
- payment_id
- requested_by
- amount
- currency
- reason
- status
- idempotency_key
- provider_reference nullable
- ledger_transaction_id nullable
- requested_at
- processed_at nullable
- created_at
- updated_at
```

Statuts de travail :

```text
PENDING
RESERVED
PROCESSING
REFUNDED
FAILED
CANCELLED
UNKNOWN
```

Règles :

```text
refunds executed + reserved
<= refundable amount
```

Contrôle atomique.

---

# 26. Payout Reservations

Créer explicitement une réservation financière.

## 26.1 payout_reservations

```text
payout_reservations
- id
- uuid
- campaign_id
- beneficiary_id
- amount
- currency
- status
- expires_at nullable
- released_at nullable
- created_at
```

Statuts :

```text
ACTIVE
CONSUMED
RELEASED
EXPIRED
```

Cette table pourra être matérialisée séparément ou comme mécanisme ledger, selon ADR.

Le principe de réservation reste obligatoire.

---

# 27. Payouts

## 27.1 payouts

```text
payouts
- id
- uuid
- campaign_id
- beneficiary_id
- reservation_id nullable
- requested_by
- provider_account_id
- destination_type
- destination_snapshot_encrypted
- gross_amount
- provider_fee_amount
- net_amount
- currency
- status
- idempotency_key
- provider_reference nullable
- ledger_transaction_id nullable
- requested_at
- approved_at nullable
- processed_at nullable
- failed_at nullable
- created_at
- updated_at
```

Statuts :

```text
PENDING_REVIEW
APPROVED
RESERVED
PROCESSING
PAID
FAILED
CANCELLED
UNKNOWN
```

---

# 28. Payout Approvals

## 28.1 payout_approvals

```text
payout_approvals
- id
- payout_id
- reviewer_id
- decision
- comment nullable
- beneficiary_version
- destination_version
- kyc_version nullable
- decided_at
```

Décisions :

```text
APPROVED
REJECTED
```

Règles :

- demandeur ≠ approbateur ;
- `finance_operator ≠ finance_approver` ;
- changement critique invalide l’approbation ;
- destination approuvée figée/auditée.

---

# 29. Timeout payout

Un timeout fournisseur produit :

```text
UNKNOWN
```

et non :

```text
FAILED
```

automatiquement.

Flux :

```text
UNKNOWN
→ verify/reconcile
→ PAID
ou
→ FAILED
```

Aucune nouvelle émission tant que l’état précédent n’est pas suffisamment établi.

---

# 30. Réconciliation

## 30.1 reconciliation_runs

```text
reconciliation_runs
- id
- uuid
- provider_account_id
- period_start
- period_end
- source_reference nullable
- currency nullable
- status
- started_at
- completed_at nullable
- counters
- errors nullable
```

---

## 30.2 reconciliation_items

```text
reconciliation_items
- id
- reconciliation_run_id
- object_type
- object_id nullable
- provider_reference
- internal_amount nullable
- provider_amount nullable
- internal_status nullable
- provider_status nullable
- discrepancy_amount nullable
- status
- resolution nullable
- resolved_by nullable
- resolved_at nullable
- details
```

Statuts :

```text
MATCHED
MISSING_INTERNAL
MISSING_PROVIDER
AMOUNT_MISMATCH
STATUS_MISMATCH
REVIEW_REQUIRED
```

Couvre au minimum :

- payments ;
- refunds ;
- payouts ;
- provider fees ;
- settlements.

---

# 31. Proofs / Transparency

## 31.1 proofs

```text
proofs
- id
- campaign_id nullable
- project_id nullable
- author_id
- type
- title
- description nullable
- storage_key
- public_storage_key nullable
- file_hash
- captured_at nullable
- verification_status
- verified_at nullable
- metadata
- created_at
- updated_at
```

Types :

```text
PHOTO
VIDEO
DOCUMENT
RECEIPT
INVOICE
THIRD_PARTY_CONFIRMATION
```

Les originaux privés et les versions publiques expurgées doivent pouvoir être distincts.

---

# 32. Scores

Trois notions indépendantes :

```text
Verification Status
Trust Score
Transparency Score
```

Elles ne sont pas une note utilisateur.

---

# 33. Content

## 33.1 contents

```text
contents
- id
- uuid
- author_id
- organization_id nullable
- project_id nullable
- campaign_id nullable
- type
- title
- slug
- body
- status
- published_at nullable
- metadata
- created_at
- updated_at
- deleted_at nullable
```

Types :

```text
ARTICLE
UPDATE
ANNOUNCEMENT
REPORT
STORY
LIVE
```

---

# 34. Collections privées et ciblées

## 34.1 contacts

```text
contacts
- id
- owner_user_id nullable
- owner_organization_id nullable
- name
- phone nullable
- email nullable
- metadata
- created_at
- updated_at
- deleted_at nullable
```

---

## 34.2 contact_groups

```text
contact_groups
- id
- owner_user_id nullable
- owner_organization_id nullable
- name
- description nullable
- created_at
- updated_at
```

---

## 34.3 contact_group_members

```text
contact_group_members
- contact_group_id
- contact_id
```

Contrainte :

```text
UNIQUE(contact_group_id, contact_id)
```

---

## 34.4 campaign_invitations

```text
campaign_invitations
- id
- campaign_id
- contact_id nullable
- invited_by
- token_hash
- status
- expires_at nullable
- sent_at nullable
- opened_at nullable
- converted_at nullable
- revoked_at nullable
- created_at
```

Le token brut n’est pas conservé.

---

# 35. Referral / Affiliate

## 35.1 referrals

```text
referrals
- id
- campaign_id
- referrer_type
- referrer_id
- code
- status
- clicks_cached
- conversions_cached
- raised_amount_cached
- created_at
- updated_at
```

---

## 35.2 affiliate_links

```text
affiliate_links
- id
- referral_id
- destination
- short_code
- utm_source nullable
- utm_medium nullable
- utm_campaign nullable
- created_at
```

---

## 35.3 commissions

```text
commissions
- id
- referral_id
- beneficiary_type
- beneficiary_id
- basis_amount
- rate_bps
- amount
- currency
- status
- paid_at nullable
- created_at
```

Les commissions commerciales restent séparées :

- des frais plateforme ;
- des frais provider ;
- des frais payout.

---

# 36. Barkeelu Live

Principe :

```text
Barkeelu ne transporte pas le flux vidéo.
```

Barkeelu fournit :

- données ;
- events ;
- overlay ;
- tracking ;
- QR ;
- analytics ;
- adapters fournisseurs.

---

## 36.1 live_providers

Catalogue global.

```text
live_providers
- id
- code
- name
- capabilities
- active
- created_at
- updated_at
```

---

## 36.2 live_provider_connections

Connexion utilisateur/organisation.

```text
live_provider_connections
- id
- provider_id
- user_id nullable
- organization_id nullable
- status
- credentials_encrypted
- expires_at nullable
- metadata
- created_at
- updated_at
```

---

## 36.3 live_sessions

```text
live_sessions
- id
- uuid
- campaign_id
- provider_connection_id nullable
- title
- status
- started_at nullable
- ended_at nullable
- metadata
- created_at
- updated_at
```

Statuts :

```text
DRAFT
SCHEDULED
LIVE
ENDED
CANCELLED
ERROR
```

---

## 36.4 live_destinations

```text
live_destinations
- id
- live_session_id
- provider_connection_id nullable
- platform
- external_id nullable
- status
- public_url nullable
- ingestion_secret_encrypted nullable
- metadata
- created_at
- updated_at
```

Ne pas exposer un endpoint d’ingestion comme URL publique.

---

## 36.5 live_overlays

```text
live_overlays
- id
- live_session_id
- format
- transparent
- theme
- show_goal
- show_collected
- show_percentage
- show_donor_count
- show_remaining
- show_qr
- show_url
- branding
- active
- created_at
- updated_at
```

Cardinalité :

```text
LiveSession 1 ── N LiveOverlay
```

---

## 36.6 overlay_tokens

```text
overlay_tokens
- id
- live_session_id
- live_overlay_id nullable
- token_hash
- scope
- expires_at
- revoked_at nullable
- created_at
```

Le token brut n’est jamais persisté.

---

## 36.7 live_events

```text
live_events
- id
- live_session_id
- event_type
- payload
- occurred_at
- created_at
```

Exemples :

```text
DONATION_CONFIRMED
GOAL_PROGRESS
DONOR_COUNT_CHANGED
LIVE_STARTED
LIVE_ENDED
```

Les événements doivent respecter l’anonymat du donateur.

---

## 36.8 live_statistics

```text
live_statistics
- id
- live_session_id
- viewers nullable
- clicks
- qr_scans
- donations
- amount
- captured_at
```

---

## 36.9 live_trackings

```text
live_trackings
- id
- campaign_id
- live_session_id nullable
- code
- destination_url
- source nullable
- medium nullable
- campaign_tag nullable
- created_at
```

---

# 37. Live Data API

Endpoint cible :

```text
GET /api/v1/campaigns/{campaign}/live-data
```

Exemple conceptuel :

```json
{
  "campaign": {
    "id": "uuid",
    "title": "Soutien aux Daaras 2026",
    "currency": "XOF"
  },
  "fundraising": {
    "goal": 50000000,
    "net_collected_nominal": 37450000,
    "percentage": 74.9,
    "donation_count": 8452,
    "remaining": 12550000
  },
  "live_session": {
    "status": "LIVE"
  },
  "projection": {
    "version": 153249,
    "generated_at": "2026-09-12T18:00:00Z"
  }
}
```

Le statut `LIVE` appartient à `LiveSession`, pas au statut de campagne.

---

# 38. Confidentialité Live

Pour PRIVATE/TARGETED :

- auth/authorization sur API ;
- authorization WebSocket ;
- cache privé ;
- tokens limités ;
- QR scoped ;
- liens expirables si nécessaire.

Un OverlayToken n’accorde jamais plus que son scope.

Un don anonyme ne doit pas révéler :

- nom ;
- téléphone ;
- email ;
- identifiant interne personnel.

---

# 39. Provider Adapter Pattern

Interface conceptuelle :

```php
interface LiveProviderInterface
{
    public function capabilities(): array;
    public function createSession(array $data): array;
    public function getStatus(string $externalId): array;
}
```

Les opérations suivantes doivent être optionnelles selon capacités :

```text
startSession
stopSession
createDestination
removeDestination
```

Ne pas forcer un provider natif à supporter une opération inexistante.

Adapters prévus :

```text
YouTubeAdapter
RestreamAdapter
CastrAdapter
NativeAdapter
FutureSelfHostedAdapter
```

Aucune dépendance critique ne doit être bâtie sur une API TikTok LIVE non officielle.

---

# 40. API V1

L’API technique existe dès le MVP.

Clients :

- Laravel SSR ;
- Flutter ;
- bots/services internes.

Ouverture publique partenaire progressive.

Ressources principales :

```text
/api/v1/auth
/api/v1/users
/api/v1/organizations
/api/v1/beneficiaries
/api/v1/programs
/api/v1/projects
/api/v1/campaigns
/api/v1/donations
/api/v1/payments
/api/v1/refunds
/api/v1/payouts
/api/v1/reports
/api/v1/statistics
/api/v1/live
/api/v1/integrations
```

Callbacks providers :

```text
/api/v1/webhooks/{provider}
```

Routes d’administration séparées selon gouvernance.

---

# 41. Montants API

Les montants restent des entiers.

Pour éviter les problèmes de précision chez certains clients :

Option recommandée pour API publique :

```json
{
  "amount": "10500",
  "currency": "XOF"
}
```

Le backend conserve :

```text
BIGINT
```

---

# 42. Contraintes physiques prioritaires

## Campaign owner

```text
CHECK XOR(owner_user_id, owner_organization_id)
```

## Organization member

```text
UNIQUE(organization_id, user_id)
```

## Payment idempotency

```text
UNIQUE(idempotency_key)
```

## Provider transaction

```text
UNIQUE(provider_account_id, provider_transaction_id)
```

pour valeurs non nulles.

## Webhook event

```text
UNIQUE(provider_account_id, external_event_id)
```

lorsqu’un ID externe stable existe.

## Token hashes

```text
UNIQUE(token_hash)
```

## Money

```text
CHECK(amount >= 0)
```

ou strictement `> 0` lorsque la sémantique l’exige.

---

# 43. Soft Deletes

Acceptables selon domaine :

```text
organizations
campaigns
contents
contacts
social profiles
```

Interdits pour :

```text
payments
refunds
payouts
webhook_events
ledger_transactions
ledger_entries
reconciliation_runs
reconciliation_items
audit_logs
```

---

# 44. Indexes prioritaires

```text
campaigns(slug)
campaigns(status, published_at)
campaigns(owner_organization_id, status)
campaigns(owner_user_id, status)
campaigns(beneficiary_id, status)

donations(campaign_id, status, created_at)
donations(donor_id, created_at)

payments(donation_id, status)
payments(provider_account_id, provider_transaction_id)
payments(idempotency_key)

webhook_events(provider_account_id, external_event_id)
webhook_events(processing_status, received_at)

refunds(donation_id, status)
refunds(payment_id, status)

payouts(campaign_id, status)
payouts(beneficiary_id, status)

ledger_transactions(source_type, source_id)
ledger_transactions(business_key)
ledger_entries(ledger_account_id, id)

reconciliation_items(reconciliation_run_id, status)

live_sessions(campaign_id, status)
overlay_tokens(token_hash)
```

---

# 45. RBAC plateforme initial

Rôles de travail :

```text
platform_admin
campaign_reviewer
kyc_reviewer
finance_operator
finance_approver
platform_auditor
```

Règles :

```text
finance_operator != finance_approver
```

Un utilisateur ne peut pas approuver sa propre demande de payout.

Le RBAC organisationnel sera distinct des privilèges plateforme.

---

# 46. Séparation des responsabilités

Aucun acteur unique ne doit normalement pouvoir :

```text
Créer la campagne
+
Valider son KYC
+
Changer le bénéficiaire
+
Changer la destination
+
Approuver le payout
+
Exécuter le payout
+
Modifier le ledger
```

Les opérations sensibles doivent être auditables.

---

# 47. Périmètre MVP

Inclus :

```text
Users
Organizations
OrganizationMembers
Platform RBAC
Beneficiaries
Beneficiary Representatives
KYC minimal
Programs légers
Projects légers
Campaigns
Donors
Donations
Wave payment
Webhook persistence
FeePolicy
AppliedFee
Ledger
Transactional Outbox
Refund
Payout
PayoutReservation
PayoutApproval
Reconciliation
AuditLog
Proof / Transparency minimal
API technique
Barkeelu Live data-only
Redis
Laravel Reverb
Live Overlay
QR / tracked links
```

---

# 48. Hors MVP initial

```text
Stripe
PayPal
Card payment
Chargebacks
Marketplace e-commerce
POS
Yessal Caisse business logic
Advanced gamification
Full grants/funding module
Advanced public directory
Full enterprise white-label
Self-hosted video relay
TikTok unofficial LIVE integration
```

Ces domaines peuvent être ajoutés ensuite sans modifier les invariants financiers du noyau.

---

# 49. Ordre de migrations recommandé

Après ADR et validation finale du schéma :

```text
Lot 1
Users
Organizations
OrganizationMembers
Platform RBAC

Lot 2
Beneficiaries
BeneficiaryRepresentatives
KYCProfiles
KYCDocuments
AuditLogs

Lot 3
Programs
Projects
Campaigns
Campaign categories

Lot 4
ProviderAccounts
FeePolicies
LedgerAccounts
LedgerTransactions
LedgerEntries
OutboxEvents

Lot 5
Donors
Donations
Payments
WebhookEvents
AppliedFees

Lot 6
Refunds
PayoutReservations
Payouts
PayoutApprovals
ReconciliationRuns
ReconciliationItems

Lot 7
Proofs
Contents
RiskAssessments
ModerationCases

Lot 8
Contacts
ContactGroups
CampaignInvitations
Referrals
AffiliateLinks
Commissions
LiveProviders
LiveProviderConnections
LiveSessions
LiveDestinations
LiveOverlays
OverlayTokens
LiveEvents
LiveStatistics
LiveTrackings
```

---

# 50. Tests obligatoires avant collecte réelle

## Identité / sécurité

- autre organisation ;
- rôle absent ;
- permission absente ;
- owner invalide ;
- double owner ;
- changement de bénéficiaire.

## Payment

- montant incorrect ;
- devise incorrecte ;
- compte provider incorrect ;
- double clic ;
- double webhook ;
- webhook désordonné ;
- timeout ;
- deux paiements réellement réussis.

## Ledger

- écriture déséquilibrée ;
- devise incohérente ;
- modification après posting ;
- double business key ;
- concurrence.

## Refund

- partiel ;
- total ;
- double refund ;
- refunds concurrents ;
- dépassement du remboursable.

## Payout

- approbateur = demandeur ;
- KYC invalide ;
- destination modifiée ;
- double payout ;
- payout concurrent ;
- timeout provider ;
- réconciliation avant retry.

## Outbox

- panne après commit DB ;
- panne Redis ;
- reprise worker ;
- event rejoué.

## Live

- token expiré ;
- token révoqué ;
- campagne privée ;
- don anonyme ;
- reconnect WebSocket ;
- fallback polling.

---

# 51. Décisions encore externes à confirmer

Le schéma peut avancer, mais les points suivants restent externes :

## Wave

À confirmer contractuellement :

- frais payout ;
- assiette ;
- limites ;
- types de destinations ;
- disponibilité/settlement des fonds ;
- callbacks ;
- réconciliation ;
- environnement de production ;
- conditions du compte marchand.

## Réglementation

À traiter séparément :

- gestion de fonds de tiers ;
- obligations KYC/AML ;
- données personnelles ;
- fiscalité ;
- diaspora ;
- reçus ;
- conservation réglementaire.

Aucune hypothèse historique ne doit être codée comme règle légale sans vérification.

---

# 52. ADR requis avant migrations financières

Minimum :

```text
ADR-001 — Campaign Owner / Beneficiary / Representative
ADR-002 — Ledger double entrée
ADR-003 — Payment Provider et idempotence
ADR-004 — Webhook persistence et traitement asynchrone
ADR-005 — Payout reservation et approval
ADR-006 — Transactional Outbox
ADR-007 — Barkeelu Live data-only
ADR-008 — Stockage privé KYC
```

---

# 53. GO / NO-GO

## GO

- générer les ADR ;
- générer `AGENTS.md` ;
- générer README/ROADMAP/CHANGELOG initiaux ;
- initialiser le dépôt ;
- préparer le socle Laravel ;
- commencer les lots non financiers après audit du dépôt.

## GO conditionnel

Migrations financières uniquement après validation des ADR correspondants.

## NO-GO

- collecte réelle ;
- payout réel ;
- production financière ;

tant que :

```text
ERD
+
ADR
+
tests
+
contrat provider
+
sécurité
+
réconciliation
```

ne sont pas suffisamment validés.

---

# 54. Référence de conception

À partir de cette version :

```text
BARKEELU_ERD_V1_2_CONSOLIDE.md
```

devient la référence ERD principale.

Les versions antérieures restent historiques :

```text
BARKEELU_ERD_V1.md
BARKEELU_ERD_REVUE_V1_1.md
```

mais ne doivent plus être utilisées seules pour générer les migrations.
