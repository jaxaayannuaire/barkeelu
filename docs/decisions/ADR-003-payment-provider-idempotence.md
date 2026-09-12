# ADR-003 — Payment Provider et idempotence

## Statut
Accepté pour conception V1.

## Contexte
Une donation peut générer plusieurs tentatives de paiement et plusieurs tentatives peuvent réellement réussir.

## Décision
Relation :

```text
Donation 1 ── N Payment
```

Aucun `payment_id` n'est stocké dans `donations`.

Chaque paiement possède son propre état et sa propre clé d'idempotence.

Les niveaux d'idempotence sont séparés :
- requête API ;
- tentative provider ;
- événement webhook ;
- opération métier ;
- posting ledger ;
- refund ;
- payout.

## Paiements multiples réussis
Un second paiement réellement encaissé reste `PAID`.

Il ne doit pas augmenter automatiquement le nominal de la donation.

Il est isolé dans `UNAPPLIED_FUNDS`, puis traité par réconciliation et remboursement ou affectation explicitement autorisée.

## Conséquences
Aucune contrainte `UNIQUE successful payment per donation`.
