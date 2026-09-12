# ADR-006 — Transactional Outbox

## Statut
Accepté pour conception V1.

## Contexte
Un commit PostgreSQL peut réussir alors qu'une publication Redis/Reverb échoue.

## Décision
Tout événement métier critique est écrit dans `outbox_events` dans la même transaction SQL que l'état métier et, lorsqu'il y en a, le ledger.

```text
DB transaction
├── business state
├── ledger
└── outbox event
```

Après commit :

```text
Outbox worker
→ Redis
→ Reverb
→ notifications
→ projections
```

## Conséquences
Redis n'est jamais source de vérité.

Les projections temps réel sont reconstructibles.
