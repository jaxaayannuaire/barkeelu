# ADR-012 — Financial Foundation : Ledger, Fees et Transactional Outbox

- **Projet** : Barkeelu.com
- **Statut** : Accepté
- **Date** : 2026-09-13
- **Décisionnaire** : Tech Lead Barkeelu
- **Portée** : Financial Foundation / Ledger / Fees / Outbox
- **Références** : ADR-002, ADR-003, ADR-004, ADR-005, ADR-006, ADR-011
- **Checkpoint technique** : `4391fdc feat(campaigns): ajouter le cœur des campagnes`

---

## 1. Découpage du domaine financier

Le domaine financier est volontairement découpé :

```text
Mission 06A
Ledger + Fee Policies + Transactional Outbox

Mission 06B
Donations + Payments + Provider Accounts + Webhooks

Mission 06C
Refunds + Payouts + Reconciliation
```

Mission 06A ne contacte aucun fournisseur de paiement et ne crée aucun flux Wave.

---

## 2. PostgreSQL reste la source de vérité

Les écritures financières sont persistées dans PostgreSQL.

Redis, queues et Reverb restent :

```text
cache
transport
temps réel
```

Ils ne déterminent jamais un solde financier.

---

## 3. Montants

Tous les montants financiers sont stockés en entier :

```text
BIGINT
```

Aucun :

```text
FLOAT
DOUBLE
DECIMAL pour montant nominal métier
```

dans le ledger V1.

Devise MVP :

```text
XOF
```

Les tables conservent néanmoins une colonne devise explicite.

---

## 4. Plan de comptes technique

Comptes initiaux :

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

Le ledger technique ne remplace pas la comptabilité légale/fiscale de Jaxaay Group.

---

## 5. ledger_accounts

Table :

```text
ledger_accounts
```

Champs :

```text
id BIGINT PK
public_id UUID UNIQUE
code VARCHAR UNIQUE
name VARCHAR
account_type VARCHAR
currency CHAR(3)
active BOOLEAN
created_at
updated_at
```

Types :

```text
ASSET
LIABILITY
REVENUE
EXPENSE
```

Mission 06A seed les comptes XOF définis dans le plan de comptes initial.

Les comptes système ne sont pas supprimés physiquement.

---

## 6. ledger_transactions

Table :

```text
ledger_transactions
```

Champs :

```text
id BIGINT PK
public_id UUID UNIQUE
business_key VARCHAR UNIQUE
content_hash CHAR(64)
currency CHAR(3)
status VARCHAR
source_type VARCHAR NULL
source_reference VARCHAR NULL
reversal_of_transaction_id BIGINT NULL FK ledger_transactions
description VARCHAR NULL
posted_at TIMESTAMP NULL
created_at
updated_at
```

Statuts :

```text
DRAFT
POSTED
```

Un reversal est une nouvelle transaction POSTED reliée à l'original.

La transaction originale n'est pas modifiée.

### Idempotence

`business_key` est unique.

Pour une même `business_key` :

```text
même content_hash
→ retourner/reconnaître la transaction déjà créée

content_hash différent
→ conflit explicite
```

Aucun remplacement silencieux.

---

## 7. ledger_entries

Table :

```text
ledger_entries
```

Champs :

```text
id BIGINT PK
ledger_transaction_id BIGINT FK
ledger_account_id BIGINT FK
campaign_id BIGINT NULL FK campaigns
direction VARCHAR
amount BIGINT
created_at
```

Directions :

```text
DEBIT
CREDIT
```

Contraintes :

```text
amount > 0
```

La devise vient de la transaction et doit être cohérente avec le compte.

`campaign_id` est une dimension analytique/subledger.

Exemple :

```text
CAMPAIGN_PAYABLE + campaign_id
```

permet de reconstruire le passif d'une campagne sans créer un compte comptable physique par campagne.

---

## 8. Invariants double entrée

Pour toute transaction `POSTED` :

