# ADR-016 — Politique de provision des frais de payout

- **Projet** : Barkeelu.com
- **Statut** : Proposé pour validation métier, contractuelle et comptable
- **Date** : 2026-09-18
- **Checkpoint de départ** : `126b2eb7e3ac1dd53d2844be319376b9a8313da8`
- **Branche** : `develop`
- **Portée** : Checkout, donations, ledger, payouts et réconciliation
- **Références** : ADR-012, ADR-013, ADR-014, ADR-015, `checkout-07A-plan.md`

---

## 1. Contexte

Barkeelu doit pouvoir financer les frais réellement facturés lors d'un payout
sans confondre cette provision avec :

```text
PLATFORM_FEE_REVENUE
PAYOUT_RESERVED
CAMPAIGN_PAYABLE
```

Le modèle de travail envisagé est :

```text
montant nominal du don
+ commission plateforme Barkeelu
+ provision de frais de payout
= total payé par le donateur
```

Exemple indicatif :

```text
nominal                 10 000 XOF
commission plateforme      400 XOF
provision payout           100 XOF
total payé              10 500 XOF
```

Les montants et taux sont ceux des `FeePolicy` applicables au moment de la
quote/confirmation. Aucun taux ne doit être codé en dur dans le checkout.

L'architecture financière existante prévoit déjà :

- `PAYOUT_PROVISION_RESERVE`, compte de passif distinct ;
- `PAYOUT_RESERVED`, compte de réservation d'un payout approuvé ;
- `PAYOUT_PROVIDER_FEE_EXPENSE`, compte de charge technique ;
- `PLATFORM_FEE_REVENUE`, compte de revenu plateforme.

L'état documentaire et l'état local divergent actuellement : ADR-012 décrit
`PAYOUT_PROVISION_WORKING` comme une hypothèse inactive/non contractuelle,
alors que `FinancialFoundationSeeder.php` la seed localement active et que le
parcours local `DonationService` calcule et matérialise cette provision lors
de la création de la Donation. Cette ADR formalise la cible et ne modifie pas
ces fichiers.

---

## 2. Décision

### 2.1 Nature de la provision

La provision payout peut être facturée au donateur en supplément du montant
nominal lorsque la `FeePolicy` applicable l'autorise et que la présentation
commerciale/contractuelle le rend explicite.

Elle n'est pas, par défaut, un revenu Barkeelu. Elle représente un montant
affecté à la couverture d'un coût de payout futur et doit rester traçable
jusqu'à sa consommation, sa libération ou sa résolution par une règle
contractuelle explicite.

```text
PLATFORM_FEE_REVENUE       != PAYOUT_PROVISION_RESERVE
PAYOUT_PROVISION_RESERVE   != PAYOUT_RESERVED
```

Un excédent de provision ne devient jamais automatiquement un revenu
plateforme.

### 2.2 Source du calcul

Le calcul repose exclusivement sur `FeePolicy` et `FeeCalculator` :

- politique et version applicables ;
- type `PAYOUT_PROVISION` ;
- basis points et/ou montant fixe ;
- base de calcul ;
- règle d'arrondi ;
- montant calculé.

La valeur opérationnelle actuellement envisagée de `100 bps` (`1 %`) est une
hypothèse métier/contractuelle, pas une constante du modèle. Un changement de
fournisseur, de tarif ou de contrat doit modifier la politique et son
snapshot, pas le checkout.

### 2.3 Moment de calcul et matérialisation

La quote peut calculer et snapshotter une provision potentielle dans
`CheckoutSession.fee_snapshot`. Une simple quote `QUOTED` ne crée aucune
`AppliedFee` définitive et aucune écriture ledger.

La confirmation fige le snapshot confirmé, mais ne crée pas encore d'effet
financier définitif. Les `AppliedFee` sont matérialisées lorsque le succès du
`Payment` est confirmé server-side et que le posting ledger correspondant est
effectué, conformément à ADR-012, ADR-013 et ADR-015.

La provision effectivement comptabilisée est donc le montant du snapshot
confirmé accepté lors de l'encaissement, non un recalcul silencieux depuis la
`FeePolicy` courante.

### 2.4 Décision d'implémentation pour 07A2

07A2 implémentera le support générique d'une `FeePolicy` de type
`PAYOUT_PROVISION`. Cette capacité technique ne constitue pas une activation
commerciale du taux actuellement envisagé de `1 %`.

Une provision n'entre dans une quote que si sa `FeePolicy` est explicitement
active, applicable à la devise et à la date concernées, et sélectionnée par
les règles métier validées. Aucun `100 bps` ni `1 %` ne doit être codé dans le
checkout.

Les tests 07A2 peuvent créer une politique de provision explicitement dans
leurs fixtures afin de couvrir le calcul, le snapshot et les invariants. Ces
fixtures ne modifient pas la configuration opérationnelle réelle.

