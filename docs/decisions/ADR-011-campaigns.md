# ADR-011 — Campaigns : ownership, cycle de vie, visibilité et projections

- **Projet** : Barkeelu.com
- **Statut** : Accepté
- **Date** : 2026-09-13
- **Décisionnaire** : Tech Lead Barkeelu
- **Portée** : Campaigns
- **Références** : ERD V1.2, ADR-001, ADR-009, ADR-010
- **Checkpoint technique** : `c1cd0f7 feat(kyc): ajouter bénéficiaires, représentants et KYC`

## 1. Principes

Une Campaign est distincte :
- de son créateur ;
- de son owner ;
- de son bénéficiaire ;
- du KYC ;
- des Donations/Payments ;
- du Ledger.

Une campagne possède exactement un owner :
- `owner_user_id`, ou
- `owner_organization_id`.

Le créateur est conservé séparément via `created_by_user_id`.
Le bénéficiaire est obligatoire et référence `beneficiaries`.

## 2. Périmètre Mission 05

Mission 05 implémente le cœur Campaign uniquement.

Elle ne crée pas :
- Program ;
- Project ;
- Category ;
- CampaignInvitation ;
- Contact/ContactGroup ;
- Donation ;
- Payment ;
- FeePolicy ;
- Ledger ;
- Payout ;
- Refund ;
- Trust Score ;
- Transparency Score ;
- Barkeelu Live métier.

Les colonnes `program_id`, `project_id` et `category_id` seront ajoutées lorsqu'un domaine correspondant existera réellement.

## 3. Table campaigns

Structure MVP :

```text
id BIGINT PK
public_id UUID UNIQUE
owner_user_id BIGINT NULL FK users
owner_organization_id BIGINT NULL FK organizations
created_by_user_id BIGINT FK users
beneficiary_id BIGINT FK beneficiaries
title VARCHAR
slug VARCHAR UNIQUE
description TEXT
goal_amount BIGINT
currency CHAR(3)
status VARCHAR
fundraising_status VARCHAR
payout_status VARCHAR
visibility VARCHAR
featured BOOLEAN DEFAULT FALSE
published_at TIMESTAMP NULL
start_at TIMESTAMP NULL
end_at TIMESTAMP NULL
closed_at TIMESTAMP NULL
gross_collected_nominal BIGINT DEFAULT 0
refunded_nominal BIGINT DEFAULT 0
net_collected_nominal BIGINT DEFAULT 0
available_for_payout BIGINT DEFAULT 0
reserved_for_payout BIGINT DEFAULT 0
paid_out_amount BIGINT DEFAULT 0
donation_count BIGINT DEFAULT 0
distinct_donor_count BIGINT DEFAULT 0
metadata JSONB NULL
created_at
updated_at
deleted_at NULL
```

## 4. Contraintes PostgreSQL

Exactement un owner :

```text
(owner_user_id IS NOT NULL)::int
+
(owner_organization_id IS NOT NULL)::int
= 1
```

Contraintes :
- `goal_amount > 0`
- toutes les projections `>= 0`
- `end_at IS NULL OR start_at IS NULL OR end_at > start_at`

Aucun FLOAT/DOUBLE.

## 5. Currency

Schéma : code ISO 4217 sur 3 caractères.

Mission 05 accepte uniquement :

```text
XOF
```

au niveau API MVP.

## 6. CampaignStatus

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

`GOAL_REACHED` n'est jamais un statut principal.
Il est dérivé de :

```text
net_collected_nominal >= goal_amount
```

## 7. FundraisingStatus

```text
NOT_STARTED
OPEN
PAUSED
CLOSED
```

Valeur initiale :

```text
NOT_STARTED
```

## 8. PayoutStatus

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

Valeur initiale :

```text
NOT_ELIGIBLE
```

Mission 05 ne fournit aucun endpoint pour changer ce statut.

## 9. Visibility

```text
PUBLIC
UNLISTED
PRIVATE
TARGETED
```

Mission 05 supporte pleinement PUBLIC, UNLISTED, PRIVATE.

`TARGETED` existe dans l'enum et le schéma mais sa création via API est refusée tant que CampaignInvitation/ContactGroup ne sont pas implémentés.

Règles :
- PUBLIC : listable et visible publiquement si PUBLISHED
- UNLISTED : non listé, mais visible par URL directe si PUBLISHED
- PRIVATE : jamais visible publiquement
- TARGETED : réservé au futur module invitation

## 10. Ownership

### Owner User
Par défaut, owner = utilisateur authentifié.
Un utilisateur ordinaire ne crée pas une campagne pour un autre User.

### Owner Organization
L'utilisateur authentifié doit avoir un membership ACTIVE et être OWNER ou ADMIN.
MEMBER ne suffit pas.

## 11. Beneficiary

Chaque Campaign référence exactement un Beneficiary existant.