```text
minimum 2 entries
sum(DEBIT.amount) = sum(CREDIT.amount)
currency transaction = currency comptes utilisés
```

Ces invariants doivent être protégés par PostgreSQL.

Un simple CHECK par ligne est insuffisant.

---

## 9. Mécanisme PostgreSQL de posting

Mission 06A utilise :

```text
fonction PostgreSQL de validation
+
constraint trigger DEFERRABLE INITIALLY DEFERRED
```

ou mécanisme PostgreSQL équivalent offrant les mêmes garanties.

Le contrôle doit s'exécuter au plus tard au COMMIT.

Il doit couvrir :
- création des entries ;
- passage transaction DRAFT -> POSTED ;
- tentative de modification d'une transaction POSTED.

Le mécanisme doit être testé sur PostgreSQL réel.

---

## 10. Immutabilité

Après `POSTED` :

```text
ledger_transactions
ledger_entries
```

sont immuables.

Interdit :

```text
UPDATE
DELETE
```

sur les données financières postées.

Toute correction :

```text
nouvelle transaction de reversal
```

Le reversal inverse les lignes originales :

```text
DEBIT <-> CREDIT
```

avec les mêmes montants et dimensions.

---

## 11. Service de posting

Créer un service unique, par exemple :

```text
LedgerPostingService
```

Il reçoit :

```text
business_key
currency
source metadata minimale
description
entries
```

Le service :

1. calcule un `content_hash` déterministe ;
2. vérifie l'idempotence ;
3. ouvre une transaction SQL ;
4. crée le ledger transaction DRAFT ;
5. crée les entries ;
6. passe à POSTED ;
7. écrit l'outbox event dans la même transaction ;
8. commit.

Aucun controller ne crée directement des entries.

---

## 12. Reversal

Créer un service explicite :

```text
LedgerReversalService
```

Il :

- refuse une transaction non POSTED ;
- ne modifie jamais l'original ;
- génère une business key dédiée ;
- inverse les directions ;
- conserve account, amount et campaign dimension ;
- crée sa propre outbox event.

Un seul reversal automatique du même original doit être garanti par contrainte/règle explicite.

---

## 13. Fee Policies

Table :

```text
fee_policies
```

Champs :

```text
id
public_id UUID UNIQUE
code VARCHAR UNIQUE
fee_type VARCHAR
rate_bps INT NULL
fixed_amount BIGINT NULL
currency CHAR(3) NULL
effective_from TIMESTAMP
effective_to TIMESTAMP NULL
active BOOLEAN
created_at
updated_at
```

Types initiaux :

```text
PLATFORM_FEE
PAYOUT_PROVISION
PAYMENT_PROVIDER_FEE
PAYOUT_PROVIDER_FEE
```

Contraintes :

```text
rate_bps >= 0
fixed_amount >= 0
au moins rate_bps ou fixed_amount
effective_to NULL ou > effective_from
```

### Politique plateforme

Décision commerciale validée :

```text
PLATFORM_FEE = 400 bps = 4 %
```

Elle peut être seedée active.

### Provision payout

Hypothèse de travail :

```text
PAYOUT_PROVISION = 100 bps = 1 %
```

Elle doit être seedée comme politique de travail **inactive/non contractuellement confirmée** jusqu'à validation fournisseur/comptable.

Mission 06A ne la traite pas comme revenu Barkeelu.

---

## 14. Applied Fees

Table :

```text
applied_fees
```

Champs :

```text
id
public_id UUID UNIQUE
fee_policy_id BIGINT NULL FK
source_type VARCHAR
source_reference VARCHAR
fee_type VARCHAR
rate_bps INT NULL
fixed_amount BIGINT NULL
basis_amount BIGINT
amount BIGINT
currency CHAR(3)
payer VARCHAR
beneficiary VARCHAR
created_at
```

Une AppliedFee est un snapshot immuable.

Elle ne dépend pas d'une politique future pour être interprétée.

Mission 06A crée le modèle/table mais ne l'applique encore à aucune Donation.

---

