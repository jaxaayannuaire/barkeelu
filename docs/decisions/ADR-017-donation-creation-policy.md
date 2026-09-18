# ADR-017 — Politique de création des Donations et compatibilité API

- **Projet** : Barkeelu.com
- **Statut** : Proposé pour validation architecture et finance
- **Date** : 2026-09-18
- **Checkpoint de départ** : `91c102724ac109d8fd974b064d9ed061ab8e777e`
- **Branche** : `develop`
- **Portée** : création de Donation, API publique, Checkout et tests financiers
- **Références** : ADR-012, ADR-013, ADR-014, ADR-015, ADR-016, `checkout-07A-plan.md`

---

## 1. Contexte

L'API directe actuelle est :

```text
POST /api/v1/campaigns/{campaign}/donations
    → DonationController
    → DonationService::create()
```

Elle crée aujourd'hui une Donation `PENDING` et matérialise immédiatement les
`AppliedFee` `PLATFORM_FEE` et `PAYOUT_PROVISION`, sans `CheckoutSession`,
quote, snapshot immuable, confirmation, Payment, webhook ou ledger.

Le parcours officiel est désormais :

```text
CheckoutSession → QUOTED → CONFIRMED → Donation
    → Payment → Payment PAID server-side → AppliedFee → ledger
```

Ces deux modèles ne doivent plus évoluer comme deux parcours publics
équivalents.

## 2. Décision principale

`CheckoutSession` devient le seul parcours public normal de création d'une
Donation pour :

- le SSR public ;
- la future application mobile Flutter ;
- les futurs clients publics API ;
- les intégrations publiques futures.

Aucun nouveau client public ne doit utiliser directement :

```text
POST /api/v1/campaigns/{campaign}/donations
```

Le contrat public commun est : création CheckoutSession, quote, confirmation,
paiement puis status/webhook server-side.

Les responsabilités restent séparées :

```text
Donation creation ≠ fee calculation
Donation creation ≠ AppliedFee creation
Donation creation ≠ ledger posting
```

## 3. Statut de l'endpoint direct

L'endpoint direct est **DEPRECATED**.

Il reste temporairement présent pour compatibilité technique et transition :

- aucun consommateur applicatif réel n'a été identifié dans le dépôt ;
- aucun nouveau développement ne doit s'y appuyer ;
- il ne constitue pas le contrat mobile futur ;
- sa neutralisation ou sa suppression relève de 07C4.

Cette ADR ne choisit pas encore entre HTTP 410 et suppression directe. Cette
décision interviendra après migration des tests et vérification d'éventuels
clients externes.

## 4. Cible de `DonationService`

`DonationService` ne doit plus être un service public de calcul autonome. Il
doit évoluer vers une responsabilité interne de type `DonationFactory` ou
`DonationCreationService`.

La cible reçoit des données financières déjà confirmées. Elle ne doit pas :

- relire `FeePolicy` ;
- recalculer un frais ;
- décider `PLATFORM_FEE` ou `PAYOUT_PROVISION` ;
- créer un `AppliedFee` ;
- poster au ledger ;
- appeler un provider.

Le calcul appartient à la quote. La matérialisation financière appartient au
traitement server-side du Payment `PAID`.

## 5. Contrat interne cible

Le contrat conceptuel de création depuis snapshot confirmé reçoit au minimum :

```text
campaign_id
donor_user_id nullable
donor_snapshot
nominal_amount
platform_fee_amount
payout_provision_amount
total_payable_amount
currency
idempotency_key
content_hash
source_context
```

`source_context` identifie un contexte autorisé : confirmation Checkout,
import validé, opération administrative explicitement autorisée ou fixture de
test dédiée.

Les valeurs reçues sont déjà déterminées par Checkout ou par un contexte
interne audité. La factory ne reconstruit pas une quote implicite.

## 6. AppliedFee et ledger

Le cycle cible est :

```text
DRAFT      → 0 AppliedFee
QUOTED     → 0 AppliedFee
CONFIRMED  → 0 AppliedFee
PENDING    → 0 AppliedFee
PAID       → AppliedFee définitifs + ledger
```

Les AppliedFee sont matérialisés uniquement lorsqu'un effet financier est
confirmé server-side, notamment au succès Payment traité par `PaymentService`.
La future factory Donation ne crée aucun AppliedFee, aucun ledger et ne simule
jamais un Payment `PAID`.

Le principe `Payment 1:N Donation` reste inchangé. Un second succès réel reste
traçable et est dirigé vers `UNAPPLIED_FUNDS` selon 06B.

## 7. FeePolicy et snapshot

