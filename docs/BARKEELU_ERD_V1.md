# Barkeelu — ERD V1

## 1. Objectif

Ce document définit le modèle de données métier de Barkeelu V1. Il constitue la base de conception de la base PostgreSQL et de l'API Laravel.

Principes :
- Laravel 13 / PHP 8.3
- PostgreSQL
- Redis / queues / events
- Ledger financier en double entrée dès le MVP
- API REST V1 dès le départ
- séparation stricte entre métier, paiements et infrastructure Live
- Barkeelu Live ne transporte pas la vidéo en architecture normale

---

# 2. Hiérarchie métier

```text
User
 ├── Organization
 │    ├── Program
 │    │    └── Project
 │    │         └── Campaign
 │    └── OrganizationMember
 │
 ├── Donor
 ├── Fundraiser
 ├── Ambassador
 └── SocialInfluencer

Campaign
 ├── Donations
 ├── Payments
 ├── Ledger
 ├── Payouts
 ├── Content / Updates
 ├── Proofs
 ├── Trust / Verification
 └── Barkeelu Live
```

---

# 3. Principales entités

## User

Compte utilisateur central.

Champs principaux :
- id
- uuid
- name
- email
- phone
- password
- status
- locale
- timezone
- email_verified_at
- phone_verified_at
- created_at
- updated_at

Relations :
- organizations
- donations
- campaigns owned
- fundraiser profiles
- ambassador profiles
- influencer profiles
- KYC profile

---

## Organization

Structure porteuse ou partenaire.

Types :
- NGO
- ASSOCIATION
- DAHIRA
- FOUNDATION
- COMPANY
- RSE_NETWORK
- MOSQUE
- CHURCH
- HOSPITAL
- SCHOOL
- GOVERNMENT
- SOCIAL_SERVICE
- COMMUNITY_GROUP
- OTHER

Champs :
- id
- uuid
- type
- legal_name
- display_name
- slug
- description
- country
- address
- phone
- email
- website
- logo
- status
- verification_status
- created_at
- updated_at

Relations :
- users via OrganizationMember
- programs
- projects
- campaigns

---

# 4. Program / Project / Campaign

## Program

Regroupe plusieurs projets ou campagnes autour d'une même mission.

Champs :
- id
- organization_id
- title
- slug
- description
- status
- start_date
- end_date
- metadata

## Project

Action concrète appartenant à un programme.

Champs :
- id
- program_id nullable
- organization_id nullable
- title
- slug
- description
- status
- start_date
- end_date
- location
- beneficiary_summary
- metadata

## Campaign

Moteur de collecte financière.

Champs :
- id
- organization_id nullable
- program_id nullable
- project_id nullable
- owner_user_id
- beneficiary_id
- title
- slug
- description
- category_id
- goal_amount
- currency
- collected_amount
- donor_count
- start_at
- end_at
- status
- visibility
- featured
- verification_status
- trust_score
- transparency_score
- published_at
- closed_at
- metadata
- created_at
- updated_at

Visibilité :
- PUBLIC
- UNLISTED
- PRIVATE
- TARGETED

Statuts :
- DRAFT
- SUBMITTED
- UNDER_REVIEW
- REJECTED
- PUBLISHED
- PAUSED
- SUSPENDED
- GOAL_REACHED
- ENDED
- CLOSED
- CANCELLED

> Les montants financiers de référence doivent être calculés à partir du ledger et/ou de données transactionnelles fiables. `collected_amount` peut être une projection/cache pour lecture rapide.

---

# 5. Donateurs et mobilisation

## Donor

Profil de donateur éventuellement rattaché à User.

- id
- user_id nullable
- display_name
- anonymous_default
- country
- metadata

## Fundraiser

Personne mobilisant des fonds pour une campagne.

- id
- user_id
- campaign_id
- status
- target_amount nullable
- raised_amount
- referral_code
- created_at

## Ambassador

Profil de mobilisation/référencement.

- id
- user_id
- status
- referral_code
- commission_policy_id nullable

## SocialInfluencer

Profil influenceur.

- id
- user_id
- display_name
- bio
- status
- verification_status
- audience_size nullable
- category nullable

## SocialAccount

Comptes sociaux d'un influenceur.

- id
- influencer_id
- platform
- username
- profile_url
- follower_count
- verified
- metadata

---

# 6. Donations / Payments

## Donation

