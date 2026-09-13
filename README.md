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
- Redis (cache et queues)
- API REST `/api/v1`
- Flutter pour les clients mobiles/desktop

## Architecture

Le monorepo réserve les emplacements suivants :

- `apps/api` : application Laravel principale (API et SSR) ;
- `apps/mobile` : emplacement réservé au futur client Flutter ;
- futur document root : `apps/api/public`.

Le backend `apps/api` utilise PostgreSQL. L'API V1 fournit une sonde HTTP
publique et une authentification minimale par jeton Laravel Sanctum :

- `GET /api/v1/health` ;
- `POST /api/v1/auth/token` ;
- `GET /api/v1/auth/user` ;
- `DELETE /api/v1/auth/token`.

Redis est utilisé uniquement comme infrastructure de cache, de queue et de
transport temps réel. PostgreSQL reste la source de vérité. Laravel Reverb est
configuré pour le broadcasting technique ; aucune fonctionnalité métier
Barkeelu Live n'est encore implémentée.

L'identité utilise Sanctum pour l'authentification API, Spatie Laravel
Permission pour le RBAC global de plateforme et des memberships Barkeelu pour
les organisations. Les Policies Laravel combinent ces permissions globales et
les règles contextuelles d'organisation.

Les bénéficiaires, représentants historisés et profils KYC sont disponibles en
API. Les documents KYC sont enregistrés sur un disque privé dédié, avec empreinte
SHA-256 et sans exposition des clés internes de stockage. Aucun domaine Payment,
Wave, Ledger ou autre domaine financier n'est implémenté.

Le cœur Campaign est disponible : owner User ou Organization, bénéficiaire
obligatoire, cycle de vie contrôlé et visibilités PUBLIC, UNLISTED et PRIVATE.
Les projections sont non autoritatives et ne sont modifiables par aucune API.
TARGETED, Program, Project, Category, Donation, Payment et Ledger restent hors
du périmètre implémenté.

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
