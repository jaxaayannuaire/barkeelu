# Barkeelu — ROADMAP

## Mission 06C

Donations, Payments, Provider Accounts, Webhooks, Refunds, Payouts et
Reconciliation MVP : réalisés. Wave production, comptabilité légale et
orchestration bancaire complète restent planifiés.

## Statut global

| Domaine | État |
|---|---|
| Vision, ERD consolidé et ADR critiques | Réalisé |
| Socle Laravel, PostgreSQL, API V1 et Sanctum | Réalisé |
| Redis, queue Redis et Reverb infrastructure | Réalisé |
| Identity / Organizations / Platform RBAC | Réalisé |
| Beneficiaries / Representatives / KYC | Réalisé |
| Campaign core | Réalisé |
| Fondation financière : ledger, frais, Outbox | Réalisé |
| Donation / Payment / Provider accounts / Webhooks | Réalisé |
| Refund / Payout / Reconciliation MVP | Réalisé |
| Barkeelu Live data-only | Planifié |
| Flutter | Planifié |
| Production | Non autorisée |

## Priorité P0

1. Socle de gouvernance et d'identité ;
2. Organizations, bénéficiaires et KYC ;
3. Cœur Campaign ;
4. Fondation financière : ledger double entrée, frais et Outbox ;
5. Donations, paiements et persistance des webhooks ;
6. Refund, Payout et Reconciliation MVP.

## Limites de la fondation financière

Le ledger est interne et technique : il ne constitue pas une comptabilité
légale ou fiscale. Les projections Campaign restent non autoritatives. Redis et
Reverb restent du transport et ne sont jamais une source de vérité.

Aucune collecte réelle n'est autorisée avant validation du circuit complet :

```text
encaissement
→ comptabilisation
→ remboursement / payout
→ réconciliation
```