`FeePolicy` est lue au moment de la quote puis snapshotée dans la
`CheckoutSession`. La création de Donation ne relit jamais les politiques
courantes et reproduit exactement les montants du snapshot confirmé :

```text
nominal_amount
platform_fee_amount
payout_provision_amount
total_payable_amount
currency
```

Une modification ultérieure de `FeePolicy` ne modifie donc aucune Donation
confirmée ni aucun Payment existant. Les valeurs opérationnelles telles que
4 % ou 1 % restent des paramètres de policy, jamais des constantes du modèle.

## 8. Mobile et clients futurs

Flutter utilisera le même contrat Checkout que le SSR :

```text
création CheckoutSession → quote → confirmation → paiement → status
```

L'API Checkout devient le contrat public commun du Web, du mobile et des
intégrations futures. L'API Donation directe ne doit pas être utilisée par ces
clients.

## 9. Opérations internes

Une future factory interne peut servir aux tests, imports validés, migrations
de données et opérations administratives explicitement autorisées, avec :

- snapshot financier explicite ;
- source auditée ;
- clé d'idempotence ;
- hash déterministe ;
- contexte d'autorisation traçable.

Un appel interne ne doit jamais relire silencieusement `FeePolicy`, créer un
AppliedFee, créer un Payment, simuler `PAID` ou poster directement au ledger.

## 10. Idempotence cible

```text
même clé + même contenu       → même Donation
même clé + contenu différent  → conflit explicite
concurrence PostgreSQL        → une seule Donation
```

Le contenu critique est déterministe et n'inclut pas de timestamp généré.
L'implémentation exacte du verrouillage et du rattrapage de concurrence sera
définie en 07C2.

## 11. Compatibilité des services

`CheckoutConfirmationService` doit à terme déléguer la création de Donation à
la factory interne commune, en préservant le snapshot confirmé, l'unicité par
CheckoutSession, l'idempotence et l'absence d'AppliedFee à `CONFIRMED`.

Cette migration relève de 07C2 et aucun code n'est modifié par cette ADR.

`PaymentService` reste responsable du traitement `PAID`, du ledger, des
AppliedFee définitifs, de l'outbox et des effets financiers confirmés. Le
refactor Donation ne déplace pas ces responsabilités.

## 12. Tests financiers

Les tests Refund, Payout, Reconciliation, Payment concurrency et Webhook ne
doivent plus utiliser `DonationService` comme raccourci implicite. Ils migrent
progressivement vers une factory de test ou des fixtures financières
explicites en 07C3.

Les tests qui vérifient spécifiquement le comportement legacy de
`DonationService` restent identifiés comme tels jusqu'à la neutralisation de
l'endpoint.

## 13. Séquence cible

```text
07C1 — ADR-017 et contrat cible
07C2 — Donation factory depuis snapshot confirmé
07C3 — migration des tests financiers
07C4 — dépréciation ou neutralisation de l'endpoint direct
07C5 — garde-fou RefundService exigeant Payment PAID
```

07C5 est une dette séparée identifiée en 07C0 : `RefundService::request()`
doit vérifier explicitement l'état `Payment PAID`. Ce sujet est hors périmètre
ADR-017 et ne doit pas être mélangé à 07C1.

## 14. Hors périmètre

Cette ADR ne modifie pas `DonationService`, les services Checkout, les modèles,
les migrations, les tests, `PaymentService`, `RefundService`,
`PayoutService`, `ReconciliationService`, les policies opérationnelles,
l'activation de `PAYOUT_PROVISION_WORKING`, l'UI, Flutter, les providers ou
les webhooks.

## 15. Décisions restant ouvertes

1. forme exacte de la factory interne ;
2. stratégie de concurrence PostgreSQL ;
3. contrat d'une éventuelle façade historique ;
4. migration de clients externes non détectés ;
5. choix HTTP 410 ou suppression en 07C4 ;
6. garde-fou `Payment PAID` de 07C5.

## 16. Conséquences et validation

Cette décision rend explicites les invariants suivants : Checkout est le seul
parcours public normal ; l'endpoint direct est deprecated ; Donation creation
ne calcule ni frais, ni AppliedFee, ni ledger ; `FeePolicy` est snapshotée avant
Donation ; les AppliedFee n'existent définitivement qu'après Payment `PAID` ;
le mobile utilise Checkout ; les tests Finance migrent vers une factory dédiée ;
l'endpoint direct sera supprimé ou neutralisé ultérieurement.

ADR-017 est strictement documentaire. Sa mise en œuvre nécessite 07C2 à 07C5
et une validation séparée des tests financiers, clients API et invariants
ledger.
