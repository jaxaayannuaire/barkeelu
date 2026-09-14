# ADR-014 — Refunds, Payouts et Reconciliation

- **Projet** : Barkeelu.com
- **Statut** : Proposé pour validation Tech Lead
- **Date** : 2026-09-14
- **Checkpoint de départ** : `c354be1d9a1388a60cad38357a5b2489faa1941e`
- **Branche** : `develop`
- **Mission cible** : Codex 06C

## 1. Contexte

Les missions précédentes ont livré :

- 06A : ledger technique interne double entrée, frais, Outbox transactionnelle ;
- 06B : Donations, Payments, Provider Accounts, Webhooks, idempotence et double succès.

La Mission 06C introduit les remboursements, les sorties de fonds vers bénéficiaires et la réconciliation financière.

Cette mission doit préserver les invariants financiers déjà validés et ne doit jamais contourner le ledger.

## 2. Décision générale

Les domaines sont séparés :

```text
Payment
  └── 1:N Refund

Campaign / Beneficiary
  └── 1:N Payout

ProviderAccount
  └── ReconciliationRun
        └── ReconciliationItem
```

Le système distingue :

```text
intention de remboursement
≠ exécution fournisseur
≠ écriture ledger
≠ rapprochement
```

et :

```text
demande de payout
≠ réservation
≠ approbation
≠ exécution fournisseur
≠ settlement
```

## 3. Refund

### 3.1 Rôle

Un `Refund` représente une demande de remboursement liée à un Payment.

Un Payment peut avoir plusieurs refunds partiels.

Aucun remboursement ne doit dépasser le montant remboursable restant.

### 3.2 Invariant financier

À tout instant :

```text
reserved_refund_amount + executed_refund_amount <= refundable_amount
```

La vérification doit être atomique côté PostgreSQL/service transactionnel.

### 3.3 Champs minimaux

- `id`
- `public_id`
- `payment_id`
- `provider_account_id`
- `amount`
- `currency`
- `status`
- `idempotency_key`
- `provider_refund_id` nullable
- `provider_reference` nullable
- `requested_by_user_id`
- `approved_by_user_id` nullable
- `requested_at`
- `approved_at` nullable
- `processed_at` nullable
- `last_error` nullable
- timestamps

### 3.4 Statuts proposés

```text
REQUESTED
APPROVED
PROCESSING
SUCCEEDED
FAILED
UNKNOWN
CANCELLED
```

Un timeout ou état ambigu doit produire `UNKNOWN`, jamais `FAILED` automatiquement.

## 4. Payout

### 4.1 Rôle

Un `Payout` représente une sortie de fonds au bénéfice d’une campagne/bénéficiaire.

Le montant doit être réservé avant tout appel provider.

### 4.2 Réservation

Avant provider call :

```text
available_for_payout
→ reserved_for_payout
```

La réservation doit être transactionnelle et empêcher toute double consommation concurrente.

### 4.3 Champs minimaux

- `id`
- `public_id`
- `campaign_id`
- `beneficiary_id`
- `provider_account_id`
- `amount`
- `currency`
- `status`
- `idempotency_key`
- `destination_snapshot`
- `requested_by_user_id`
- `approved_by_user_id` nullable
- `provider_payout_id` nullable
- `provider_reference` nullable
- `requested_at`
- `approved_at` nullable
- `processed_at` nullable
- `last_error` nullable
- timestamps

### 4.4 Statuts proposés

```text
REQUESTED
PENDING_APPROVAL
APPROVED
RESERVED
PROCESSING
SUCCEEDED
FAILED
UNKNOWN
CANCELLED
BLOCKED
```

## 5. Séparation des rôles

Règles obligatoires :

- `finance_operator` peut préparer/demander ;
- `finance_approver` peut approuver ;
- un utilisateur ne peut pas approuver sa propre demande ;
- `platform_admin` n’obtient pas automatiquement les permissions finance ;
- toute modification critique après approbation invalide l’approbation.

Champs critiques :

- montant ;
- devise ;
- bénéficiaire ;
- destination ;
- provider account.

## 6. Destination snapshot

Le payout doit conserver un snapshot immuable de la destination utilisée au moment de l’approbation/exécution.

Le système ne doit pas dépendre d’une donnée bénéficiaire modifiée ultérieurement pour expliquer un payout historique.

## 7. Ledger