La configuration opérationnelle existante ne doit être ni activée ni modifiée
par cette ADR ou par 07A2. Toute activation, désactivation ou modification de
`PAYOUT_PROVISION_WORKING` exige une décision séparée, validée sur les plans
métier, contractuel et comptable.

---

## 3. Comptes et exemple ledger

Pour un don de 10 000 XOF avec 400 XOF de commission et 100 XOF de provision,
le posting conceptuel du premier Payment `PAID` est :

| Compte | Sens | Montant | Signification |
|---|---:|---:|---|
| `PAYMENT_CLEARING` | Débit | 10 500 | Fonds reçus du donateur |
| `CAMPAIGN_PAYABLE` | Crédit | 10 000 | Montant nominal dû à la campagne/bénéficiaire |
| `PLATFORM_FEE_REVENUE` | Crédit | 400 | Commission plateforme |
| `PAYOUT_PROVISION_RESERVE` | Crédit | 100 | Provision affectée aux frais futurs |

Le posting est une transaction double entrée append-only selon ADR-012.
La provision n'augmente pas le montant nominal de `CAMPAIGN_PAYABLE` et ne
réserve pas un payout.

`PAYOUT_RESERVED` n'intervient que lorsqu'un payout déterminé est approuvé et
que son montant est réservé atomiquement selon ADR-014.

La consommation ou la libération de la provision doit être une nouvelle
transaction ledger idempotente, jamais une modification d'une transaction
`POSTED`. Le compte de contrepartie exact pour le coût fournisseur doit être
validé avec la comptabilité avant implémentation ; cette ADR ne crée pas
implicitement un nouveau compte.

---

## 4. Lifecycle de la provision

```text
FeePolicy applicable
        │
        ▼
quote : snapshot potentiel
        │  (aucune AppliedFee, aucun ledger)
        ▼
confirmation : snapshot figé
        │  (aucun AppliedFee définitif)
        ▼
Payment PAID server-side
        │
        ├── AppliedFee PLATFORM_FEE
        ├── AppliedFee PAYOUT_PROVISION
        └── posting vers PAYOUT_PROVISION_RESERVE
                │
                ├── coût payout constaté
                ├── libération du reliquat
                └── exception/réconciliation
```

Chaque opération est idempotente, auditée et rattachée à sa source métier
(`Donation`, `Payment`, `Payout` ou opération de réconciliation).

---

## 5. Coût réel du payout

### 5.1 Coût égal à la provision

La totalité de `PAYOUT_PROVISION_RESERVE` est affectée au coût réel. La
transaction de consommation documente le payout, le fournisseur, le montant,
la devise et la référence de réconciliation. Aucun montant ne devient un
revenu plateforme.

### 5.2 Coût inférieur à la provision

Le coût constaté est consommé et le reliquat reste un solde de provision
identifiable. Il ne revient automatiquement ni à `PLATFORM_FEE_REVENUE`, ni à
`CAMPAIGN_PAYABLE`, ni à `available_for_payout`.

Le reliquat ne peut être libéré vers la campagne/bénéficiaire, maintenu comme
réserve ou reclassé en revenu que sur la base d'une règle contractuelle et
comptable explicite, versionnée et auditée.

### 5.3 Coût supérieur à la provision

La provision est consommée jusqu'à son solde disponible. Le dépassement est
un coût supplémentaire à traiter par la politique de financement et le
posting comptable validés ; il ne doit pas être prélevé silencieusement sur le
nominal dû à la campagne et ne transforme pas la provision en revenu.

Une réconciliation doit signaler le dépassement et empêcher toute clôture
silencieuse de l'écart.

---

## 6. Payout partiel et payouts multiples

Une campagne peut avoir plusieurs payouts partiels. La provision doit alors
être suivie par allocation :

```text
provision attendue
- provision consommée par payout 1
- provision consommée par payout 2
- provision libérée/résolue
= solde de provision non résolu
```

`PAYOUT_RESERVED` reste calculé et réservé séparément pour chaque payout
autorisé. Une réservation de payout ne consomme pas implicitement la réserve
de frais ; la consommation de cette dernière nécessite le coût provider
effectivement confirmé.

Les clés d'idempotence doivent empêcher une double consommation lorsqu'un
webhook, un retry ou une réconciliation est rejoué.

---

## 7. Refund avant payout

Un refund avant payout doit tenir compte de la part de provision rattachée au
montant remboursé :

1. verrouiller les Payment/Donation concernés ;
2. calculer la provision effectivement encaissée et encore non consommée ;
3. poster un reversal ou une nouvelle transaction conforme à ADR-012 ;
4. réduire la provision disponible selon la règle de remboursement validée ;
5. ne jamais modifier une écriture `POSTED` ni rembourser automatiquement la
   commission plateforme sans règle explicite.

Un refund partiel doit produire une allocation proportionnelle ou une règle
arrondie déterministe. Un refund après consommation de la provision doit être
traité comme un cas d'écart/réconciliation, sans fabriquer un solde négatif.

---

## 8. Réconciliation

La réconciliation compare au minimum :

