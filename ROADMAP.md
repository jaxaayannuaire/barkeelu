# Barkeelu — ROADMAP

## Statut global

| Domaine | État |
|---|---|
| Vision produit | ✅ Validée |
| Audit pré-migrations | ✅ Réalisé |
| ERD V1.2 consolidé | ✅ Réalisé |
| ADR critiques | ✅ Préparés |
| Gouvernance AGENTS.md | ✅ Préparée |
| Initialisation dépôt local | ✅ Réalisé |
| Socle Laravel | ✅ Réalisé |
| PostgreSQL backend | ✅ Réalisé |
| API /api/v1 de base | ✅ Réalisé |
| Sanctum minimal | ✅ Réalisé |
| Redis infrastructure (cache) | ✅ Réalisé |
| Queue Redis | ✅ Réalisé |
| Reverb infrastructure (broadcasting) | ✅ Réalisé |
| Identity / Organizations / Platform RBAC | ✅ Réalisé |
| Beneficiaries / Representatives / KYC | ✅ Réalisé |
| Campaign core | ✅ Réalisé |
| Payment Wave | ⏳ Planifié |
| Ledger | ⏳ Planifié |
| Refund / Payout / Reconciliation | ⏳ Planifié |
| Barkeelu Live data-only | ⏳ Planifié |
| Flutter | ⏳ Planifié |
| Production | ⏳ Non autorisée |

## Priorité P0

1. initialiser le dépôt et la gouvernance ;
2. installer le socle Laravel ;
3. mettre en place Identity / Organizations / RBAC ;
4. Beneficiaries / Representatives / KYC ;
5. Campaigns ;
6. provider accounts / fee policies / ledger foundation ;
7. Donation / Payment / Webhook ;
8. Refund / Payout / Reconciliation.

## Règle

Aucune collecte réelle avant validation du circuit :

```text
encaissement
→ comptabilisation
→ remboursement/payout
→ réconciliation
```