Toutes les opérations passent par le ledger 06A.

Exemples conceptuels :

```text
refund:{refund_id}:reserved
refund:{refund_id}:executed
refund:{refund_id}:reversed

payout:{payout_id}:reserved
payout:{payout_id}:executed
payout:{payout_id}:released
```

Aucune mise à jour silencieuse d’une transaction `POSTED`.

Toute correction financière passe par reversal et nouvelle écriture.

## 8. Unknown et reprise

Pour Refund comme Payout :

```text
timeout provider
réponse ambiguë
connexion interrompue après émission
```

doit conduire à :

```text
UNKNOWN
```

Avant retry, le système doit vérifier l’état réel provider ou passer par réconciliation.

Ne jamais lancer automatiquement une seconde opération financière identique après un état `UNKNOWN`.

## 9. Reconciliation

### 9.1 Portée MVP

La réconciliation couvre :

- Payments ;
- Refunds ;
- Payouts ;
- frais provider ;
- settlements.

### 9.2 Modèle

`ReconciliationRun` :

- provider account ;
- période ;
- source/import ;
- statut ;
- started_at ;
- completed_at.

`ReconciliationItem` :

- type ;
- internal_reference ;
- provider_reference ;
- internal_amount ;
- provider_amount ;
- currency ;
- internal_status ;
- provider_status ;
- result ;
- resolution_status ;
- metadata.

### 9.3 Résultats

```text
MATCHED
MISSING_INTERNAL
MISSING_PROVIDER
AMOUNT_MISMATCH
STATUS_MISMATCH
REVIEW_REQUIRED
```

### 9.4 Résolution

Une résolution :

- doit être explicite ;
- doit être auditée ;
- ne doit pas réécrire silencieusement l’historique ;
- peut produire une correction ledger par reversal/nouvelle écriture ;
- doit conserver la preuve du constat initial.

## 10. Audit

Les actions suivantes doivent être auditables :

- demande Refund ;
- approbation Refund ;
- demande Payout ;
- approbation Payout ;
- annulation ;
- changement critique ;
- résolution de reconciliation ;
- action manuelle finance.

## 11. API minimale 06C

API envisageable :

```text
POST /api/v1/payments/{payment}/refunds
GET  /api/v1/refunds/{refund}

POST /api/v1/campaigns/{campaign}/payouts
GET  /api/v1/payouts/{payout}
POST /api/v1/payouts/{payout}/approve

POST /api/v1/reconciliation-runs
GET  /api/v1/reconciliation-runs/{run}
POST /api/v1/reconciliation-items/{item}/resolve
```

Les routes exactes restent soumises aux Policies et permissions finance.

## 12. Concurrence

Tests PostgreSQL réels obligatoires pour :

- deux refund requests concurrentes sur même refundable balance ;
- deux payout reservations concurrentes ;
- double approbation ;
- retry sur même idempotency key ;
- résolution reconciliation concurrente.

Attendu :

```text
aucun dépassement
aucune double réservation
aucun double effet ledger
aucune double Outbox
```

## 13. Hors périmètre 06C

Ne pas implémenter :

- intégration Wave production réelle ;
- règlement légal/fiscal final ;
- comptabilité légale ;
- orchestration bancaire externe complète ;
- Flutter ;
- Barkeelu Live ;
- moteur BI avancé ;
- automatisation IA des décisions financières.

## 14. Critères GO 06C

La mission ne sera validée que si les tests prouvent au minimum :

1. refund partiel ;
2. plusieurs refunds sans dépassement ;
3. invariant atomique reserved + executed <= refundable ;
4. timeout Refund => UNKNOWN ;
5. payout réservé avant appel provider ;
6. double réservation impossible ;
7. operator != approver ;
8. auto-approbation refusée ;
9. modification critique invalide l’approbation ;
10. destination snapshot immuable ;
11. timeout Payout => UNKNOWN ;
12. reconciliation avec les six résultats définis ;
13. résolution auditée ;
14. correction financière par reversal ;
15. idempotence sans double ledger/outbox ;
16. concurrence PostgreSQL réelle ;
17. suite complète sans régression.

## 15. Conséquence

06C complète la première fondation financière cohérente de Barkeelu :

```text
Donation
→ Payment
→ Ledger
→ Refund / Payout
→ Reconciliation
```

tout en maintenant le ledger comme source technique de vérité financière interne.
