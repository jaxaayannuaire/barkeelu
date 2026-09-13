# ADR-013 — Donations, Payments, Provider Accounts et Webhooks

- **Projet** : Barkeelu.com
- **Statut** : Proposé pour validation Tech Lead
- **Date** : 2026-09-13
- **Checkpoint de départ** : `c5af35ce6aa7739b4bb16caa97593c737f361ecb`
- **Branche** : `develop`
- **Mission cible** : Codex 06B

## 1. Contexte

La Mission 06A a livré et validé la fondation financière interne :

- ledger double entrée PostgreSQL ;
- `business_key` + `content_hash` pour l’idempotence ;
- immutabilité des transactions `POSTED` ;
- reversal ;
- politiques de frais ;
- Outbox transactionnelle ;
- traitement concurrent sécurisé.

La Mission 06B introduit les intentions de don, les tentatives de paiement, les comptes fournisseurs de paiement et la persistance/gestion des webhooks.

Cette mission ne doit pas implémenter les remboursements, payouts ni la réconciliation complète, qui relèvent de 06C.

## 2. Décision générale

Le domaine financier est séparé en ressources distinctes :

```text
Donation
  1 ─── N Payment

ProviderAccount
  1 ─── N Payment
  1 ─── N WebhookEvent
```

Une `Donation` représente l’intention métier de donner.

Un `Payment` représente une tentative ou transaction chez un fournisseur de paiement.

Un `WebhookEvent` représente un événement brut reçu du fournisseur, persisté avant traitement métier.

Aucune réponse navigateur/mobile ne confirme définitivement un paiement.

## 3. Donation

### 3.1 Rôle

`Donation` représente l’engagement métier du donateur envers une campagne.

Elle ne doit pas contenir de champ `payment_id`.

Une donation peut avoir plusieurs tentatives de paiement.

### 3.2 Champs minimaux

- `id`
- `public_id` UUID
- `campaign_id`
- `donor_user_id` nullable
- `donor_name` nullable
- `donor_email` nullable
- `is_anonymous`
- `currency`
- `nominal_amount`
- `platform_fee_amount`
- `payout_provision_amount`
- `total_payable_amount`
- `status`
- `created_by_user_id` nullable
- timestamps

Tous les montants sont des `BIGINT`.

### 3.3 Statuts proposés

```text
PENDING
PROCESSING
PAID
PARTIALLY_REFUNDED
REFUNDED
CANCELLED
FAILED
```

Pour 06B, seuls les états nécessaires avant Refund peuvent être utilisés effectivement.

`PARTIALLY_REFUNDED` et `REFUNDED` peuvent être réservés pour 06C si aucune logique Refund n’est ajoutée en 06B.

## 4. Payment

### 4.1 Rôle

Chaque `Payment` correspond à une tentative distincte auprès d’un fournisseur.

Relation :

```text
Donation 1:N Payments
```

Ne jamais supposer qu’une Donation ne peut avoir qu’un seul paiement réussi.

### 4.2 Champs minimaux

- `id`
- `public_id` UUID
- `donation_id`
- `provider_account_id`
- `provider`
- `provider_payment_id` nullable
- `provider_reference` nullable
- `internal_reference`
- `currency`
- `amount`
- `status`
- `idempotency_key`
- `provider_status` nullable
- `provider_payload` JSONB nullable
- `paid_at` nullable
- timestamps

### 4.3 Statuts proposés

```text
CREATED
PENDING
PROCESSING
PAID
FAILED
CANCELLED
EXPIRED
UNKNOWN
```

`UNKNOWN` est obligatoire pour les cas où l’état final ne peut pas être déterminé de façon fiable.

Un timeout ou une réponse ambiguë ne doit jamais devenir automatiquement `FAILED`.

## 5. Double succès provider

Une Donation peut avoir plusieurs `Payment` réellement `PAID`.

Règle :

- le premier succès applicable est affecté normalement à la Donation ;
- un succès supplémentaire ne doit pas augmenter automatiquement le montant nominal de la Donation ;
- les fonds supplémentaires sont comptabilisés dans le ledger vers `UNAPPLIED_FUNDS` ;
- une réconciliation ou décision métier ultérieure déterminera remboursement ou allocation explicite.

Aucune contrainte `UNIQUE` ne doit interdire plusieurs paiements réussis pour une même Donation.

## 6. ProviderAccount

`ProviderAccount` représente un compte marchand ou une configuration fournisseur.

Champs minimaux :

- `id`
- `provider`
- `name`
- `environment`
- `merchant_reference` nullable
- `currency`
- `is_active`
- configuration non secrète
- référence vers stockage sécurisé des secrets si nécessaire
- timestamps

Les secrets ne doivent jamais être stockés en clair dans les logs, réponses API ou colonnes génériques exposables.

## 7. WebhookEvent

### 7.1 Principe

Le webhook est persisté avant traitement métier.

Flux :

```text
Provider
→ réception HTTP
→ lecture raw body
→ vérification signature/authentification
→ persistance WebhookEvent
→ ACK
→ queue
→ traitement domaine
→ Payment / Donation
→ Ledger
→ Outbox
```

