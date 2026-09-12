# ADR-005 — Payout : réservation, approbation et reprise

## Statut
Accepté pour conception V1.

## Décision
Avant tout envoi provider, Barkeelu réserve atomiquement le montant disponible.

Un payout possède :
- demandeur ;
- bénéficiaire ;
- destination figée ;
- montant ;
- approbations ;
- provider ;
- idempotency key ;
- état ;
- référence provider ;
- écriture ledger.

## Séparation des rôles
Au minimum :

```text
finance_operator != finance_approver
```

Un utilisateur ne peut pas approuver sa propre demande.

Un changement de bénéficiaire, destination, montant ou KYC pertinent invalide l'approbation existante.

## Timeout
Un timeout provider donne un état `UNKNOWN`, pas automatiquement `FAILED`.

Avant toute nouvelle émission :
- vérifier ;
- réconcilier ;
- déterminer si le premier payout a réellement été exécuté.

## Conséquences
Pas de double versement après timeout.
