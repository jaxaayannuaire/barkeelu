# ADR-010 — Beneficiaries, Representatives et KYC

- **Projet** : Barkeelu.com
- **Statut** : Accepté
- **Date** : 2026-09-13
- **Décisionnaire** : Tech Lead Barkeelu
- **Portée** : Beneficiaries / Representatives / KYC
- **Références** : ADR-001, ADR-008, ADR-009
- **Checkpoint technique** : `032bb7b feat(identity): ajouter organizations et le RBAC plateforme`

## 1. Principes

Barkeelu distingue strictement :

```text
Campaign Owner
Beneficiary
Beneficiary Representative
KYC Subject
Organization Membership
```

Ces concepts ne sont pas interchangeables.

Un bénéficiaire peut exister sans compte Barkeelu.

Le KYC peut porter sur exactement un sujet parmi :

```text
User
Organization
Beneficiary
```

Les documents KYC sont privés et séparés du profil KYC.

## 2. Beneficiary

Table :

```text
beneficiaries
```

Champs initiaux :

```text
id BIGINT PK
public_id UUID UNIQUE
type VARCHAR
display_name VARCHAR
status VARCHAR
linked_user_id BIGINT NULL FK users
linked_organization_id BIGINT NULL FK organizations
created_by_user_id BIGINT FK users
created_at
updated_at
```

Types MVP :

```text
INDIVIDUAL
ORGANIZATION
COMMUNITY
OTHER
```

Statuts MVP :

```text
ACTIVE
SUSPENDED
ARCHIVED
```

### Contraintes

Un bénéficiaire peut être lié à :
- aucun compte ;
- un User ;
- une Organization.

Mais pas simultanément à un User et une Organization.

Contrainte XOR :

```text
NOT (linked_user_id IS NOT NULL AND linked_organization_id IS NOT NULL)
```

`display_name` reste une donnée métier propre au bénéficiaire même lorsqu'il est lié à un compte.

Pas de suppression physique via l'API métier.

## 3. Beneficiary Representative

Table :

```text
beneficiary_representatives
```

Champs initiaux :

```text
id BIGINT PK
beneficiary_id BIGINT FK
representative_user_id BIGINT FK users
status VARCHAR
valid_from TIMESTAMP
valid_until TIMESTAMP NULL
created_by_user_id BIGINT FK users
ended_by_user_id BIGINT NULL FK users
created_at
updated_at
```

Statuts MVP :

```text
ACTIVE
ENDED
REVOKED
```

### Règles

- l'historique est conservé ;
- aucun UPDATE destructif ne doit effacer l'historique d'une représentation ;
- un représentant actif doit avoir `valid_from <= now()` ;
- `valid_until` est nullable ;
- si `valid_until` existe, il doit être postérieur à `valid_from`.

Une relation de représentation n'accorde pas automatiquement :
- permission financière ;
- droit d'approuver un payout ;
- rôle Spatie global.

## 4. KYC Profile

Table :

```text
kyc_profiles
```

Champs initiaux :

```text
id BIGINT PK
public_id UUID UNIQUE
user_id BIGINT NULL FK users
organization_id BIGINT NULL FK organizations
beneficiary_id BIGINT NULL FK beneficiaries
status VARCHAR
risk_level VARCHAR
submitted_at TIMESTAMP NULL
reviewed_at TIMESTAMP NULL
reviewed_by_user_id BIGINT NULL FK users
rejection_reason TEXT NULL
created_at
updated_at
```

### XOR obligatoire

Chaque profil KYC porte sur exactement un sujet :

```text
(user_id IS NOT NULL)::int
+
(organization_id IS NOT NULL)::int
+
(beneficiary_id IS NOT NULL)::int
= 1
```

Cette règle doit être protégée en base PostgreSQL par une contrainte CHECK.

### Unicité

Un seul profil KYC courant par sujet dans le MVP :

```text
UNIQUE user_id WHERE user_id IS NOT NULL
UNIQUE organization_id WHERE organization_id IS NOT NULL
UNIQUE beneficiary_id WHERE beneficiary_id IS NOT NULL
```

Si Laravel Schema ne couvre pas proprement les index partiels PostgreSQL, utiliser une migration SQL explicite et testée.

## 5. Statuts KYC

Statuts MVP :

```text
DRAFT
SUBMITTED
UNDER_REVIEW
VERIFIED
REJECTED
EXPIRED
SUSPENDED
```

Le statut KYC est distinct de :
- OrganizationStatus ;
- BeneficiaryStatus ;
- Verification Status futur ;
- Trust Score futur ;
- Transparency Score futur.

Aucune logique de score de confiance n'est incluse dans Mission 04.

## 6. Risk Level

Valeurs initiales :

```text
UNKNOWN
LOW
MEDIUM
HIGH
```

Le niveau de risque ne vaut jamais décision réglementaire automatique.

Mission 04 ne doit pas créer de moteur AML automatisé.

## 7. KYC Documents

Table :

```text
kyc_documents
```

Champs initiaux :

