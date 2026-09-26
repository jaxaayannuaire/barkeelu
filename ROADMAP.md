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
| KYC profile workflow | Réalisé |
| KYC document workflow | Réalisé |
| KYC immutable review audit | Réalisé |
| KYC private file security/download | Réalisé |
| KYC image derivatives/WebP | Réalisé |
| KYC PDF optimization | Planifié |
| KYC antivirus/quarantine | Planifié |
| KYC geolocation evidence | Planifié |
| Payout KYC gating | Planifié |
| Trust/Compliance avancé | Planifié |
| OCR | Planifié |
| IA compliance | Planifié |
| Biométrie | Planifié |
| Campaign core | Réalisé |
| Fondation financière : ledger, frais, Outbox | Réalisé |
| Donation / CheckoutSession / Payment / Provider accounts / Webhooks | Réalisé |
| Refund / Payout / Reconciliation MVP | Réalisé |
| Parcours SSR terminal et transparence frais | Réalisé |
| Wave locale durcie 09C1 | Réalisé |
| Webhook Tester Wave Business Portal 09C2 | Réalisé |
| Micro-transaction Wave réelle contrôlée 09C3 | Réalisé |
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

Terminé et validé : micro-transaction Wave contrôlée, webhook signé, retrieve
serveur, proxy egress optionnel, HTTPS Cloudflare trusted proxy, retour
cross-device read-only, migrations PostgreSQL R6/R7 et synchronisation Checkout.

Résultat réel : 100 + 4 + 1 = 105 XOF, Payment/Donation/Checkout `PAID`,
WebhookEvent `PROCESSED`, ledger `POSTED`, deux `AppliedFee`. Suite complète :
221 tests, 1547 assertions, exit 0.

L'activation production reste une décision de déploiement et d'exploitation
séparée. Elle exige migrations appliquées, worker queue, webhook signé et proxy
Wave privé si configuré.

## Phases suivantes

10A4C couvre uniquement le pipeline image documentaire C1 à C4. L'optimisation PDF et l'antivirus restent planifiés.

1. 10A4D — preuve de localisation KYC (planifié, selon le phasage accepté) ;
2. 10A5 — gating KYC des payouts ;
3. 10A6 — Trust / Compliance ;
4. 10A7 — OCR ;
5. 10A8 — IA ;
6. 10A9 — biométrie ;
7. Administration / modération ;
8. API mobile Flutter.

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
