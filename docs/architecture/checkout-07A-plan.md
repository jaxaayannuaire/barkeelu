# Plan d’implémentation Checkout 07A

> **Pour les agents d’implémentation :** ce document est un plan de cadrage issu de la Mission 07A0. Il ne constitue pas une autorisation de modifier le code. Chaque sous-phase doit respecter ses critères GO/NO-GO et les contraintes du working tree.

**Objectif :** faire évoluer le parcours Donation/Wave existant vers un checkout métier générique, temporaire et persisté, conforme à ADR-015, sans réécrire les invariants financiers des ADR-012, ADR-013 et ADR-014.

**Architecture :** `CheckoutSession` devient la ressource temporaire du parcours. Elle porte la quote et les snapshots jusqu’à une confirmation explicite, puis crée une `Donation` durable ; chaque tentative de paiement reste un `Payment` distinct. Wave est intégré derrière un contrat `PaymentProviderGateway`, sans devenir le modèle métier du checkout.

**Technologies de référence :** Laravel 13, PHP 8.3, PostgreSQL, Redis/queues, Sanctum lorsque requis, SSR Laravel, API REST `/api/v1`.

**Sources de décision :** ADR-012, ADR-013, ADR-014, ADR-015 et `docs/architecture/BARKEELU_ERD_V1_2_CONSOLIDE.md`.

## Contraintes globales

- `CheckoutSession != Donation != Payment`.
- `CheckoutSession` est une ressource métier persistée temporaire.
- Une session Laravel, `provider_checkout_session_id`, `Payment`, `Donation` ou une simple quote ne remplace pas `CheckoutSession`.
- Une `Donation` est créée uniquement après confirmation explicite et valide.
- La relation `Donation 1:N Payment` reste inchangée.
- Un retry Payment réutilise la même Donation et peut créer un nouveau Payment.
- Une quote `QUOTED` ne crée aucun `AppliedFee` financier persistant.
- Les `AppliedFee` définitifs sont matérialisés au moment de l’effet financier confirmé.
- Les montants sont des entiers et la devise est explicite ; aucun calcul client ne fait autorité.
- `FeePolicy` et `FeeCalculator` sont la source du calcul ; aucun taux de frais ne doit être codé dans le checkout.
- `PAYOUT_PROVISION_WORKING` ne doit être ni activée ni désactivée pendant 07A0 ; le conflit observé est une décision séparée à arbitrer avant 07A2.
- Le navigateur est un signal UX ; le webhook/provider server-side est autoritatif.
- Toute émission fournisseur ambiguë conduit vers une stratégie `UNKNOWN`, jamais vers un état `CREATED` silencieux.
- Après `UNKNOWN`, aucun retry aveugle ne doit émettre une seconde opération financière.
- Aucun fichier UI protégé ou fichier financier existant ne doit être modifié pendant 07A0.

## 1. Constat de départ et stratégie de migration

Le working tree contient actuellement :

- un parcours SSR public en trois étapes ;
- une session Laravel `donation_flow.{slug}` ;
- des colonnes Payment spécifiques à Wave ;
- un `WaveCheckoutService` qui crée et relit une session provider ;
- une création de Donation dans l’action `pay()` avant l’émission Wave ;
- un calcul de frais relu depuis les politiques actives à chaque affichage et recalculé à la création de Donation ;
- des webhooks Wave signés, persistés puis traités par queue ;
- un retry qui crée un nouveau Payment après expiration provider.

Ces éléments sont une base de caractérisation et un adaptateur à isoler. Ils ne constituent pas une `CheckoutSession` ADR-015.

La migration cible est additive et progressive :

1. caractériser le comportement actuel sans modifier les fichiers protégés ;
2. introduire la persistance et la machine d’états `CheckoutSession` ;
3. faire porter quote, expirations et snapshots par la session ;
4. déplacer la création de Donation après confirmation ;
5. extraire le contrat provider ;
6. adapter Wave derrière ce contrat ;
7. conserver provisoirement les vues SSR via un contrôleur d’orchestration compatible, sans faire des vues une source de vérité.