## 15. Calcul des fees

Service pur/testable :

```text
FeeCalculator
```

Calcul en basis points :

```text
amount = round_half_up(basis_amount * rate_bps / 10000)
```

Pour XOF entier, la règle d'arrondi doit être explicite et testée.

Exemple plateforme :

```text
10 000 × 400 / 10 000 = 400
```

Aucun floating point.

Utiliser arithmétique entière.

---

## 16. Transactional Outbox

Table :

```text
outbox_events
```

Champs :

```text
id BIGINT PK
public_id UUID UNIQUE
event_type VARCHAR
aggregate_type VARCHAR
aggregate_reference VARCHAR
dedupe_key VARCHAR UNIQUE
payload JSONB
occurred_at TIMESTAMP
available_at TIMESTAMP
published_at TIMESTAMP NULL
attempts INT DEFAULT 0
last_error TEXT NULL
created_at
updated_at
```

Le payload ne contient aucun secret.

---

## 17. Atomicité ledger + outbox

Pour tout posting/reversal Mission 06A :

```text
DB transaction
├── ledger transaction
├── ledger entries
└── outbox event
```

Si l'outbox insert échoue :

```text
rollback ledger
```

Si le ledger échoue :

```text
aucun outbox event
```

Test obligatoire.

---

## 18. Outbox worker

Créer un mécanisme minimal d'outbox processing :

```text
pending
→ claim
→ dispatch Laravel event / queue
→ mark published
```

Contraintes :
- concurrence sûre ;
- préférer `FOR UPDATE SKIP LOCKED` ou mécanisme équivalent PostgreSQL ;
- ne jamais publier avant commit ;
- retry possible ;
- `published_at` uniquement après succès ;
- `attempts` incrémenté ;
- `last_error` expurgé.

Mission 06A ne doit pas envoyer de notification métier à un utilisateur.

---

## 19. Events Mission 06A

Événements techniques autorisés :

```text
ledger.transaction.posted
ledger.transaction.reversed
```

Payload minimal :

```text
ledger_transaction_public_id
business_key
currency
```

Ne pas inclure de données sensibles inutiles.

---

## 20. API

Mission 06A n'expose **aucun endpoint public de posting ledger**.

Le ledger est un service interne.

Option autorisée :

```text
aucune nouvelle route API financière
```

Les tests appellent les services directement.

Cette décision réduit la surface d'attaque avant Donation/Payment.

---

## 21. Campaign projections

Mission 06A ne modifie pas encore automatiquement les projections Campaign.

La règle reste :

```text
ledger = autoritatif
campaign projections = dérivées
```

La synchronisation arrivera avec Donation/Payment et Outbox dans Mission 06B.

---

## 22. Concurrence

Tests PostgreSQL requis :

- même business_key concurrente ;
- même business_key + même hash ;
- même business_key + hash différent ;
- transaction déséquilibrée rejetée au commit ;
- transaction avec une seule ligne rejetée ;
- modification entry POSTED rejetée ;
- suppression entry POSTED rejetée ;
- update transaction POSTED rejeté ;
- reversal unique ;
- outbox atomique.

---

## 23. Hors périmètre 06A

Ne pas créer :

```text
Donor
Donation
Payment
ProviderAccount
WebhookEvent
Wave adapter
Refund
Payout
PayoutApproval
ReconciliationRun
ReconciliationItem
Campaign projection updater
```

Ces domaines sont 06B/06C.

---

## 24. Décision finale

```text
Money storage       = BIGINT
Ledger              = double entrée
Posted ledger       = immutable
Correction          = reversal
Balance enforcement = PostgreSQL deferred validation
Idempotence ledger  = business_key + content_hash
Campaign subledger  = ledger_entries.campaign_id
Fee policy          = versionnée
Applied fee         = snapshot immuable
Platform fee        = 400 bps active
Payout provision    = 100 bps working/inactive
Outbox              = même transaction que ledger
Redis               = jamais source de vérité
Public ledger API   = NON en 06A
```
