# Barkeelu — ROADMAP

## Phases 06 à 08 terminées

- CheckoutSession, quote serveur, confirmation et `DonationFactory` ;
- suppression du flux Donation legacy et endpoint direct neutralisé en HTTP `410 Gone` ;
- abstraction provider, intégration Wave interne et webhook serveur ;
- états SSR terminaux, fallback sans JavaScript et transparence des frais depuis `fee_snapshot` ;
- Refund limité aux `Payment` `PAID` ;
- Donations, Payments, Refunds, Payouts et Reconciliation MVP.

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
| Donation / CheckoutSession / Payment / Provider accounts / Webhooks | Réalisé |
| Refund / Payout / Reconciliation MVP | Réalisé |
| Parcours SSR terminal et transparence frais | Réalisé |
| Wave sandbox / E2E réel | Planifié |
| Barkeelu Live data-only | Planifié |
| Flutter | Planifié |
| Production | Non autorisée |

## Phase 09 — suite proposée

### 09A — Sécurité PII CheckoutSession

- minimiser ou chiffrer téléphone dans `checkout_sessions.donor_snapshot` ;
- conserver absence de PII dans réponses et vues publiques.

### 09B — Cohérence UI terminale

- centraliser libellés utilisateur du `FeeSnapshot` ;
- corriger libellé technique restant dans `thanks` ;
- conserver pages terminales après pause ou clôture campagne.

### 09C — Validation Wave sandbox / E2E

- secrets runtime hors Git ;
- checkout signé et webhook signé ;
- doublons, ordre inversé, `UNKNOWN` et retrieve ;
- `PAID`, `FAILED`, `EXPIRED`, montant, devise et compte provider ;
- ledger et `AppliedFee` seulement après `PAID`.

## Phases suivantes

1. KYC / Trust ;
2. Payout opérateur ;
3. Réconciliation opérationnelle ;
4. Administration / modération ;
5. API mobile Flutter.

## Historique P0

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