Le parcours actuel doit rester identifiable pendant la transition par un identifiant de version ou une stratégie de compatibilité documentée. Aucune migration destructive ou réécriture silencieuse de données Payment existantes n’est prévue.

## 2. Architecture cible

```text
Client SSR/API
    │
    ├── crée ou reprend CheckoutSession
    │       │
    │       ├── DRAFT
    │       ├── quote serveur → QUOTED
    │       ├── confirmation explicite → CONFIRMED
    │       │                         │
    │       │                         └── crée/retrouve Donation + fige le snapshot confirmé
    │       │
    │       └── demande Payment → PAYMENT_PENDING
    │                                  │
    │                                  └── PaymentProviderGateway
    │                                             │
    │                                      WaveGateway (07A3)
    │                                             │
    │                            webhook/provider server-side
    │                                             │
    │                              Payment → ledger/outbox
    │
    └── status : projection contrôlée de CheckoutSession/Donation/Payment
```

Responsabilités :

- `CheckoutSession` : parcours, quote, expirations, snapshots, idempotence et lien campagne.
- `Donation` : intention métier durable créée après confirmation.
- `Payment` : tentative fournisseur individuelle, avec relation N:1 vers Donation.
- `PaymentProviderGateway` : contrat d’initiation et de vérification provider.
- `WebhookEvent` : événement brut persisté avant traitement.
- Ledger/Outbox : source financière et événementielle selon ADR-012.

## 3. Schéma conceptuel exact

### 3.1 CheckoutSession

Ressource persistée temporaire, avec identifiant public non devinable.

Champs minimum conceptuels :

```text
checkout_sessions
- id
- public_id
- campaign_id
- donor_user_id nullable
- status
- currency
- nominal_amount
- total_payable_amount nullable jusqu’à la quote
- fee_snapshot JSONB
- donor_snapshot JSONB
- quote_expires_at nullable
- checkout_expires_at
- confirmed_at nullable
- donation_id nullable UNIQUE
- idempotency_key
- content_hash
- last_payment_id nullable si une projection de suivi est nécessaire
- created_at
- updated_at
```

Contraintes conceptuelles :

- `campaign_id` référence une campagne admissible au don ;
- `donor_user_id` est nullable pour le don invité ;
- `fee_snapshot` et `donor_snapshot` sont des snapshots métier, non des caches recalculables ;
- `content_hash` protège une réutilisation incohérente de la même clé d’idempotence ;
- `checkout_expires_at` est contrôlé côté serveur ;
- aucune colonne provider ne transforme la session en session Wave.

### 3.2 fee_snapshot

La structure JSON conceptuelle contient, pour chaque frais applicable :

```text
fee_snapshot = {
  policy_id,
  policy_version,
  fee_type,
  calculation_parameters,
  basis_points,
  fixed_amount,
  calculation_base_amount,
  rounding_rule,
  calculated_amount,
  currency
}
```

Le snapshot doit permettre de comprendre le montant sans relire une politique future. Une quote `QUOTED` peut conserver ce snapshot dans `CheckoutSession`, mais ne crée pas d’`AppliedFee` persistant.

### 3.3 donor_snapshot

```text
donor_snapshot = {
  name,
  email,
  phone,
  is_anonymous,
  show_name,
  show_amount
}
```

Le snapshot conserve les valeurs soumises pour la confirmation. L’anonymat concerne la visibilité publique ; il ne supprime pas les données privées nécessaires aux contrôles autorisés.

### 3.4 Relations

```text
Campaign 1 ── N CheckoutSession
CheckoutSession 0..1 ── 1 Donation
Donation 1 ── N Payment
Payment N ── 1 ProviderAccount
ProviderAccount 1 ── N WebhookEvent
```

