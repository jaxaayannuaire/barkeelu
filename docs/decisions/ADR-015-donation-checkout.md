# ADR-015 — Donation Checkout

- **Projet** : Barkeelu.com
- **Statut** : Proposé pour validation Tech Lead
- **Date** : 2026-09-18
- **Checkpoint de départ** : `eadbca162dd4d74efbc368c16f7eb58031d42329`
- **Branche** : `develop`
- **Mission cible** : 07A
- **Références** : ADR-003, ADR-004, ADR-006, ADR-012, ADR-013, ADR-014

## 1. Contexte

Le parcours de don doit permettre à une personne de préparer et confirmer un don avant de lancer une tentative de paiement. Le parcours utilisateur, la donation métier, la tentative fournisseur et les événements webhook ont des durées de vie, des responsabilités et des sources d’autorité différentes.

La Mission 07A formalise donc un checkout temporaire, sans modifier les invariants financiers des ADR-012, ADR-013 et ADR-014. Cet ADR est documentaire : il ne crée aucune migration, aucun modèle, aucun service, aucune route ni aucun test métier.

## 2. Décision générale

Le domaine distingue explicitement :

```text
CheckoutSession  ≠  Donation  ≠  Payment  ≠  WebhookEvent
```

- `CheckoutSession` est un objet temporaire orienté parcours utilisateur. Il conserve les données nécessaires pour préparer, afficher et reprendre un checkout.
- `Donation` est l’intention métier durable envers une campagne. Elle est créée après confirmation explicite du donateur, et non lors de la simple ouverture du checkout.
- `Payment` est une tentative ou transaction auprès d’un fournisseur. La relation `Donation 1:N Payment` de l’ADR-013 reste inchangée.
- `WebhookEvent` reste l’événement fournisseur persisté et traité selon l’ADR-004 et l’ADR-013.

Le checkout ne devient jamais une source de vérité financière. Le ledger de l’ADR-012 reste autoritatif pour les écritures financières, et les corrections suivent les règles de l’ADR-014.

## 3. Cycle de vie de CheckoutSession

Les états conceptuels sont :

```text
DRAFT
QUOTED
CONFIRMED
PAYMENT_PENDING
PAID
FAILED
UNKNOWN
EXPIRED
CANCELLED
```

Transitions principales :

```text
DRAFT
  → QUOTED
  → CONFIRMED
  → PAYMENT_PENDING
  → PAID

QUOTED → EXPIRED
CONFIRMED → FAILED | UNKNOWN | EXPIRED | CANCELLED
PAYMENT_PENDING → FAILED | UNKNOWN | PAID | CANCELLED
DRAFT | QUOTED | CONFIRMED → EXPIRED | CANCELLED
```

Une transition doit être validée côté serveur, idempotente et compatible avec les états déjà observés. `EXPIRED` est possible depuis `DRAFT`, `QUOTED` ou `CONFIRMED` lorsque `checkout_expires_at` est dépassé et qu’aucune opération fournisseur ambiguë n’est en cours. Un retour tardif ou hors ordre ne doit pas rétrogader un état final valide.

## 4. Quote, expiration et frais

Une quote est un instantané calculé à partir de la `FeePolicy` applicable. Le calcul est réalisé côté serveur par les composants financiers prévus par l’ADR-012 ; aucun taux de frais tel que `4 %` ou `1 %` ne doit être codé en dur dans le checkout.

La quote conserve au minimum, conceptuellement :

- la devise ;
- le montant nominal du don ;
- les frais applicables ;
- le montant total payable ;
- un snapshot du calcul de frais comprenant conceptuellement :
  - l’identifiant/version de `FeePolicy` ;
  - le type de frais ;
  - la base et la règle de calcul ;
  - les basis points ou paramètres applicables ;
  - la règle d’arrondi ;
  - le montant calculé ;
- `quote_expires_at`.

Une quote à l’état `QUOTED` ne crée pas d’`AppliedFee` financier persistant. Les `AppliedFee` définitifs sont matérialisés seulement au moment où l’intention métier confirmée produit l’effet financier correspondant, conformément aux ADR-012 et ADR-013.

Les montants et le snapshot du calcul de frais affichés puis confirmés sont des snapshots immuables de la décision prise pour cette quote. Toute écriture financière ultérieure doit réutiliser les montants et la politique effectivement confirmés, sans recalcul implicite depuis une configuration courante.

`quote_expires_at` est distinct de `checkout_expires_at` :

- `quote_expires_at` borne la validité du calcul de prix et de frais ;
- `checkout_expires_at` borne la durée de vie du parcours et de sa session temporaire.

Après expiration d’une quote, aucun recalcul silencieux n’est autorisé. Le client doit demander une nouvelle quote explicite, qui produit un nouveau snapshot et une nouvelle échéance. Une session dont la durée de vie est expirée ne peut pas être confirmée comme si elle était encore active.

## 5. Confirmation et création de Donation

La création de `Donation` intervient uniquement après une confirmation explicite et valide :

1. la session est récupérée et vérifiée côté serveur ;
2. la quote est présente et non expirée ;
3. les montants, devise, campagne et snapshots sont cohérents ;
4. l’action explicite de confirmation est acceptée idempotemment ;
5. une `Donation` est créée ou retrouvée pour cette confirmation ;
6. une initiation de `Payment` peut ensuite être demandée.

Une simple visite, un rafraîchissement, une quote ou un retour navigateur ne crée pas de Donation.

## 6. Donateur, compte et visibilité publique

Un don peut être réalisé sans compte utilisateur. `donor_user_id` est donc facultatif lorsque le parcours est anonyme au sens de l’authentification.