L'utilisateur doit être autorisé à consulter/utiliser ce Beneficiary selon `BeneficiaryPolicy`.

Owner et Beneficiary peuvent être différents.

Le changement de beneficiary est hors Mission 05.

## 12. Slug

Slug généré côté serveur, collision déterministe, stable après modification du titre.

## 13. Création

Defaults serveur :

```text
status = DRAFT
fundraising_status = NOT_STARTED
payout_status = NOT_ELIGIBLE
featured = false
projections = 0
```

Le client ne peut pas fournir ces champs.

## 14. Workflow MVP

Transitions :

```text
DRAFT -> SUBMITTED
SUBMITTED -> UNDER_REVIEW
UNDER_REVIEW -> PUBLISHED
UNDER_REVIEW -> REJECTED
PUBLISHED -> PAUSED
PAUSED -> PUBLISHED
PUBLISHED/PAUSED -> ENDED
DRAFT/SUBMITTED/REJECTED -> CANCELLED
ENDED -> CLOSED
```

Transitions invalides refusées.

### Soumission
Owner autorisé :
```text
DRAFT -> SUBMITTED
```

### Revue
Requiert :
```text
moderation.manage
```

Actions :
```text
SUBMITTED -> UNDER_REVIEW
UNDER_REVIEW -> PUBLISHED
UNDER_REVIEW -> REJECTED
```

### Publication
À PUBLISHED :
- `published_at` renseigné une seule fois ;
- fundraising = OPEN si start_at null ou <= now ;
- sinon NOT_STARTED.

### Pause
PUBLISHED -> PAUSED :
```text
fundraising_status = PAUSED
```

PAUSED -> PUBLISHED :
```text
fundraising_status = OPEN
```

### Fin/Fermeture
À ENDED/CLOSED :
```text
fundraising_status = CLOSED
```

`closed_at` renseigné lors de CLOSED.

## 15. Policies

Créer `CampaignPolicy`.

- create : utilisateur authentifié
- view : owner user, créateur, OWNER/ADMIN actifs de l'organization owner, `moderation.manage`, ou visibilité publique autorisée
- update : owner user ou OWNER/ADMIN actifs selon états autorisés
- submit : même autorité owner
- review : `moderation.manage` uniquement

## 16. API Mission 05

### Public

```text
GET /api/v1/campaigns
GET /api/v1/campaigns/{slug}
```

Listing :
```text
PUBLISHED + PUBLIC uniquement
```

Show public :
```text
PUBLISHED + PUBLIC/UNLISTED
```

PRIVATE/TARGETED ne fuient aucune donnée.

### Authentifiée

```text
GET  /api/v1/me/campaigns
POST /api/v1/campaigns
PATCH /api/v1/campaigns/{campaign:public_id}
POST /api/v1/campaigns/{campaign:public_id}/submit
POST /api/v1/campaigns/{campaign:public_id}/review
```

Si le binding public/auth devient ambigu, utiliser des URI distinctes et explicites.

## 17. Resource API

Exposer au minimum :
- public_id
- title
- slug
- description
- goal_amount
- currency
- status
- fundraising_status
- visibility
- goal_reached
- published_at
- start_at
- end_at
- projections publiques non sensibles
- created_at
- updated_at

Ne pas exposer les IDs internes.

## 18. Projections

Présentes dès Mission 05, initialisées à zéro.

Elles ne sont jamais source comptable.
Aucun endpoint ne permet de les modifier.

Plus tard :

```text
Donation/Ledger -> événements/outbox -> projections
```

## 19. Soft delete

SoftDeletes autorisé.
Aucun DELETE public Mission 05.

## 20. Tests obligatoires

Tester :
- owner XOR zéro/double
- goal_amount > 0
- projections non négatives
- dates cohérentes
- UUID/slug uniques
- FKs
- création owner user
- création organization OWNER/ADMIN
- MEMBER refusé
- beneficiary autorisé
- XOF accepté
- autre devise refusée
- TARGETED refusé
- PUBLIC/UNLISTED/PRIVATE
- isolation
- workflow
- moderation.manage
- published_at
- fundraising status
- goal_reached dérivé
- projections non modifiables
- régression PostgreSQL/Sanctum/Organizations/RBAC/KYC/Redis/Reverb

## 21. Décision finale

```text
Campaign Owner          = User XOR Organization
Creator                 = séparé et auditable
Beneficiary             = obligatoire et distinct
Goal money              = BIGINT
Currency MVP            = XOF
Status                  = cycle de vie
FundraisingStatus       = acceptation dons
PayoutStatus            = éligibilité/retrait futur
Visibility              = PUBLIC/UNLISTED/PRIVATE (+ TARGETED réservé)
GOAL_REACHED            = dérivé
Financial projections   = présentes mais non autoritatives
Payments/Ledger         = hors Mission 05
```