### 7.2 Champs minimaux

- `id`
- `provider_account_id`
- `provider`
- `provider_event_id` nullable
- `event_type` nullable
- `signature_valid`
- `headers_redacted` JSONB nullable
- `raw_payload` JSONB/text
- `payload_hash`
- `status`
- `received_at`
- `processed_at` nullable
- `attempts`
- `last_error` nullable
- timestamps

### 7.3 Statuts proposés

```text
RECEIVED
VERIFIED
PROCESSING
PROCESSED
IGNORED
FAILED
```

## 8. Idempotence

L’idempotence doit être séparée par niveau :

- requête API ;
- tentative Provider ;
- événement webhook ;
- opération métier ;
- écriture ledger ;
- future requête Refund ;
- future requête Payout.

Une même clé d’idempotence avec un montant, devise ou ressource critique différente doit produire un conflit explicite et auditable.

### 8.1 Webhook

Si le fournisseur fournit un identifiant événement fiable :

```text
UNIQUE(provider_account_id, provider_event_id)
```

Sinon utiliser une clé déterministe fondée sur les attributs de l’événement réellement stables et documentés.

Un événement authentiquement invalide ne doit pas empêcher un futur événement valide d’être traité par collision sur une clé mal conçue.

## 9. Vérification serveur

Avant toute transition vers `PAID`, vérifier côté serveur :

- compte fournisseur attendu ;
- référence interne ;
- montant ;
- devise ;
- état fournisseur ;
- transition autorisée ;
- identité de la tentative.

Le retour navigateur/mobile ne constitue qu’un signal UX.

## 10. Ordre et répétition des événements

Le système doit tolérer :

- duplicata ;
- retry fournisseur ;
- événement retardé ;
- événements hors ordre ;
- interruption worker ;
- traitement répété.

Une transition ancienne ne doit pas rétrograder un état final valide.

Exemple :

```text
PAID
→ webhook tardif PENDING
=> ignorer la rétrogradation
```

## 11. Ledger

06B doit utiliser le ledger 06A, jamais le contourner.

Les opérations financières doivent passer par `LedgerPostingService`.

Une réussite Payment doit produire des business keys stables.

Exemples conceptuels :

```text
payment:{payment_id}:captured
payment:{payment_id}:unapplied
```

Les projections Campaign ne sont pas source de vérité et ne doivent pas devenir l’autorité financière.

## 12. Frais

Les frais appliqués doivent utiliser :

- `FeePolicy`
- `FeeCalculator`
- `AppliedFee`

Règles actuelles :

- `PLATFORM_FEE` : 400 bps, active ;
- `PAYOUT_PROVISION_WORKING` : 100 bps, inactive.

La provision 1 % ne doit pas être activée implicitement en 06B.

## 13. Sécurité

Obligatoire :

- aucune clé API ou secret en logs ;
- signature webhook vérifiée avant traitement métier ;
- payload brut conservé pour audit avec politique de rétention ;
- erreurs expurgées ;
- rate limiting des endpoints publics ;
- audit des anomalies ;
- aucune route publique permettant un posting direct dans le ledger.

## 14. API 06B minimale

API envisageable :

```text
POST /api/v1/campaigns/{campaign}/donations
POST /api/v1/donations/{donation}/payments
GET  /api/v1/donations/{donation}
GET  /api/v1/payments/{payment}
POST /api/v1/webhooks/{provider}
```

Les routes exactes restent soumises aux Policies et à la stratégie fournisseur.

## 15. Hors périmètre 06B

Ne pas implémenter :

- Refund métier complet ;
- Payout ;
- Reconciliation complète ;
- activation réelle Wave production ;
- règlement juridique/fiscal ;
- Campaign projection updater autoritatif ;
- Flutter ;
- Barkeelu Live ;
- orchestration permanente de l’Outbox.

## 16. Critères GO 06B

La mission ne sera validée que si les tests prouvent au minimum :

1. Donation 1:N Payments ;
2. plusieurs paiements réussis possibles pour une Donation ;
3. second succès correctement isolé vers `UNAPPLIED_FUNDS` ;
4. idempotence création Payment ;
5. même clé + contenu différent => conflit ;
6. webhook brut persisté avant traitement ;
7. duplicata webhook sans double effet métier ;
8. événements hors ordre sans rétrogradation ;
9. signature invalide sans effet métier ;
10. montant/devise/référence incohérents rejetés ;
11. timeout/ambiguïté => `UNKNOWN`, pas `FAILED` arbitraire ;
12. posting ledger idempotent ;
13. aucun secret en logs ;
14. tests PostgreSQL réels ;
15. suite complète sans régression.

## 17. Conséquence

Cette architecture sépare clairement :

```text
intention métier
≠ tentative fournisseur
≠ événement fournisseur
≠ écriture financière
```

Elle permet d’ajouter Wave puis d’autres fournisseurs sans coupler le domaine Barkeelu à un fournisseur unique.