```text
id BIGINT PK
public_id UUID UNIQUE
kyc_profile_id BIGINT FK
type VARCHAR
status VARCHAR
storage_disk VARCHAR
object_key TEXT
sha256 CHAR(64)
mime_type VARCHAR
size_bytes BIGINT
issued_at DATE NULL
expires_at DATE NULL
metadata JSONB NULL
uploaded_by_user_id BIGINT FK users
reviewed_by_user_id BIGINT NULL FK users
reviewed_at TIMESTAMP NULL
created_at
updated_at
```

Types initiaux :

```text
IDENTITY_DOCUMENT
PASSPORT
REGISTRATION_DOCUMENT
PROOF_OF_ADDRESS
REPRESENTATION_PROOF
OTHER
```

Statuts :

```text
UPLOADED
ACCEPTED
REJECTED
EXPIRED
```

## 8. Stockage privé obligatoire

Les documents KYC :

```text
NE DOIVENT JAMAIS
```

être stockés sur un disque public ou exposés via URL publique permanente.

Mission 04 doit préparer un disque privé configurable, mais ne doit pas imposer encore OVH Object Storage en production.

Le stockage local de test peut utiliser un disk privé Laravel dédié.

L'API ne retourne jamais `object_key` directement.

Un téléchargement futur devra passer par une autorisation explicite et une URL temporaire ou un contrôleur sécurisé.

## 9. Hash

Chaque document conserve :

```text
sha256
```

calculé sur le contenu reçu.

Objectifs :
- intégrité ;
- détection de doublons éventuelle ;
- audit.

Ne pas utiliser le hash comme secret.

## 10. Accès KYC

Rôles globaux existants :

```text
compliance_officer
platform_admin
```

Permissions :

```text
compliance.manage
```

Mission 04 utilise `compliance.manage` pour les opérations de revue KYC.

`platform_admin` possède déjà cette permission selon ADR-009.

Les utilisateurs ordinaires peuvent consulter leur propre état KYC ou celui des ressources qu'ils contrôlent seulement si explicitement prévu par Policy.

Les documents eux-mêmes ont une politique plus restrictive que le profil KYC.

## 11. Policies

Créer au minimum :

```text
BeneficiaryPolicy
KycProfilePolicy
KycDocumentPolicy
```

Principes :

### Beneficiary
- créateur ou relation métier autorisée ;
- ou permission globale pertinente.

### KYC Profile
- sujet lui-même pour lecture limitée ;
- membres Organization OWNER/ADMIN actifs pour leur organisation ;
- représentant actif pour un Beneficiary si explicitement autorisé ;
- `compliance.manage` pour revue.

### KYC Document
- accès plus restrictif ;
- téléchargement jamais public ;
- `compliance.manage` pour revue ;
- propriétaire/sujet peut voir les métadonnées autorisées, pas les chemins internes.

## 12. API minimale Mission 04

Sous `auth:sanctum`.

Beneficiaries :

```text
GET  /api/v1/beneficiaries
POST /api/v1/beneficiaries
GET  /api/v1/beneficiaries/{beneficiary:public_id}
PATCH /api/v1/beneficiaries/{beneficiary:public_id}
```

Representatives :

```text
POST /api/v1/beneficiaries/{beneficiary:public_id}/representatives
GET  /api/v1/beneficiaries/{beneficiary:public_id}/representatives
```

Pas de DELETE destructif ; fin/révocation par changement d'état contrôlé si implémenté.

KYC :

```text
GET  /api/v1/kyc/profiles/{kycProfile:public_id}
POST /api/v1/kyc/profiles
POST /api/v1/kyc/profiles/{kycProfile:public_id}/documents
```

La revue KYC peut être incluse seulement si le périmètre reste maîtrisé :

```text
POST /api/v1/kyc/profiles/{kycProfile:public_id}/submit
POST /api/v1/kyc/profiles/{kycProfile:public_id}/review
```

sinon elle doit être séparée en sous-mission 04B.

## 13. Audit et logs

Ne jamais écrire dans logs/audit :
- contenu du document ;
- object_key sensible complet si évitable ;
- token d'accès ;
- pièce d'identité complète ;
- payload binaire.

Les actions sensibles doivent être auditables sans recopier le document.

## 14. Hors périmètre

Mission 04 ne doit pas créer :

```text
Campaign
Donation
Payment
Wave
Ledger
Refund
Payout
Reconciliation
AML engine
Trust Score
Transparency Score
Face recognition
OCR
Object Storage OVH production integration
```

## 15. Tests obligatoires

Tester :
- XOR KYC sujet en base ;
- unicité KYC par sujet ;
- Beneficiary sans compte ;
- Beneficiary lié User ;
- Beneficiary lié Organization ;
- interdiction double lien User + Organization ;
- historique representatives ;
- validité temporelle ;
- stockage document privé ;
- hash SHA-256 ;
- absence d'object_key dans API ;
- Policies ;
- `compliance.manage` ;
- PostgreSQL réel ;
- régression Organizations/RBAC/Redis/Reverb.

## 16. Décision finale

```text
Beneficiary             = entité métier dédiée
Beneficiary account     = optionnel
Representative          = relation historisée
KYC Profile             = exactement 1 sujet
KYC Document            = entité privée séparée
KYC status              != Organization/Beneficiary status
Trust/Transparency      = hors Mission 04
Storage production      = abstrait, privé, fournisseur non figé
```
