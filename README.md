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

## Paiements et webhooks Wave

`PaymentProviderGateway` isole Wave du domaine métier. Le navigateur reste un
signal UX : webhook signé ou vérification serveur, puis `PaymentService`, sont
les seules autorités de transition financière.

Une initiation Wave devient `SENT_UNKNOWN` après `429`, `5xx`, timeout ou
réponse `2xx` incomplète. Les erreurs `400`, `401`, `403`, `422` et locales
déterministes deviennent `NOT_SENT`. Après `UNKNOWN`, aucun retry aveugle ni
second `POST` checkout n'est autorisé. Aucun `AppliedFee` ni posting ledger
n'existe avant `PAID`.

Les webhooks bruts sont persistés, dédupliqués et traités par queue. Un
`checkout.session.payment_failed` minimal est corrélé strictement par
`provider_account_id` et session checkout, puis passe Payment et Checkout à `FAILED` sans
effet Finance. Un `test.test_event` signé est persisté, dédupliqué et
`PROCESSED` sans effet métier. Une signature invalide est persistée `IGNORED`,
sans job, puis rejetée HTTP `401`. Un healthcheck Wave signé devient
`PROCESSED` sans job métier, Payment, Donation, `AppliedFee` ni ledger.

Le provider canonique est `WAVE`; les frontières acceptent la casse. Un webhook
requiert exactement un compte Wave actif. Zéro ou plusieurs comptes actifs sont
rejetés. Multi-compte explicite reste futur.

`webhook-wave` limite à 120 requêtes/minute par provider normalisé et IP. La
signature et la déduplication restent protections métier principales. Ce seuil
sera recalibré après E2E réel.

Pour résoudre `UNKNOWN`, Wave est interrogé directement si la session provider
est connue. Sinon, Barkeelu cherche uniquement par `client_reference` persistée
et égale à `internal_reference`: zéro ou plusieurs résultats restent `UNKNOWN`;
un résultat unique doit valider référence, montant et devise avant mémorisation
de session et mapping. Aucune recherche par téléphone ou montant seul. `PAID`
reste irréversible; frais et ledger passent uniquement par `PaymentService`.

Le Webhook Tester Wave Business Portal a validé l'endpoint
`https://test.barkeelu.com/api/v1/webhooks/WAVE` : serveur joignable, SSL
valide, signatures valides acceptées et signatures invalides rejetées HTTP
`401`. Aucun nouvel échec de queue n'a été observé après correctif. Secrets
runtime restent hors Git.

## Statut

Parcours Donation / Checkout SSR et domaines Finance MVP sont implémentés et
testés. Le Webhook Tester Wave Business Portal est validé. La prochaine étape
est une micro-transaction Wave contrôlée. Production Wave reste interdite tant
que 09C3 n'est pas validé.

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