- provision snapshotée et encaissée ;
- `AppliedFee` `PAYOUT_PROVISION` ;
- montant crédité à `PAYOUT_PROVISION_RESERVE` ;
- coût payout réellement confirmé par le fournisseur ;
- montants consommés par payout ;
- reliquat, dépassement ou montant non rapproché ;
- refunds et reversals associés.

Les écarts sont persistés comme éléments de réconciliation et résolus par
une action explicite, auditée et idempotente. Une correction se fait par
reversal/nouvelle transaction, jamais par modification silencieuse du ledger.

Un timeout ou une ambiguïté provider produit `UNKNOWN` et interdit un retry
aveugle, conformément à ADR-014 et ADR-015.

---

## 9. Impacts par domaine

### 9.1 CheckoutSession / 07A2

`CheckoutSession.fee_snapshot` doit pouvoir contenir la politique de provision
comme tout autre frais, avec son identifiant/version, type, paramètres, base,
règle d'arrondi et montant calculé.

07A2 ne doit pas décider que `1 %` est une constante, ni créer une
`AppliedFee` au stade `QUOTED` ou à la seule confirmation. La Donation doit
conserver le snapshot financier confirmé et le total payable cohérent.

### 9.2 Donation et AppliedFee

La Donation peut conserver `payout_provision_amount` comme montant snapshoté,
mais ce champ ne suffit pas à prouver une consommation réelle. L'`AppliedFee`
immutably snapshotée doit être liée à la source et au posting qui produit
l'effet financier confirmé.

Le comportement local actuel qui crée les deux `AppliedFee` lors de
`DonationService::create` est une caractéristique à refactorer avant son
alignement avec ADR-015/016 ; aucune correction n'est incluse dans cette ADR.

### 9.3 Ledger et disponible payout

La provision est un passif affecté séparé. `available_for_payout` doit
continuer à représenter le montant autorisé pour la campagne selon les règles
ADR-014, sans réutiliser `PAYOUT_PROVISION_RESERVE` comme synonyme de
`PAYOUT_RESERVED`.

### 9.4 Payout et `PAYOUT_RESERVED`

La séquence reste :

```text
available_for_payout
→ approbation
→ PAYOUT_RESERVED
→ appel provider
→ succès/échec/UNKNOWN
```

La provision de frais est une dimension parallèle du coût du payout ; elle ne
remplace ni l'approbation, ni la réservation, ni l'état provider.

---

## 10. Invariants

1. `PLATFORM_FEE_REVENUE != PAYOUT_PROVISION_RESERVE`.
2. `PAYOUT_PROVISION_RESERVE != PAYOUT_RESERVED`.
3. Aucun taux `4 %` ou `1 %` n'est codé en dur dans le checkout.
4. La politique et le montant sont snapshotés avant tout effet financier.
5. Une quote seule ne crée ni `AppliedFee` définitive ni écriture ledger.
6. Une `AppliedFee` définitive correspond à un effet financier confirmé et
   idempotent.
7. Le reliquat de provision n'est jamais automatiquement un revenu.
8. Le nominal dû à la campagne ne diminue pas silencieusement pour financer un
   dépassement de frais.
9. `PAYOUT_RESERVED` est atomique, auditable et distinct de la provision.
10. Toute correction d'une transaction `POSTED` passe par reversal.
11. Les doubles succès Payment restent traités selon ADR-013, notamment
    `UNAPPLIED_FUNDS`.
12. `UNKNOWN` interdit tout retry aveugle jusqu'à résolution provider ou
    réconciliation.

---

## 11. Hors périmètre

Cette ADR ne met pas en œuvre :

- migration, modèle, seeder ou configuration ;
- activation ou désactivation de `PAYOUT_PROVISION_WORKING` ;
- choix d'un fournisseur payout ou d'un tarif fournisseur ;
- comptabilité légale ou fiscale ;
- nouveau compte ledger sans validation comptable ;
- implémentation du quote/confirm/payment 07A2 ;
- refund, payout ou réconciliation de production ;
- Orange Money, Stripe/PayPal, Flutter ou Barkeelu Live.

---

## 12. Décisions soumises à validation contractuelle/comptable

Avant activation opérationnelle, valider explicitement :

1. la facturation de la provision au donateur et sa présentation ;
2. la qualification juridique et comptable de la provision ;
3. le compte de contrepartie lors de la consommation et de la libération ;
4. le traitement du reliquat positif ;
5. le financement d'un coût supérieur à la provision ;
6. la règle de proratisation pour refunds et payouts partiels ;
7. la politique applicable si aucun payout n'est exécuté ;
8. la durée de conservation et la clôture d'une provision non rapprochée ;
9. le statut contractuel de `PAYOUT_PROVISION_WORKING` et sa valeur `100 bps`;
10. la date à laquelle l'implémentation locale devra cesser de créer les
    `AppliedFee` au stade de création Donation.

Tant que ces décisions ne sont pas validées, `1 %` reste une hypothèse de
travail documentée et ne constitue pas une promesse commerciale ou comptable.