L’anonymat est une propriété de visibilité publique uniquement : il ne doit pas supprimer les informations nécessaires à l’audit, à la conformité, au support contrôlé ou au traitement fournisseur. La session et la Donation conservent, selon les règles d’accès et de minimisation applicables, un snapshot du nom, de l’adresse e-mail et du téléphone fournis par le donateur.

Les préférences suivantes sont distinctes de l’existence d’un compte et de l’anonymat public :

- visibilité publique du nom ;
- visibilité publique du montant.

Ces préférences sont capturées avec la Donation et ne doivent pas réécrire l’historique financier ni exposer les données privées dans les réponses publiques.

## 7. Idempotence et retries

L’idempotence est requise à chaque niveau du parcours :

- création de `CheckoutSession` ;
- génération d’une quote explicite ;
- confirmation ;
- initiation de `Payment` ;
- traitement des webhooks et transitions fournisseur selon l’ADR-013.

Une répétition avec la même clé et un contenu critique différent doit produire un conflit explicite et auditable.

Un retry de Payment réutilise la Donation existante et crée, si nécessaire, une nouvelle tentative `Payment` idempotente. Il ne recrée jamais la Donation.

Plusieurs paiements réellement réussis pour une même Donation restent traçables. Le premier succès applicable suit la règle métier normale ; tout succès supplémentaire est conservé conformément à l’ADR-013 et isolé dans le ledger comme `UNAPPLIED_FUNDS` jusqu’à une allocation ou une résolution explicite.

## 8. Autorité du paiement et état UNKNOWN

Le retour du navigateur, d’un mobile ou d’une redirection fournisseur n’est jamais autoritatif. Il sert uniquement de signal pour l’expérience utilisateur.

La confirmation d’un paiement est fondée sur une observation server-side : webhook authentifié et persisté, interrogation fournisseur autorisée ou traitement de réconciliation conforme aux ADR-004, ADR-013 et ADR-014.

`UNKNOWN` n’est possible qu’après l’existence d’une tentative `Payment` ou d’une opération fournisseur effectivement émise dont l’état réel ne peut pas être établi de manière fiable. Un timeout, une interruption après émission ou toute réponse ambiguë conduit alors à `UNKNOWN`, qui n’est pas assimilé à `FAILED`. Un checkout simplement `CONFIRMED`, sans tentative fournisseur, ne devient pas `UNKNOWN`.

Après `UNKNOWN`, aucun retry aveugle ne doit émettre une seconde opération financière. Il faut d’abord déterminer l’état réel auprès du fournisseur ou par réconciliation ; toute nouvelle tentative doit être explicitement autorisée et idempotente.

## 9. API cible conceptuelle

La Mission 07A vise le contrat conceptuel suivant :

```text
POST /api/v1/campaigns/{campaign}/checkout-sessions
GET  /api/v1/checkout-sessions/{checkout}
POST /api/v1/checkout-sessions/{checkout}/quote
POST /api/v1/checkout-sessions/{checkout}/confirm
POST /api/v1/checkout-sessions/{checkout}/payments
GET  /api/v1/checkout-sessions/{checkout}/status
```

Les routes exactes, leurs noms de paramètres, leurs Policies, leurs middlewares et leurs représentations JSON pourront évoluer pendant 07A. Cette liste ne constitue pas une autorisation d’implémentation dans le présent ADR.

Chaque future route devra appliquer côté backend, dans l’ordre adapté : authentification lorsqu’elle est requise, ownership ou portée campagne/session, validation, contrôle d’expiration, idempotence et audit des anomalies. L’absence de compte utilisateur ne doit pas désactiver les contrôles d’intégrité du checkout.

## 10. Cohérence avec les ADR-012, ADR-013 et ADR-014

La décision est compatible avec les ADR précédents sur les points suivants :

- elle conserve `Donation 1:N Payment` et ne réintroduit pas `payment_id` dans `donations` (ADR-013) ;
- elle délègue le calcul à `FeePolicy`, conserve les frais appliqués en snapshot et ne contourne pas le ledger ni l’Outbox transactionnelle (ADR-012) ;
- elle conserve `UNKNOWN`, l’idempotence, l’absence de retry aveugle et la correction par opérations explicites/réconciliation (ADR-013 et ADR-014) ;
- elle traite les doubles succès comme des fonds supplémentaires `UNAPPLIED_FUNDS`, sans réécrire un historique `POSTED` (ADR-012 à ADR-014).

Les points à préciser pendant 07A sont volontairement laissés au niveau d’implémentation : la structure persistée exacte de `CheckoutSession`, la politique de rétention des sessions expirées, le format des clés d’idempotence et le mapping détaillé entre états de session et états `Donation`/`Payment`. Ils ne doivent pas contredire les invariants des ADR antérieurs.

## 11. Hors périmètre ADR-015 / 07A

Ne sont pas couverts :

- Wave production ;
- Orange Money ;
- Stripe/PayPal ;
- Flutter ;
- Barkeelu Live ;
- commentaires ;
- gamification ;
- influenceurs ;
- campagnes `TARGETED` ;
- payouts production ;
- comptabilité légale.

## 12. Conséquence

Le checkout devient une étape temporaire et réessayable du parcours utilisateur, sans confondre préparation, confirmation métier et exécution fournisseur :

```text
CheckoutSession
  → quote snapshotée
  → confirmation explicite
  → Donation
  → Payment 1:N
  → webhook/provider server-side
  → ledger et Outbox
```

Cette séparation permet de reprendre un parcours, de recalculer uniquement via une nouvelle quote explicite, de conserver les tentatives et les ambiguïtés, et de préserver les invariants financiers déjà décidés.
