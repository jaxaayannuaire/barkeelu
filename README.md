# Barkeelu.com

Plateforme de fundraising, crowdfunding, dons, solidarité et impact social.

## Statut

Projet en phase de conception technique et d'initialisation.

Aucune fonctionnalité financière n'est considérée production-ready à ce stade.

## Stack cible

- Laravel 13
- PHP 8.3
- PostgreSQL
- Redis
- Laravel Sanctum
- Laravel Reverb
- API REST `/api/v1`
- Flutter pour les clients mobiles/desktop

## Architecture

Le monorepo réserve les emplacements suivants :

- `apps/api` : application Laravel principale (API et SSR) ;
- `apps/mobile` : emplacement réservé au futur client Flutter ;
- futur document root : `apps/api/public`.

Référence :

```text
docs/architecture/BARKEELU_ERD_V1_2_CONSOLIDE.md
```

Décisions :

```text
docs/decisions/
```

## Gouvernance

Lire `AGENTS.md` avant toute modification.

## Langue

Documentation, rapports, commentaires et messages Git : français.

Identifiants techniques du code : anglais.