Intention et opération de don.

- id
- uuid
- campaign_id
- donor_id nullable
- fundraiser_id nullable
- ambassador_id nullable
- payment_id nullable
- nominal_amount
- donor_fee
- total_paid
- currency
- anonymous
- status
- donor_message
- metadata
- created_at
- confirmed_at

Statuts :
- PENDING
- CONFIRMED
- FAILED
- REFUNDED
- CANCELLED

Règle :
`total_paid = nominal_amount + donor_fee`

Barkeelu fee V1 :
- 4% payé par le donateur.

## Payment

Transaction auprès d'un prestataire de paiement.

- id
- uuid
- donation_id nullable
- provider
- provider_transaction_id
- payment_method
- amount
- currency
- status
- idempotency_key
- provider_payload
- paid_at
- failed_at
- created_at
- updated_at

Le navigateur ne confirme jamais un paiement. La confirmation fiable provient du webhook/prestataire.

---

# 7. Ledger financier

## LedgerAccount

Compte comptable interne.

Exemples :
- donor clearing
- campaign payable
- platform fees
- payout fees
- refunds
- settlement

Champs :
- id
- code
- name
- type
- currency
- owner_type nullable
- owner_id nullable
- status

## LedgerTransaction

Écriture financière globale.

- id
- uuid
- reference
- type
- source_type
- source_id
- currency
- status
- posted_at
- metadata

## LedgerEntry

Ligne débit/crédit.

- id
- ledger_transaction_id
- ledger_account_id
- direction
- amount
- currency

Contraintes :
- aucune écriture sans transaction
- somme des débits = somme des crédits
- montants en entier dans la devise minimale
- jamais de float pour l'argent
- écritures immuables après validation

---

# 8. Fees

## Fee

Frais appliqués à une opération.

- id
- source_type
- source_id
- fee_type
- rate
- amount
- currency
- payer
- beneficiary
- status
- metadata

Types V1 :
- BARKEELU_PLATFORM
- PAYMENT_PROVIDER
- PAYOUT_PROVISION
- REFUND
- OTHER

Règle commerciale validée :
- Barkeelu : 4% payé par le donateur.
- Provision payout Wave : 1%, sous réserve de confirmation contractuelle.
- Le coût réel ne doit pas être présenté comme une marge cachée.

---

# 9. Payout

## Payout

Versement vers le bénéficiaire/promoteur selon les règles de la campagne.

- id
- uuid
- campaign_id
- beneficiary_id
- provider
- destination_type
- destination_reference
- amount
- currency
- status
- provider_reference
- requested_at
- processed_at
- failed_at
- metadata

Statuts :
- PENDING
- APPROVED
- PROCESSING
- PAID
- FAILED
- CANCELLED

Séparation des rôles :
- création campagne
- validation KYC
- approbation payout
- modification ledger

ne doivent pas être concentrées sur un même acteur.

---

# 10. KYC / Trust & Safety

## KYCProfile

- id
- user_id
- organization_id nullable
- status
- document_type
- document_reference
- verified_at
- expires_at
- metadata

## RiskAssessment

- id
- subject_type
- subject_id
- risk_level
- score
- reasons
- assessed_at
- reviewer_id nullable

## ModerationCase

- id
- subject_type
- subject_id
- type
- status
- priority
- assigned_to
- resolution
- created_at
- resolved_at

## AuditLog

Journal des actions sensibles.

- id
- actor_id
- action
- subject_type
- subject_id
- before
- after
- ip
- user_agent
- created_at

---

# 11. Proofs / Transparency

## Proof

Évidence liée à une campagne, un projet ou une opération.

Types :
- PHOTO
- VIDEO
- DOCUMENT
- RECEIPT
- INVOICE
- THIRD_PARTY_CONFIRMATION

Champs :
- id
- campaign_id nullable
- project_id nullable
- author_id
- type
- title
- description
- file
- file_hash
- captured_at nullable
- verified_at nullable
- verification_status
- metadata

## Trust / Verification / Transparency

Ces trois notions restent distinctes :

- Verification Status : identité/structure contrôlée.
- Trust Score : indicateur de confiance.
- Transparency Score : qualité des preuves, rapports et informations publiées.

Ils ne constituent pas une simple note utilisateur.

---

# 12. Content

## Content

Base générique pour les contenus.

