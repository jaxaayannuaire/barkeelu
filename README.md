# Barkeelu.com

Plateforme de fundraising, crowdfunding, dons, solidarité et impact social.

## Parcours public de don

`CheckoutSession` est le seul parcours public normal. Le CTA campagne ouvre le
flux SSR montant, coordonnées, quote, confirmation, paiement puis statut.
L'ancien endpoint direct de création de Donation est déprécié et répond HTTP
`410 Gone`.

`CheckoutQuoteService` construit le quote serveur depuis les `FeePolicy` actives.
Le `fee_snapshot` confirmé fournit montant nominal, frais et total payable aux
vues. `DonationFactory` crée alors une `Donation` `PENDING` depuis ce snapshot.
Il ne crée ni `AppliedFee`, ni écriture ledger, ni `Payment`.

`PaymentService` reste autorité de matérialisation financière après confirmation
serveur d'un `Payment` `PAID` : postings ledger et `AppliedFee` issus du snapshot
confirmé. Une Donation peut avoir plusieurs Payments ; un second succès reste
traçable vers `UNAPPLIED_FUNDS`.

## Paiements et webhooks

Les fournisseurs passent par `PaymentProviderGateway`. `WaveGateway` et son
checkout sont intégrés côté code. Le navigateur est seulement une UX : webhook,
vérification fournisseur et `PaymentService` restent autorités métier. Les
webhooks bruts sont persistés, dédupliqués et traités par queue. Une signature
invalide n'a aucun effet métier ; un résultat ambigu reste `UNKNOWN`.

Wave n'est pas validé E2E réel et n'est pas production-ready. Secrets et
configuration runtime restent hors Git.

## Statut

Parcours Donation / Checkout SSR et domaines Finance MVP sont implémentés et
testés. Activation d'encaissement réelle reste interdite avant validation Wave
sandbox/E2E, sécurité PII CheckoutSession et revue opérationnelle.

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

Les Refunds et Payouts sont réservés dans le ledger, avec
idempotence, séparation `finance_operator` / `finance_approver`, statuts
`UNKNOWN` pour les réponses fournisseur ambiguës et rapprochement audité. Les
corrections de reconciliation passent exclusivement par reversal. Les soldes
Campaign restent des projections non autoritatives : les réservations sont
matérialisées dans le ledger, via `PAYOUT_RESERVED`.

La provision payout reste inactive par défaut. Les frais affichés proviennent
uniquement du `fee_snapshot` serveur ; aucune valeur de frais n'est recalculée
dans le navigateur.

Cette fondation ne constitue pas une comptabilité légale ou fiscale.

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