La session n’ajoute pas `payment_id` dans `donations` et ne remplace pas `donation_id` dans `payments`.

## 4. États et transitions

États de `CheckoutSession` :

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

Transitions autorisées conceptuellement :

```text
DRAFT → QUOTED | EXPIRED | CANCELLED
QUOTED → QUOTED | CONFIRMED | EXPIRED | CANCELLED
CONFIRMED → PAYMENT_PENDING | EXPIRED | CANCELLED
PAYMENT_PENDING → PAID | FAILED | UNKNOWN | CANCELLED
UNKNOWN → UNKNOWN | PAID | FAILED
FAILED → PAYMENT_PENDING | CANCELLED
PAID → PAID
```

Règles :

- `EXPIRED` depuis `DRAFT`, `QUOTED` ou `CONFIRMED` est permis lorsque `checkout_expires_at` est dépassé et qu’aucune opération fournisseur ambiguë n’est en cours ;
- `UNKNOWN` est impossible tant qu’aucun Payment n’existe et qu’aucune opération provider n’a été émise ;
- `UNKNOWN` est obligatoire après une émission dont le résultat réel est indéterminé ;
- une confirmation produit au maximum une `Donation` ; la relation persistée et contrainte entre session et Donation garantit l’idempotence et l’audit ;
- `PAID` est issu d’une observation server-side valide ;
- aucune transition ne rétrogade un état final valide ;
- les transitions sont atomiques, idempotentes et auditées.

## 5. Flux cible et diagramme d’expiration

```text
DRAFT
  │ montant/données validés
  ▼
QUOTED ── quote_expires_at dépassé ──► nouvelle quote explicite
  │                                      │
  │ confirmation explicite               └── nouveau snapshot
  ▼
CONFIRMED ── checkout_expires_at dépassé sans provider ambigu ──► EXPIRED
  │
  │ création Donation atomique
  ▼
Payment CREATED
  │
  ├── aucune émission provider ──► FAILED/CANCELLED selon décision métier
  │
  ├── requête provider émise et résultat certain ──► PAYMENT_PENDING
  │                                                    │
  │                                                    ├── succès server-side → PAID
  │                                                    └── échec certain → FAILED
  │
  └── requête provider émise et résultat ambigu ──► UNKNOWN
```

Une expiration de quote ne déclenche jamais un recalcul implicite. Après émission provider, l’état doit être résolu en `PAID`, `FAILED`, `UNKNOWN` ou dans un autre état explicitement supporté par le modèle Payment existant ; il ne devient pas implicitement `EXPIRED`.

## 6. Idempotence

Clés et niveaux :

- création CheckoutSession : clé client + campagne + hash du contenu initial ;
- quote : clé de quote ou version monotone de session ;
- confirmation : clé de confirmation liée à la session ;
- création Donation : clé dérivée de la confirmation, unique et réutilisable ;
- initiation Payment : clé par tentative, jamais réutilisée pour une intention différente ;
- webhook : identifiant provider et hash du corps selon ADR-004/013 ;
- posting ledger : business key stable par Payment et opération.

Même clé avec montant, devise, campagne, donor snapshot ou quote différents : conflit explicite, sans second effet métier.

## 7. Séquence Donation et Payment

### 7.1 Confirmation

La confirmation doit vérifier dans une transaction :

1. session existante, accessible et non expirée ;
2. quote présente et non expirée ;
3. snapshots et campagne cohérents ;
4. confirmation idempotente ;
5. création ou récupération d’au plus une `Donation` pour cette `CheckoutSession` ;
6. gel du snapshot confirmé et de la relation persistée session → Donation ;
7. passage à `CONFIRMED`.

Une visite, un rafraîchissement, une quote ou une redirection provider ne crée pas de Donation.

