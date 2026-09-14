# Barkeelu.com

## Fondation Donations et Payments

La mission 06B ajoute les intentions de donation, les tentatives de paiement,
les comptes fournisseur et la persistance des webhooks. Une Donation peut avoir
plusieurs Payments : un second succès est conservé et comptabilisé vers
`UNAPPLIED_FUNDS`, sans augmenter automatiquement le nominal de la Donation.

Les webhooks bruts sont persistés avant traitement, dédupliqués et traités par
queue. Les signatures invalides n'ont aucun effet métier. Aucun fournisseur Wave
de production n'est activé.

Plateforme de fundraising, crowdfunding, dons, solidarité et impact social.

## Statut

Projet en phase d'initialisation technique. Aucun flux d'encaissement, de
paiement fournisseur, Wave, remboursement ou payout n'est production-ready.

## Stack cible

- Laravel 13 ;
- PHP 8.4 ;
- PostgreSQL ;
- Redis pour le cache, les queues et le transport temps réel ;
- Laravel Sanctum ;
- Laravel Reverb ;
- API REST `/api/v1` ;
- Flutter pour les futurs clients mobiles et desktop.

## Architecture disponible

Le backend `apps/api` utilise PostgreSQL comme source de vérité. Redis et
Reverb ne conservent aucun état métier autoritatif.

L'identité utilise Sanctum pour l'authentification API, Spatie Laravel
Permission pour le RBAC global et des memberships Barkeelu pour les
organisations. Les bénéficiaires, représentants historisés et profils KYC sont
disponibles ; les documents KYC restent sur un disque privé dédié avec empreinte
SHA-256.

Le cœur Campaign est disponible avec owner User ou Organization, bénéficiaire
obligatoire, cycle de vie contrôlé et projections non autoritatives.

La fondation financière 06A fournit un ledger technique interne en double
entrée. PostgreSQL protège l'équilibre des transactions `POSTED` au commit,
l'immutabilité des transactions et entries postées, ainsi que l'immutabilité des
frais appliqués. Les corrections passent par reversal ; l'idempotence repose sur
`business_key` et `content_hash`. Les politiques de frais XOF et une Outbox
transactionnelle sont disponibles pour les futurs domaines financiers.

La mission 06C ajoute des Refunds et Payouts réservés dans le ledger, avec
idempotence, séparation `finance_operator` / `finance_approver`, statuts
`UNKNOWN` pour les réponses fournisseur ambiguës et rapprochement audité. Les
corrections de reconciliation passent exclusivement par reversal. Les soldes
Campaign restent des projections non autoritatives : les réservations sont
matérialisées dans le ledger, via `PAYOUT_RESERVED`.

Cette fondation ne constitue pas une comptabilité légale ou fiscale. Wave de
production, l'orchestration bancaire complète et les décisions financières
automatisées ne sont pas implémentés.

Référence :

```text
docs/architecture/BARKEELU_ERD_V1_2_CONSOLIDE.md
```

Décisions :

```text
docs/decisions/
```

## Gouvernance

Lire `AGENTS.md` avant toute modification. Les commentaires, documents,
rapports et messages Git sont en français ; les identifiants techniques restent
en anglais.
