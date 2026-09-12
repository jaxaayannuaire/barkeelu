# ADR-001 — Campaign Owner, Beneficiary et Representative

## Statut
Accepté pour conception V1.

## Contexte
Barkeelu doit distinguer la personne ou structure responsable d'une campagne, le bénéficiaire réel des fonds et la personne autorisée à représenter ce bénéficiaire.

## Décision
Une campagne possède exactement un Campaign Owner :
- `owner_user_id`, ou
- `owner_organization_id`.

Le créateur est conservé séparément via `created_by_user_id`.

Le bénéficiaire utilise une entité dédiée `beneficiaries`.

Les représentants utilisent `beneficiary_representatives`.

Un bénéficiaire peut exister sans compte Barkeelu.

Le KYC peut porter sur un User, une Organization ou un Beneficiary.

## Conséquences
- pas d'utilisation ambiguë du terme « promoteur » dans le modèle ;
- changement de bénéficiaire ou de représentant critique peut invalider l'éligibilité payout ;
- les permissions d'organisation ne suffisent pas à autoriser un payout.

## Contraintes
- XOR sur `owner_user_id` / `owner_organization_id` ;
- audit du créateur ;
- historique des représentants ;
- séparation des permissions campagne/KYC/finance.