La confirmation ne matérialise pas encore d’`AppliedFee` financier définitif. Ces snapshots financiers sont matérialisés lorsque l’effet financier correspondant est réellement confirmé, notamment lors du succès `Payment` traité server-side avec son posting ledger.

### 7.2 Payment

L’initiation crée un Payment `CREATED` lié à la Donation confirmée. L’appel provider intervient ensuite.

- résultat provider certain et accepté : `PAYMENT_PENDING` puis suivi server-side ;
- échec certain avant émission : `FAILED` selon la politique retenue ;
- émission probable ou réponse ambiguë : `UNKNOWN` ;
- retry après résolution provider certaine autorisant une nouvelle tentative : nouveau Payment, même Donation ;
- retry après `UNKNOWN` : interdit sans résolution provider/réconciliation.

## 8. Contrat provider conceptuel

Le contrat à préparer, sans l’implémenter pendant 07A0, peut être défini ainsi :

```text
interface PaymentProviderGateway
{
    availability(): ProviderAvailability

    initiate(PaymentContext $payment): ProviderInitiationResult

    retrieve(ProviderOperationReference $reference): ProviderStatusResult

    verifyWebhook(string $rawBody, ProviderHeaders $headers): VerifiedWebhook

    mapWebhook(VerifiedWebhook $webhook): ProviderPaymentEvent
}
```

Le contrat doit exprimer trois résultats distincts :

```text
NOT_SENT       // aucune émission provider confirmée
SENT_CONFIRMED // émission et référence provider connues
SENT_UNKNOWN   // émission possible, état réel indéterminé
```

`WaveGateway` sera le premier adaptateur 07A3. Il encapsulera signature, payload Wave, récupération de session Wave et mapping des statuts, sans exposer de champs Wave au modèle `CheckoutSession`.

## 9. UNKNOWN et expirations

Matrice minimale :

| Situation | État interne attendu | Retry automatique | Action suivante |
|---|---|---:|---|
| Checkout non initié | `DRAFT`, `QUOTED` ou `CONFIRMED` | Non | quote/confirmation/expiration |
| Payment créé, appel non envoyé | `CREATED` | Non | appel contrôlé ou échec explicite |
| Provider request envoyée, réponse certaine | `PAYMENT_PENDING` | Non aveugle | webhook ou retrieve |
| Provider request envoyée, timeout/réponse ambiguë | `UNKNOWN` | Non | retrieve ou réconciliation |
| Provider échec certain, état interne résolu | `FAILED` | Seulement clé nouvelle | nouveau Payment, même Donation si autorisé |

La future implémentation doit rendre impossible le retour silencieux à `CREATED` après une émission potentielle.

## 10. Compatibilité SSR

Les vues SSR Donation existantes peuvent être conservées comme façade de transition :

- l’étape montant appelle la création/reprise de `CheckoutSession` ;
- l’étape récapitulatif affiche le snapshot de quote fourni par le serveur ;
- le bouton de confirmation appelle l’action de confirmation 07A ;
- la page d’attente affiche le statut server-side ;
- la page de remerciement n’est rendue que pour une Donation effectivement `PAID`.

Les vues ne doivent pas calculer les frais, créer Donation ou interpréter directement un retour Wave. Les routes exactes pourront évoluer vers l’API ADR-015 sans faire des noms SSR une contrainte permanente.

## 11. Fichiers à conserver et fichiers à refactorer plus tard

### À conserver comme base technique

- `apps/api/app/Services/Payments/WaveCheckoutService.php` : logique provider à encapsuler dans l’adaptateur ;
- `apps/api/app/Services/Payments/WaveWebhookMapper.php` : mapping Wave à reprendre derrière le contrat ;
- `apps/api/app/Services/Webhooks/WebhookIngressService.php` : persistance et déduplication ;
- `apps/api/app/Jobs/ProcessWebhookEvent.php` : traitement post-commit ;
- `apps/api/app/Services/Payments/PaymentService.php` : idempotence, Payment 1:N, ledger et `UNAPPLIED_FUNDS` à préserver ;
- tests existants de signature, mapping, retry et double succès comme caractérisation.

