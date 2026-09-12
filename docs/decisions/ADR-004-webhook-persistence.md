# ADR-004 — Persistance et traitement asynchrone des webhooks

## Statut
Accepté pour conception V1.

## Décision
Pour un événement authentifié :

```text
Provider
→ vérification signature
→ persistance WebhookEvent
→ ACK
→ Queue
→ traitement métier
→ Payment / Donation
→ Ledger
→ Outbox
```

Le système doit tolérer :
- doublons ;
- événements désordonnés ;
- retries ;
- retards ;
- interruption worker.

La déduplication est scoped par compte fournisseur/environnement lorsque nécessaire.

Les événements invalides sont journalisés dans un canal sécurité expurgé et ne doivent pas empoisonner la clé d'un événement valide ultérieur.

## Sécurité
Toujours vérifier côté serveur :
- compte provider ;
- référence interne ;
- montant ;
- devise ;
- transition autorisée.