Types :
- ARTICLE
- UPDATE
- ANNOUNCEMENT
- REPORT
- STORY
- LIVE

Champs :
- id
- author_id
- campaign_id nullable
- project_id nullable
- organization_id nullable
- type
- title
- slug
- body
- status
- published_at
- metadata

Fonctions futures :
- médias
- galerie
- vidéos
- documents
- liens
- tags
- commentaires
- partages
- vues
- programmation
- modération

---

# 13. Private / Targeted Collections

## Contact

- id
- owner_id
- name
- phone
- email
- metadata

## ContactGroup

- id
- owner_id
- name
- description

## ContactGroupMember

- contact_group_id
- contact_id

## CampaignInvitation

- id
- campaign_id
- contact_id nullable
- invited_by
- token
- status
- sent_at
- opened_at
- converted_at

La confidentialité, le consentement et les règles d'utilisation des contacts doivent être intégrés dès la conception.

---

# 14. Referral / Affiliate

## Referral

- id
- campaign_id
- referrer_type
- referrer_id
- code
- clicks
- conversions
- raised_amount
- status

## AffiliateLink

- id
- referral_id
- destination
- short_code
- utm_source
- utm_medium
- utm_campaign

## Commission

- id
- referral_id
- beneficiary_type
- beneficiary_id
- basis_amount
- rate
- amount
- currency
- status
- paid_at

Les commissions commerciales doivent rester séparées des frais financiers de la campagne.

---

# 15. Funding / Grants / Impact

## Funder

- id
- organization_id nullable
- name
- type
- status

## Funding

- id
- funder_id
- amount
- currency
- start_date
- end_date
- status

## Grant

- id
- funding_id
- program_id
- amount
- currency
- status
- conditions

## ImpactMetric

- id
- project_id
- name
- value
- unit
- target
- measured_at

Flux :

```text
FUNDER
   ↓
FUNDING / GRANT
   ↓
PROGRAM
   ↓
PROJECT
   ↓
CAMPAIGN
   ↓
DONATIONS / PAYMENTS
   ↓
IMPACT
```

---

# 16. Barkeelu Live

## LiveProvider

Fournisseur externe.

- id
- name
- type
- status
- capabilities
- configuration

Types possibles :
- YOUTUBE
- RESTREAM
- CASTR
- NATIVE
- FUTURE_SELF_HOSTED

## LiveSession

Une session Live liée à une campagne.

- id
- campaign_id
- provider_id nullable
- title
- status
- started_at
- ended_at
- overlay_token_id nullable
- metadata

Statuts :
- DRAFT
- SCHEDULED
- LIVE
- ENDED
- CANCELLED
- ERROR

## LiveDestination

Destination d'une session.

- id
- live_session_id
- platform
- external_id
- status
- stream_url nullable
- metadata

## LiveOverlay

Configuration de l'overlay.

- id
- live_session_id
- format
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

Formats :
- 16:9
- 9:16
- transparent

## LiveEvent

Événement temps réel.

- id
- live_session_id
- event_type
- payload
- occurred_at

Exemples :
- DONATION_CONFIRMED
- GOAL_PROGRESS
- DONOR_COUNT_CHANGED
- LIVE_STARTED
- LIVE_ENDED

## LiveStatistic

- id
- live_session_id
- viewers nullable
- clicks
- qr_scans
- donations
- amount
- captured_at

## LiveTracking

Traçabilité des liens/QR.

- id
- campaign_id
- live_session_id nullable
- code
- destination_url
- source
- medium
- campaign_tag
- clicks
- conversions

---

# 17. Architecture Live

Principe :

```text
Payment
   ↓
Donation
   ↓
Ledger
   ↓
Campaign totals
   ↓
Domain Event
   ↓
Redis
   ↓
Laravel Reverb
   ↓
Live Overlay
```

Barkeelu ne transporte normalement pas le flux vidéo.

Les fournisseurs vidéo sont accessibles via une abstraction :

```php
interface LiveProviderInterface
{
    public function createSession(array $data): array;
    public function startSession(string $externalId): array;
    public function stopSession(string $externalId): array;
    public function getStatus(string $externalId): array;
}
```

Adaptateurs :
- YouTubeAdapter
- RestreamAdapter
- CastrAdapter
- NativeAdapter
- FutureSelfHostedAdapter

---

# 18. API V1

L'API doit être conçue dès le MVP.

Ressources principales :

