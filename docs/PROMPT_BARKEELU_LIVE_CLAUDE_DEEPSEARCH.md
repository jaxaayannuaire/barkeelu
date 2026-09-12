# PROMPT — ÉTUDE TECHNIQUE BARKEELU LIVE

Réaliser une étude 2026 approfondie, factuelle et sourcée sur l’architecture Barkeelu Live.

## Contexte validé
Barkeelu.com est une plateforme sénégalaise de collecte de fonds, solidarité et gestion de programmes sociaux. Stack : Laravel 13, PHP 8.3, PostgreSQL, Redis/queues, API REST, WebSockets. Le SaaS Core générique de Yessal ERP sera réutilisé. Les pages publiques restent en Laravel SSR. Flutter est prévu pour Android/iOS/Windows/macOS.

Barkeelu Live ne doit PAS héberger ni distribuer le flux vidéo. Le flux reste chez un fournisseur spécialisé et/ou les réseaux sociaux. Barkeelu fournit les données dynamiques de campagne, QR code, suivi des dons, analytics et intégrations API.

## Objectif
Comparer et recommander une architecture permettant à un influenceur ou organisateur de diffuser simultanément sur TikTok, YouTube, Facebook, Instagram et autres, tout en affichant dynamiquement dans le Live :
- objectif ;
- montant collecté ;
- pourcentage ;
- nombre de donateurs ;
- montant restant ;
- QR code ;
- URL de campagne ;
- éventuellement dernier don.

Après chaque don confirmé, les données doivent pouvoir être mises à jour en temps réel via Laravel Events + Redis + WebSockets, avec fallback polling.

## Solutions à étudier

### SaaS
1. Restream
2. Castr
3. StreamYard
4. OneStream Live
5. autres alternatives pertinentes

Pour chacune : tarifs 2026, plans, API, REST API, OAuth, webhooks/WebSockets, destinations, TikTok, YouTube, Facebook, Instagram, RTMP/SRT, stream keys, Studio, invités, chat, statistiques, recording, clips, overlays/tickers/QR, automatisation, limites, rate limits, coûts cachés, conditions commerciales et adéquation à Barkeelu.

### Open source / self-hosted
Étudier au minimum :
- Ant Media Server
- MediaMTX
- Owncast
- OvenMediaEngine
- SRS
- autres solutions pertinentes

Pour chacune : licence, maturité, API, RTMP, WebRTC, HLS, SRT, transcoding, recording, restreaming, multistream, overlays, Docker, sécurité, scalabilité, CPU/RAM, bande passante, coûts OVH, maintenance et compatibilité Laravel.

## YouTube
Étudier officiellement YouTube Data API + YouTube Live Streaming API :
OAuth, scopes, création/programming de broadcast, stream, association, start/stop, statut, statistiques, quotas, coûts et automatisation depuis Barkeelu.

## TikTok
Étude séparée et rigoureuse :
- Login Kit/OAuth ;
- Content Posting API ;
- Display API ;
- Webhooks ;
- TikTok LIVE ;
- LIVE API éventuelle ;
- RTMP ;
- TikTok Studio/Live Center ;
- conditions d’accès ;
- scopes ;
- audit ;
- restrictions des apps non auditées ;
- restrictions géographiques et de comptes ;
- création/programmation de LIVE ;
- statistiques ;
- commentaires/chat ;
- intégration via Restream/Castr ;
- distinction stricte entre Content Posting API et LIVE.

Ne jamais supposer qu’une API TikTok LIVE existe si la documentation officielle ne le confirme pas.

## Architecture souhaitée

Proposer :

Barkeelu Campaign
→ Barkeelu Live Engine
→ Provider Adapter (Restream/Castr/YouTube/Futur self-hosted)
→ réseaux sociaux

Et séparément :

Barkeelu
→ Live Data API
→ Live Overlay
→ Browser Source OBS/Restream/Castr/autres

Le Live Overlay doit exister en 16:9 et 9:16, éventuellement transparent, et afficher les données de campagne sans transporter la vidéo.

Prévoir une architecture anti-vendor-lock-in.

## API conceptuelle

GET /api/v1/campaigns/{campaign}/live-data

Exemple :
{
  "campaign": "Soutien aux Daaras 2026",
  "goal": 50000000,
  "collected": 37450000,
  "percentage": 74.9,
  "donors": 8452,
  "remaining": 12550000,
  "currency": "XOF",
  "status": "LIVE"
}

Étudier aussi :
- OAuth ;
- API keys ;
- webhooks ;
- events ;
- Redis ;
- WebSockets ;
- retry/idempotence ;
- sécurité ;
- rate limiting ;
- audit ;
- observabilité.

## Scénarios
1. OBS → Restream → TikTok + YouTube + Facebook.
2. StreamYard → YouTube uniquement.
3. TikTok natif.
4. YouTube natif.
5. Barkeelu Dashboard → création automatique du Live → provider.
6. Donation confirmée → Ledger → WebSocket → Overlay.
7. Live terminé → statistiques → campagne → analytics.

## Étude économique
Calculer le coût réel pour 10, 50, 100, 500 et 1 000 Lives/mois :
abonnement + infrastructure + bande passante + stockage + maintenance + monitoring + développement + support.

## Rapport attendu
1. Executive Summary
2. Requirements
3. Restream
4. Castr
5. StreamYard
6. Alternatives SaaS
7. Open Source
8. YouTube API
9. TikTok API/LIVE
10. Comparaison technique
11. Comparaison économique
12. Architecture recommandée
13. Live Data API
14. Live Overlay
15. Sécurité/scalabilité
16. Risques
17. Roadmap
18. Recommandation finale

Privilégier les sources officielles (documentation développeurs, pricing, GitHub officiel, conditions d’utilisation). Pour les prix, indiquer la date de vérification. Distinguer clairement faits documentés, limitations constatées et recommandations.
