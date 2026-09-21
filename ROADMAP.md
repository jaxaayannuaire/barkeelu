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
| Wave locale durcie 09C1 | Réalisé |
| Webhook Tester Wave Business Portal 09C2 | Réalisé |
| Micro-transaction Wave réelle contrôlée 09C3 | Planifié |
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

### 09C1 — Durcissement Wave local

Terminé : initiations ambiguës, webhooks stricts, compte `WAVE`, throttle
 dédié et réconciliation `UNKNOWN` par session ou `client_reference`.

### 09C2 — Webhook Tester Wave Business Portal

Terminé : endpoint `https://test.barkeelu.com/api/v1/webhooks/WAVE` joignable
en SSL, signatures valides acceptées, signatures invalides rejetées HTTP `401`,
healthcheck `PROCESSED` sans job métier et `test.test_event` `PROCESSED` sans
finance. Warning Wave historique non bloquant.

### 09C3 — Micro-transaction réelle contrôlée

- exécuter une micro-transaction autorisée et traçable ;
- vérifier `PAID`, `FAILED`, `UNKNOWN`, montant, devise, session, `AppliedFee` et ledger ;
- garder Wave production non autorisée jusqu'à validation 09C3.

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