```text
/api/v1/users
/api/v1/organizations
/api/v1/programs
/api/v1/projects
/api/v1/campaigns
/api/v1/donations
/api/v1/payments
/api/v1/payouts
/api/v1/reports
/api/v1/statistics
/api/v1/events
/api/v1/live
/api/v1/integrations
/api/v1/webhooks
```

Live Data :

```text
GET /api/v1/campaigns/{campaign}/live-data
```

Réponse type :

```json
{
  "campaign": "Soutien aux Daaras 2026",
  "goal": 50000000,
  "collected": 37450000,
  "percentage": 74.9,
  "donors": 8452,
  "remaining": 12550000,
  "currency": "XOF",
  "status": "LIVE"
}
```

Pour les overlays :
- token public à durée courte
- scope minimal
- rate limiting
- cache Redis
- polling fallback
- WebSocket principal
- timestamps serveur

---

# 19. Contraintes techniques essentielles

## Argent

- PostgreSQL `BIGINT` pour les montants en unité minimale.
- Aucun `FLOAT` pour les valeurs monétaires.
- Devise obligatoire.
- Ledger équilibré.
- Transactions idempotentes.

## Webhooks

- idempotency key obligatoire.
- payload fournisseur conservé.
- signature vérifiée.
- aucune confirmation depuis le navigateur.

## Sécurité

- KYC séparé du profil utilisateur.
- audit des opérations sensibles.
- RBAC/permissions granulaires.
- séparation des tâches financières.
- tokens Live courts et limités.
- secrets fournisseurs chiffrés.

## Scalabilité

- queues pour opérations asynchrones.
- Redis pour cache/pubsub.
- events/domain events.
- API stateless.
- WebSockets via Reverb.
- polling fallback pour les overlays.

---

# 20. Relations principales

```text
User 1──N OrganizationMember N──1 Organization

Organization 1──N Program
Program 1──N Project
Project 1──N Campaign
Organization 1──N Campaign

Campaign 1──N Donation
Donation 1──N Payment

Campaign 1──N Payout

Campaign 1──N Proof
Campaign 1──N Content
Campaign 1──N CampaignInvitation

Campaign 1──N Fundraiser
Campaign 1──N Referral

User 1──0..1 Donor
User 1──0..1 SocialInfluencer
SocialInfluencer 1──N SocialAccount

Campaign 1──N LiveSession
LiveSession 1──N LiveDestination
LiveSession 1──N LiveEvent
LiveSession 1──N LiveStatistic
LiveSession 1──N LiveTracking
LiveSession 1──1 LiveOverlay

LedgerTransaction 1──N LedgerEntry
LedgerEntry N──1 LedgerAccount

Funder 1──N Funding
Funding 1──N Grant
Grant N──1 Program

Project 1──N ImpactMetric
```

---

# 21. Décisions hors périmètre de l'ERD V1

À ne pas surcharger le modèle initial :

- vidéo self-hosted
- broadcaster mobile propriétaire
- TikTok LIVE API non officiellement disponible
- marketplace e-commerce
- POS
- logique Yessal Caisse
- gamification avancée
- moteur publicitaire complet
- CRM complet

Ces fonctionnalités pourront utiliser l'API et les événements Barkeelu sans contaminer le noyau fundraising.

---

# 22. Roadmap de données

### MVP

1. User
2. Organization
3. Program
4. Project
5. Campaign
6. Donor
7. Donation
8. Payment
9. Fee
10. Ledger
11. Payout
12. KYC
13. Proof
14. AuditLog
15. Content
16. API
17. LiveSession + Live Data + Overlay

### V1

- Fundraiser
- Ambassador
- Referral
- SocialInfluencer
- private/targeted collections
- reports
- statistics
- YouTube Adapter

### V1.5

- Restream/Castr adapters
- advanced influencer dashboard
- premium Live orchestration

### V2+

- grants/funding
- impact management
- enterprise API
- white-label
- éventuelle infrastructure vidéo souveraine

---

# 23. Principe directeur

Barkeelu doit rester un **Fundraising & Social Impact Engine**, et non devenir une plateforme vidéo ou un simple clone de GoFundMe.

Le noyau doit donc rester centré sur :

```text
CONFIANCE
   +
FUNDRAISING
   +
TRANSPARENCE
   +
COMMUNAUTÉ
   +
API
   +
IMPACT
```
