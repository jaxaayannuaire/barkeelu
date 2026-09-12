# ADR-007 — Barkeelu Live data-only

## Statut
Accepté pour conception V1.

## Décision
Barkeelu ne transporte pas le flux vidéo dans l'architecture normale.

Barkeelu Live fournit :
- Live Data API ;
- Redis/Reverb ;
- overlay navigateur ;
- QR et liens trackés ;
- analytics ;
- adapters fournisseurs.

Le flux vidéo reste chez :
- YouTube ;
- outils de streaming ;
- plateformes sociales ;
- fournisseurs spécialisés.

## Adapter Pattern
Providers possibles :
- YouTube
- Restream
- Castr
- Native
- futur self-hosted exceptionnel

Les capacités sont optionnelles. Un provider n'a pas à implémenter artificiellement une opération qu'il ne supporte pas.

## TikTok
Aucune dépendance critique ne doit reposer sur une API TikTok LIVE non officielle.