### À refactorer dans les phases autorisées suivantes

- `DonationFlowController.php` : orchestration CheckoutSession plutôt que création directe Donation ;
- `DonationService.php` : accepter le snapshot confirmé au lieu de recalculer depuis les politiques courantes ;
- `WaveCheckoutService.php` : implémenter le contrat WaveGateway ;
- migration et colonnes provider actuellement portées par Payment ;
- routes et vues Donation pour consommer le statut de session ;
- tests SSR et Wave pour refléter les phases du checkout.

### Fichiers UI protégés

07A0 et les phases 07A ne doivent pas modifier sans autorisation séparée :

```text
apps/api/app/Http/Controllers/Web/CampaignShowController.php
apps/api/resources/views/components/campaign/action-bar.blade.php
apps/api/resources/views/pages/campaigns/show.blade.php
apps/api/resources/js/app.js
apps/api/tests/Feature/CampaignShowTest.php
docs/design/**
docs/DOCUMENTATION_WAVE_YESSAL_ERP.md
```

## 12. Tests de caractérisation requis

Avant toute migration fonctionnelle, les tests doivent figer le comportement observé sans le déclarer conforme :

1. session Laravel du parcours montant/détails/checkout ;
2. absence de Donation avant `POST pay` actuel ;
3. création Donation puis Payment lors du parcours actuel ;
4. montant calculé depuis les politiques actives ;
5. création des AppliedFee actuels ;
6. signature des requêtes Wave ;
7. signature webhook, rotation et ancien timestamp ;
8. persistance brute avant queue ;
9. mapping montant/devise/session/référence ;
10. succès Payment et second succès `UNAPPLIED_FUNDS` ;
11. `UNKNOWN` non rétrogradable par `PENDING` ;
12. retry après expiration provider sans recréer Donation ;
13. absence de données privées dans les URLs et représentations publiques ;
14. purge différée du téléphone.

Les tests de caractérisation ne doivent pas être utilisés pour valider implicitement l’activation de `PAYOUT_PROVISION_WORKING`. Ce conflit doit être traité par une décision dédiée avant 07A2.

## 13. Découpage des sous-phases

### 07A1 — CheckoutSession persistence + state machine

Périmètre :

- modèle/table/identifiant public de `CheckoutSession` ;
- champs minimum et contraintes ;
- enum d’états et transitions atomiques ;
- idempotence de création/reprise ;
- `quote_expires_at` et `checkout_expires_at` sans encore matérialiser les AppliedFee ;
- mapping contrôlé du parcours SSR vers la session.

GO 07A1 si :

- `CheckoutSession` est distincte de Donation/Payment/provider session ;
- les transitions invalides sont refusées ;
- expiration `DRAFT | QUOTED | CONFIRMED` est testée ;
- `UNKNOWN` est impossible sans Payment ou opération provider émise ;
- les retries de création sont idempotents ;
- aucune vue UI protégée ni aucun invariant financier existant n’est modifié.

NO-GO 07A1 si :

- la session repose sur la session Laravel ou `Payment.provider_checkout_session_id` ;
- une Donation est créée à la simple ouverture/quote ;
- le working tree protégé est modifié ;
- la provision payout est activée ou désactivée dans ce lot.

### 07A2 — Quote snapshot + confirmation + Donation

Périmètre :

- quote persistée dans `CheckoutSession` ;
- snapshot complet FeePolicy/règle/base/arrondi/montant ;
- snapshot donateur ;
- confirmation explicite idempotente ;
- création Donation après confirmation ;
- AppliedFee définitifs lors du succès Payment traité server-side avec son posting ledger ;
- Payment `CREATED` créé seulement après Donation confirmée.

GO 07A2 si :

