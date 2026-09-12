# ADR-002 — Ledger interne en double entrée

## Statut
Accepté pour conception V1.

## Contexte
Barkeelu conserve temporairement des fonds destinés à des campagnes et doit pouvoir expliquer tout mouvement financier.

## Décision
Le noyau financier utilise un ledger technique interne en double entrée.

Tables principales :
- `ledger_accounts`
- `ledger_transactions`
- `ledger_entries`

Une transaction postée est immuable.

Toute correction utilise un reversal.

## Invariants
- minimum deux lignes ;
- montant positif ;
- direction DEBIT/CREDIT ;
- total débit = total crédit ;
- devise cohérente ;
- business key idempotente ;
- aucune suppression physique après posting.

## Mécanisme PostgreSQL
Le mécanisme précis sera implémenté via une fonction de posting ou un trigger différé, puis testé sous concurrence.

Un simple `CHECK` de ligne ne suffit pas pour garantir l'équilibre multi-lignes.

## Plan de comptes technique initial
Assets/Clearing :
- `PAYMENT_CLEARING`
- `SETTLEMENT_CLEARING`
- `PROVIDER_FUNDS`

Liabilities :
- `CAMPAIGN_PAYABLE`
- `PAYOUT_PROVISION_RESERVE`
- `REFUND_PAYABLE`
- `UNAPPLIED_FUNDS`

Revenue :
- `PLATFORM_FEE_REVENUE`

Expenses :
- `PAYMENT_PROVIDER_FEE_EXPENSE`
- `PAYOUT_PROVIDER_FEE_EXPENSE`

## Conséquences
Le ledger ne remplace pas la comptabilité légale/fiscale de Jaxaay Group.
