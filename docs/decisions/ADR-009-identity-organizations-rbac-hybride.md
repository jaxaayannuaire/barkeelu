# ADR-009 — Identity, Organizations et RBAC hybride

- **Projet** : Barkeelu.com
- **Statut** : Accepté
- **Date** : 2026-09-13
- **Décisionnaire** : Tech Lead Barkeelu
- **Portée** : Identity / Organizations / Platform RBAC
- **Références** : ERD V1.2 consolidé, ADR-001, ADR-005, ADR-008
- **Checkpoint technique** : `4d281e5 feat(infra): configurer Redis, les queues et Reverb`

## 1. Décision

Barkeelu adopte un modèle hybride :

1. **Spatie Laravel Permission v8** pour les rôles et permissions globaux de plateforme.
2. **Tables métier Barkeelu dédiées** pour les relations contextuelles, notamment `organization_members`.
3. **Policies Laravel** pour combiner permissions globales, appartenance à l'organisation et règles métier.
4. **Un seul guard de permission : `web`**.
5. Sanctum reste le mécanisme d'authentification API.
6. **Spatie Teams n'est pas utilisé** pour représenter les organisations Barkeelu.

## 2. Principe

> Un rôle Spatie décrit une capacité globale sur la plateforme. Une relation Barkeelu décrit l'autorité sur une ressource métier précise.

Ainsi, `platform_admin`, `moderator`, `compliance_officer`, `finance_operator` et `finance_approver` sont des rôles globaux.

En revanche, `organization owner`, `organization admin`, `organization member`, `campaign owner`, `beneficiary representative`, `fundraiser` et `ambassador` restent des relations métier contextuelles.

## 3. Rôles globaux initiaux

```text
platform_admin
moderator
compliance_officer
finance_operator
finance_approver
```

Aucun rôle `super_admin` avec bypass implicite n'est introduit à ce stade.

## 4. Permissions globales initiales

```text
platform.access
organizations.manage_all
moderation.manage
compliance.manage
finance.operate
finance.approve
```

Mapping :

```text
platform_admin
  - platform.access
  - organizations.manage_all
  - moderation.manage
  - compliance.manage

moderator
  - moderation.manage

compliance_officer
  - compliance.manage

finance_operator
  - finance.operate

finance_approver
  - finance.approve
```

`platform_admin` ne reçoit pas automatiquement `finance.operate` ni `finance.approve`.

La séparation des tâches financières est obligatoire. Une future règle métier interdira aussi à un utilisateur d'approuver son propre payout, même s'il détient plusieurs permissions.

## 5. Guard

Le guard Spatie de référence est :

```text
web
```

Sanctum authentifie les requêtes API, puis Laravel Gate / Policies évaluent les permissions. Il n'y a pas de duplication des rôles sous un guard `sanctum`.

## 6. Organization

Table :

```text
organizations
```

Champs initiaux :

```text
id                    BIGINT PK
public_id             UUID UNIQUE
name                  VARCHAR
slug                  VARCHAR UNIQUE
type                  VARCHAR
status                VARCHAR
created_by_user_id    BIGINT FK users
created_at
updated_at
```

Types :

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

Statuts :

```text
ACTIVE
SUSPENDED
ARCHIVED
```

Le statut d'organisation ne représente pas le KYC. Le KYC reste un domaine séparé.

Pas de suppression physique via l'API métier ; l'archivage est préféré.

## 7. Identifiant public

L'identifiant interne reste un `BIGINT`.

L'API expose prioritairement :

```text
public_id UUID
```

Objectifs : ne pas exposer les séquences internes, garder un identifiant public stable et conserver des FKs performantes.

## 8. Membership organisationnelle

Table :

```text
organization_members
```

Champs initiaux :

```text
id
organization_id
user_id
membership_role
status
joined_at
left_at nullable
created_at
updated_at
```

Contrainte :

```text
UNIQUE (organization_id, user_id)
```

Rôles contextuels MVP :

```text
OWNER
ADMIN
MEMBER
```

Statuts MVP :

```text
ACTIVE
SUSPENDED
LEFT
```

Ces rôles contextuels ne sont jamais convertis automatiquement en rôles Spatie.

## 9. Création d'une organisation

La création doit être atomique :

1. création de l'organisation ;
2. `created_by_user_id` = utilisateur authentifié ;
3. création dans la même transaction du membership propriétaire ;
4. `membership_role = OWNER` ;
5. `status = ACTIVE` ;
6. `joined_at = now()`.

Une organisation ne doit jamais être créée sans son OWNER initial.

## 10. Policies

### view

Autorisé si :
- membership `ACTIVE` ;
- ou permission globale `organizations.manage_all`.

### update

Autorisé si :
- membership `ACTIVE` + rôle `OWNER` ou `ADMIN` ;
- ou permission globale `organizations.manage_all`.

### archive

Autorisé si :
- membership `ACTIVE` + rôle `OWNER` ;
- ou permission globale `organizations.manage_all`.

Aucune suppression physique.

## 11. API minimale Mission 03

Sous `auth:sanctum` :

```text
GET  /api/v1/organizations
POST /api/v1/organizations
GET  /api/v1/organizations/{organization:public_id}
PATCH /api/v1/organizations/{organization:public_id}
```

`GET /organizations` retourne par défaut seulement les organisations auxquelles l'utilisateur appartient activement.

`POST /organizations` crée l'organisation et l'OWNER initial dans une transaction.

`GET` et `PATCH` passent par `OrganizationPolicy`.

## 12. Slug

Le slug est généré côté serveur depuis le nom.

Les collisions doivent être résolues de manière déterministe :

```text
association-x
association-x-2
association-x-3
```

Préférence MVP : le slug reste stable après création même si le nom change.

## 13. Seeders

Créer des seeders idempotents pour :
- rôles ;
- permissions ;
- mapping rôles/permissions.

Ne jamais :
- créer un compte administrateur avec mot de passe par défaut ;
- attribuer automatiquement `platform_admin` au premier utilisateur.

## 14. Spatie Teams

`Teams` reste désactivé.

Une organisation Barkeelu est une entité métier riche : type, cycle de vie, membres, KYC, programmes, projets, campagnes, bénéficiaires, audit. Elle ne doit pas être réduite à un scope de permission.

## 15. Tests obligatoires

Tester :
- rôles/permissions seedés ;
- mapping exact ;
- `platform_admin` sans permissions financières implicites ;
- création Organization authentifiée ;
- UUID public ;
- OWNER initial atomique ;
- listing limité aux memberships actifs ;
- isolation entre organisations ;
- MEMBER lecture seule ;
- OWNER/ADMIN modification ;
- accès global via `organizations.manage_all` ;
- contraintes uniques et FKs ;
- PostgreSQL réel ;
- régression Sanctum/API/Redis/Reverb.

## 16. Hors périmètre Mission 03

Ne pas créer :

```text
Campaign
Beneficiary
BeneficiaryRepresentative
KYCProfile
KYCDocument
Program
Project
Donation
Payment
Wave
Webhook métier
Ledger
Refund
Payout
Reconciliation
Fundraiser
Ambassador
Barkeelu Live métier
```

## 17. Décision finale

```text
RBAC global          = Spatie Laravel Permission v8
Organizations        = domaine Barkeelu
Memberships          = table Barkeelu
Authorization métier = Laravel Policies
Spatie Teams         = NON
Guard permissions    = web
Sanctum              = authentification API
```