- une quote ne crée aucune AppliedFee persistante ;
- une Donation est créée une seule fois par confirmation idempotente ;
- une CheckoutSession confirmée produit au maximum une Donation, garanti par une relation persistée et contrainte, par exemple `checkout_sessions.donation_id nullable UNIQUE` ;
- une modification de FeePolicy après quote ne change pas le snapshot confirmé ;
- un retry Payment réutilise la Donation ;
- le conflit `PAYOUT_PROVISION_WORKING` est arbitré séparément et aucune activation/désactivation opportuniste n’est introduite.

NO-GO 07A2 si :

- les frais sont recalculés depuis la configuration courante au lieu du snapshot ;
- une confirmation navigateur crée directement un effet financier sans contrôle server-side ;
- AppliedFee est écrit au stade `QUOTED` ;
- le montant Payment diverge du snapshot Donation confirmé.

### 07A3 — Provider abstraction + adaptation Wave

Périmètre :

- contrat `PaymentProviderGateway` ;
- `WaveGateway` adaptateur ;
- résultat explicite `NOT_SENT`, `SENT_CONFIRMED`, `SENT_UNKNOWN` ;
- webhook Wave signé, persisté, mappé puis appliqué ;
- récupération provider avant tout retry après `UNKNOWN` ;
- compatibilité avec Payment 1:N et `UNAPPLIED_FUNDS`.

GO 07A3 si :

- aucun modèle métier ne dépend de champs Wave ;
- timeout ou ambiguïté d’émission devient `UNKNOWN` ;
- aucun retry aveugle n’est possible après `UNKNOWN` ;
- le webhook reste l’autorité de confirmation ;
- les doubles succès conservent leur traitement ledger existant ;
- les tests provider et PostgreSQL requis passent.

NO-GO 07A3 si :

- Wave impose ses statuts au modèle CheckoutSession ;
- un retour navigateur suffit à déclarer `PAID` ;
- une réponse ambiguë reste `CREATED` ou déclenche une seconde émission automatique ;
- l’adaptateur modifie les invariants de ledger/outbox/refund/payout/reconciliation.

## 14. Décisions encore ouvertes

Les choix suivants doivent être arbitrés avant la phase qui les consomme :

- stratégie de rétention et purge des CheckoutSession expirées ;
- format exact des JSON `fee_snapshot` et `donor_snapshot` ;
- durée et renouvellement d’une quote ;
- politique de réouverture d’une session `FAILED` ;
- relation persistée et contrainte `CheckoutSession` → `Donation` à figer avant 07A2, par exemple `checkout_sessions.donation_id nullable UNIQUE` ou équivalent ;
- relation de lecture éventuelle `CheckoutSession.last_payment_id`, sans en faire une relation financière autoritative ;
- mapping exact des états CheckoutSession vers Donation et Payment ;
- contrat d’erreur et réponse API publique ;
- décision séparée sur l’activation de `PAYOUT_PROVISION_WORKING` au regard d’ADR-012/013 ;
- stratégie de coexistence entre routes SSR actuelles et routes `/api/v1` ADR-015.

Une décision ouverte ne doit pas être remplacée par une valeur implicite dans le code d’une sous-phase.

## 15. GO / NO-GO global 07A0

**GO pour le cadrage documentaire uniquement.** Le présent document est autorisé comme unique livrable 07A0.

**NO-GO pour l’implémentation 07A1 immédiate** tant que les critères suivants ne sont pas réunis :

- validation de ce schéma conceptuel et des transitions ;
- confirmation du gel des fichiers UI protégés ;
- caractérisation du parcours existant et de ses fixtures ;
- arbitrage explicite du conflit `PAYOUT_PROVISION_WORKING` avant 07A2 ;
- confirmation que l’implémentation 07A1 reste additive et ne réutilise pas un identifiant provider comme CheckoutSession.

07A0 ne modifie aucun fichier applicatif, UI ou financier et ne lance aucune opération Git de publication.
