# Barkeelu Live — Étude technique et économique 2026

*Rapport rédigé le 2 septembre 2026. Devise pivot : XOF (parité fixe 1 EUR = 655,957 XOF , garantie par le Trésor français via la BCEAO ; 1 USD ≈ 575 XOF, dérivé de la parité euro et d'un EUR/USD ≈ 1,14  ; Xe cotait 1 USD = 565,58 XOF au 31 août 2026 — fourchette retenue 565–575 XOF/USD).*

## 1. Executive Summary

**Réponse directe : l'architecture recommandée est un modèle « données-only » où Barkeelu ne transporte JAMAIS la vidéo, combiné à un modèle économique où chaque influenceur apporte son propre abonnement de multistreaming (modèle b), Barkeelu ne payant qu'une infrastructure de données temps réel dérisoire (~11 000–79 000 XOF/mois selon la volumétrie).** Le cœur de Barkeelu Live doit être une **Live Data API** + un **Live Overlay** (Browser Source) indépendants de tout fournisseur, testables immédiatement avec OBS seul, alimentés par Laravel Events → Redis → WebSockets (Laravel Reverb) avec fallback polling.

Constats structurants confirmés :
- **TikTok n'a AUCUNE API LIVE officielle** (confirmé). Les produits officiels sont Login Kit, Content Posting API, Display API, Research API, Commercial Content API, Data Portability API, Share Kit. Le Content Posting API publie des vidéos/photos, **pas** de LIVE.
- **La restriction « 50 % gaming » des outils tiers est réelle et datée du 7 juillet 2025.** Le centre d'aide Restream indique textuellement : *« As of July 7 2025, TikTok now requires creators to stream at least 50% gaming content to maintain access… You can reapply after 14 days »*. Streamlabs confirme la même règle et cite explicitement *« StreamElements, Restream.io, etc »* comme touchés. Pour des influenceurs sénégalais non-gaming, **il n'existe aucune voie self-serve fiable pour obtenir une stream key TikTok pour OBS/Restream**.
- **StreamYard n'a aucune API publique** (position officielle) → écarté comme adapter.
- **Restream** dispose d'une vraie API (OAuth 2.0, REST `api.restream.io/v2`, WebSocket `wss://streaming.api.restream.io/ws`).
- **Castr** dispose d'une API REST publique (`developers.castr.com`), mais réservée aux plans **Premium (199,99 $/mois) et supérieurs**.
- **YouTube Live Streaming API** est gratuite, partage le quota de 10 000 unités/jour **par projet Google Cloud** ; un cycle Live complet coûte ~250 unités → ~40 Lives/jour par projet. Le quota partagé est le principal risque de montée en charge multi-influenceurs.

**Séquencement recommandé** (validé) : (1) Live Data API + Overlay ; (2) Adapter YouTube ; (3) Adapter Restream/Castr conditionné à l'économie ; (4) TikTok = LIVE natif + QR/lien tracké uniquement, jamais d'adapter LIVE.

## 2. Requirements

Affichage dynamique dans le Live : objectif, montant collecté, pourcentage, nombre de donateurs, montant restant, QR code, URL de campagne, éventuellement dernier don. Mise à jour temps réel après chaque don confirmé via Laravel Events + Redis + WebSockets, fallback polling. Overlay en 16:9 ET 9:16, transparent. Devise XOF zéro décimale. **Principe non négociable : Barkeelu n'héberge ni ne distribue le flux vidéo.** Public : influenceurs sénégalais non-gaming (religieux, artistes, sportifs, créateurs, personnalités). Stack : Laravel 13, PHP 8.3, PostgreSQL, Redis/queues, API REST, WebSockets, Flutter (Android/iOS/Windows/macOS), pages publiques en Laravel SSR, réutilisation du SaaS Core de Yessal ERP. Anti-vendor-lock-in exigé.

## 3. Restream

**Faits documentés (vérifié 2 sept. 2026).** API réelle : OAuth 2.0 (authorization code), REST base `https://api.restream.io/v2`, WebSocket temps réel `wss://streaming.api.restream.io/ws` (événements de flux entrants/sortants, statut plateforme, nombre de spectateurs), WebSocket chat unifié. Scopes : `profile.read`, `channels.read`, `channels.write`, `events.read`, `events.write`, `chat.read`. Endpoints : `/user/channel/all`, `/user/streamKey` (récupère la stream key + URL SRT), `/user/events`, `/user/event/upcoming`, etc. Destinations : 30+ plateformes (YouTube, Facebook, Twitch, LinkedIn, X, TikTok…).

**Tarifs 2026** (pages officielles restream.io/pricing et centre d'aide, corroborés par sources tierces datées juin 2026 ; à reconfirmer sur la page officielle car les agrégateurs se contredisent) : Free (2 destinations, watermark, 720p) ; **Standard 16 $/mois annuel (19 $ mensuel)** — supprime watermark, 3 canaux ; **Professional 39 $/mois annuel (49 $ mensuel)** — 1080p, invités, enregistrement, jusqu'à 30 destinations ; **Business 199 $/mois annuel** — 8 canaux/SRT/lecteur web. Add-ons facturés en sus : sièges ~25 $/mois, add-on Clips, workspaces. **Le prix est indexé sur le nombre de canaux simultanés et la qualité, PAS sur le nombre de spectateurs.**

**Conditions commerciales (critique).** Les Terms of Service Restream distinguent explicitement l'inscription « as an individual » (streaming pour soi uniquement) et « as an entity » : *« if you are a digital marketing agency producing live video broadcast for agency clients, you must register for our "for companies" or "agency" plan »*. Barkeelu, en tant qu'entité, **peut donc légalement diffuser pour des tiers** sous un plan entreprise/agence. **Mais** interdiction de multi-comptes pour contourner un programme tarifaire.

**Limite d'architecture décisive.** Restream est conçu pour **UN diffuseur fan-out vers N destinations**, pas pour N diffuseurs indépendants simultanés. La concurrence réelle (nombre de sessions live parallèles) est faible. Pour 10 influenceurs en Live en même temps, un compte Restream central est inadapté.

**TikTok via Restream** : soumis à la règle 50 % gaming (7 juillet 2025) + demande d'accès nécessitant un lien vers une chaîne live sur Twitch/YouTube/Facebook (pas TikTok). **Inadapté aux influenceurs religieux/artistes sénégalais.**

## 4. Castr

**Faits documentés (page officielle castr.com/pricing fetchée le 2 sept. 2026).** API REST publique JSON sur `developers.castr.com` (token Bearer, AccessID + Secret Key). Couvre : CRUD live streams, stats de flux, gestion des destinations multistream (`POST /v2/live_streams/{id}/platforms` avec server RTMP + key), recordings/live-to-VOD, clipping instantané, webhooks (démarrage/arrêt de flux), sub-second. **API réservée aux plans Premium et supérieurs.** Multistream vers 30+ plateformes ; overlay chat en connectant les plateformes par login (méthode API) et non RTMP custom.

**Tarifs officiels 2026** (annuel / mensuel) : **Starter 16,67 $ / 19,99 $** (2–3 flux concurrents, 6 destinations, 2,4 To upfront, 100 Go stockage) ; **Standard 41,67 $ / 49,99 $** (10 destinations, pull links) ; **Premium 166,67 $ / 199,99 $** (5–10 flux concurrents, 20 destinations, ABR, contrôles géo, HLS, **API access**, 3 membres) ; **Ultra 291,67 $ / 349,99 $** (10–15 flux concurrents, 30 destinations, failover, eCDN) ; **Events (OTT) 624,99 $ / 749,99 $**. Commission paywall 9 % ; DRM en add-on ; pas de contrat, mensuel possible. Recordings : jusqu'à 72 h, conservés 3 jours.

**Adéquation Barkeelu.** Castr est **mieux adapté qu'un usage centralisé de Restream** car son modèle « flux concurrents » (jusqu'à 10–15 sur Premium/Ultra) + API + gestion programmatique des destinations correspond à une plateforme orchestrant plusieurs flux. Premium à 199,99 $/mois (~115 000 XOF) donne API + 10 flux concurrents + 20 destinations. **TikTok LIVE n'est pas une destination fiable via Castr** (même contrainte de stream key TikTok non-gaming). Castr transporte la vidéo — conforme au principe car c'est le fournisseur, non Barkeelu, qui l'héberge.

## 5. StreamYard

**Faits documentés (centre d'aide officiel).** *« StreamYard does not currently have a public API available, and there's currently no way to embed an entire StreamYard studio directly on a website. »* Les « API » listées par des agrégateurs (apitracker, apis.io) pointent en réalité vers la page ToS et sont des profils tiers non officiels — **aucune API réelle**. StreamYard sert : production navigateur, invités, RTMP custom (sans scheduling/chat/viewer count), webinaires On-Air embarquables. Facturation annuelle uniquement sur l'entrée (dealbreaker pour tests).

**Verdict :** aucune automatisation possible → **écarté comme adapter Barkeelu**. Reste utilisable manuellement par un influenceur (scénario 2 : StreamYard → YouTube uniquement), sans intégration.

## 6. Alternatives SaaS

**OneStream Live.** Plan Free (2 destinations, 720p, 1 h studio, 4 invités). Plans payants avec add-ons modulaires : **destinations +5 pour 10 $/mois, flux concurrent +1 (24/7 YouTube) pour 30 $/mois, +10 membres à partir de 25 $/mois, +1 To bande passante à 10 $/mois, +10 Go stockage à 10 $/mois**. RTMP custom sur tous les plans, multistreaming 45+ plateformes, chat unifié, streaming pré-enregistré, hosted live pages. **Existence d'une API publique non confirmée officiellement** → à traiter comme non disponible tant que non prouvée. Positionnement : forte valeur pour le pré-enregistré et le web embed.

**Switchboard Live, Streamlabs, Melon, Be.Live.** Streamlabs : soumis à la même règle 50 % gaming TikTok (7 juillet 2025), citant explicitement Restream et StreamElements comme touchés. Switchboard Live : orienté multistream pro/agence (API existante mais à vérifier au cas par cas). Melon/Be.Live : studios navigateur grand public sans API robuste. **Aucun ne résout le problème TikTok non-gaming ; aucun n'apporte d'avantage décisif sur Castr/Restream pour l'automatisation.**

## 7. Open Source / Self-hosted

| Solution | Licence | RTMP | WebRTC | HLS | SRT | Restream réseaux sociaux | API | Maturité |
|---|---|---|---|---|---|---|---|---|
| **Ant Media Server** | Community (gratuit) / Enterprise (payant/instance) | ✓ | ✓ (Enterprise ~0,5 s) | ✓ | ✓ | **Enterprise uniquement** | REST | Élevée |
| **MediaMTX** | MIT | ✓ | ✓ | ✓ | ✓ | ✓ (push targets) | REST | Élevée, léger |
| **OvenMediaEngine** | AGPL/proprio | ✓ (E-RTMP) | ✓ (sub-second) | ✓ (LL-HLS) | ✓ | ✓ (push) | REST | Élevée (low latency) |
| **SRS** | MIT | ✓ | ✓ | ✓ | ✓ | ✓ (forward) | HTTP | Très mature (27,3k★ sur GitHub, dépôt ossrs/srs) |
| **nginx-rtmp-module** | BSD | ✓ | ✗ | ✓ | ✗ | ✓ (push multiple) | Directives exec | Mature mais figée |
| **Owncast** | MIT | ✓ | ✗ (HLS) | ✓ | ✗ | Multi-plateforme + REST | REST | GUI clé en main |

**Confirmation clé (GitHub officiel Ant Media) :** *« Restream to Social Media Simultaneously (Facebook and Youtube in Enterprise Edition) »* — le restream simultané vers réseaux sociaux **n'est PAS en Community Edition**. Licence Enterprise payante par instance (options horaire/mensuel/annuel/perpétuel ; licences gratuites pour étudiants/académiques/communautés/early-stage startups sur demande).

**Coûts d'hébergement (OVH, après hausse tarifaire 2026).** VPS-2 ≈ 8,49 €/mois, VPS-3 ≈ 16,99 €/mois (Intel, NVMe, anti-DDoS inclus). VPS-1 monté à ~7,60 €/mois. Hetzner mène désormais le bas de gamme européen. **Latence Europe↔Afrique de l'Ouest** : un relais à Gravelines/Roubaix ajoute ~30–60 ms + risque de gigue ; un relais self-hosted à Dakar (Sonatel/Orange, ou VPS régional) réduirait la latence mais augmenterait coût/maintenance.

**Tension de principe (à trancher).** Un relais self-hosted (Ant Media Enterprise, MediaMTX…) **transporte de fait le flux vidéo**, ce qui contredit frontalement « Barkeelu n'héberge pas le flux ». **Recommandation : abandonner la piste self-hosted en V1/V2 comme chemin par défaut.** Ne la rouvrir qu'en V3+ comme exception explicitement assumée, si et seulement si (a) un besoin de latence/souveraineté le justifie, (b) le coût CPU/bande passante/maintenance (transcodage = très CPU-intensif) est budgété, et (c) la gouvernance accepte la dérogation. MediaMTX serait alors le candidat le plus léger, Ant Media Enterprise le plus complet.

## 8. YouTube API

**Faits documentés (developers.google.com, vérifié 2 sept. 2026).** La YouTube Live Streaming API fait partie de la YouTube Data API v3. OAuth 2.0, scopes `https://www.googleapis.com/auth/youtube` ou `youtube.force-ssl`. Cycle de vie (« Life of a Broadcast ») : `liveBroadcasts.insert` (créer broadcast) → `liveStreams.insert` (créer flux) → `liveBroadcasts.bind` (associer) → `liveBroadcasts.transition` vers `testing` puis `live` puis `complete`. Statistiques et statut via `liveBroadcasts.list` / `liveStreams.list`.

**Quota (page officielle « Quota Calculator », dernière MAJ 2026-06-01).** La doc officielle Google précise : *« Projects that enable the YouTube Data API have a default quota allocation of 100 search.list calls, 100 videos.insert calls, and 10,000 units per day combined for all other endpoints. »* Coûts d'écriture des méthodes Live : `liveBroadcasts.insert` = 50, `liveStreams.insert` = 50, `bind` = 50, chaque `transition` = 50. **Cycle Live complet** = insert(50) + insert(50) + bind(50) + transition live(50) + transition complete(50) = **250 unités**, soit **~40 Lives/jour** sur le quota par défaut (calcul confirmé méthode par méthode). Les lectures de statut (`list` = 1) sont négligeables.

**Problème du quota partagé par projet.** Le quota est **par projet Google Cloud, pas par utilisateur**. Si de multiples influenceurs déclenchent des Lives via la même app Barkeelu, ils **partagent les 40 Lives/jour**. Solutions : (1) demander une extension via le formulaire « YouTube API Services - Audit and Quota Extension » (nécessite audit de conformité, délais de plusieurs semaines, non garanti) ; (2) sharding multi-projets Google Cloud (chaque projet = 10 000 u/j) avec routage — mais chaque projet doit respecter les ToS ; (3) limiter la création automatique de Lives et privilégier la programmation manuelle par l'influenceur pour les gros volumes. **Gratuit** (pas de frais par appel).

## 9. TikTok API / LIVE

**Faits documentés (developers.tiktok.com + support officiel, vérifié 2 sept. 2026).** Produits officiels : **Login Kit** (OAuth), **Content Posting API** (publication vidéos/photos — PAS de LIVE), **Display API** (`GET /v2/user/info/`, listing vidéos), **Research API** (chercheurs approuvés uniquement), **Commercial Content API**, **Data Portability API**, **Share Kit**, **Green Screen Kit**, TikTok API for Business (pub/shop). Scopes ex. `user.info.basic`, `video.list`. Apps soumises à audit ; apps non auditées = quotas/scoping très restreints.

**Il n'existe AUCUNE API TikTok LIVE officielle** — confirmé par de multiples sources dont les libs tierces elles-mêmes : *« TikTok does not offer a public official API for reading livestream events »*. Les « TikTok LIVE API » du marché (TikTokLive/isaackogan, tiktok-live-connector, EulerStream, tik.tools) sont **non officielles, en reverse-engineering du WebSocket Webcast**, en lecture seule (chat/gifts/viewers), et **ne permettent PAS de créer/piloter un LIVE ni d'obtenir une stream key**. À proscrire en production Barkeelu (risque de rupture + AGPL/conditions).

**Accès TikTok LIVE (confirmé).** Conditions : compte en règle, 18 ans+ pour les gifts, **1 000 abonnés** pour LIVE mobile (variable selon région). **TikTok LIVE Studio (Windows)** — seuils officiels, verbatim page TikTok : *« Gaming creators can use LIVE Studio once they reach 1,000 followers!… Non-gaming creators need a minimum of 10,000 followers to have access to LIVE Studio. »* LIVE Studio **n'expose généralement PAS de stream key copiable** pour OBS/Restream.

**Stream key pour encodeur (OBS/Restream) — verdict.** Deux seules voies, toutes deux fermées pour un influenceur religieux/artiste sénégalais typique : (a) outils tiers → **50 % de contenu gaming exigé depuis le 7 juillet 2025** (Restream, Streamlabs, StreamElements) ; (b) TikTok LIVE Studio → **10 000 abonnés non-gaming** et pas de key exportable. **Voie MCN / agence LIVE :** TikTok opère un programme officiel **« TikTok LIVE Creator Networks »** (agences partenaires). Des agences (MENA via TCE ; activité en Afrique du Sud) recrutent mondialement et prétendent débloquer l'accès encodeur, mais **aucune agence TikTok LIVE officielle dédiée au Sénégal / Afrique de l'Ouest n'a pu être vérifiée**, et les promesses d'accès stream key hors seuils sont des affirmations marketing d'agences, non endossées par TikTok.

**Disponibilité au Sénégal.** TikTok LIVE gifting est décrit comme conditionné à la localisation ; **TikTok ne publie pas de liste officielle confirmant l'activation au Sénégal** (probable mais officiellement non confirmé). Le Creator Rewards Program (paiement aux vues) est confirmé dans seulement 8 pays au 26 juillet 2026 — États-Unis, Royaume-Uni, Allemagne, France, Japon, Corée du Sud, Brésil et Mexique (Mexique ajouté le 1er octobre 2025) — **Sénégal exclu**.

**Conclusion TikTok pour Barkeelu :** ne jamais supposer une LIVE API. **Traiter TikTok comme un canal LIVE natif mobile** piloté par l'influenceur, où Barkeelu n'apporte QUE le QR code + lien tracké + écran compagnon. Pas d'adapter TikTok.

## 10. Comparaison technique (matrice notée)

Notation /5 (adéquation Barkeelu : automatisation, API, conformité au principe « pas de vidéo », TikTok non-gaming).

| Critère | Restream | Castr | StreamYard | OneStream | YouTube API | Self-hosted (Ant/MediaMTX) |
|---|---|---|---|---|---|---|
| API publique réelle | 5 | 5 (Premium+) | 0 | 2 (non confirmée) | 5 | 4 |
| OAuth / auth | 5 | 4 | 0 | 2 | 5 | 3 |
| Webhooks / WebSocket | 5 | 4 | 0 | 2 | 3 (push notif) | 3 |
| Multistream réseaux sociaux | 5 | 5 | 4 | 5 | 1 (YT seul) | 3 (Enterprise) |
| TikTok non-gaming | 1 | 1 | 1 | 1 | 0 | 1 |
| Conformité « pas de vidéo » (fournisseur porte le flux) | 5 | 5 | 5 | 5 | 5 | 1 |
| Automatisation multi-influenceurs | 2 | 4 | 0 | 3 | 3 | 3 |
| Coût prévisibilité | 4 | 4 | 3 | 4 | 5 | 2 |
| **Total /40** | **32** | **32** | **13** | **24** | **27** | **20** |

Lecture : **Castr et Restream ex æquo** ; Castr l'emporte pour l'orchestration multi-flux programmatique, Restream pour la simplicité OAuth + WebSocket temps réel. YouTube API = socle gratuit incontournable. Self-hosted pénalisé par la violation du principe.

## 11. Comparaison économique

Hypothèses : Live ≈ 2 h à 4 Mbps ; multistream 4 destinations ⇒ ~14,4 Go d'egress/Live. Conversions : USD×575, EUR×655,957.

**Modèle (a) — Barkeelu porte les abonnements centralement (le fournisseur transporte la vidéo).**

| Lives/mois | Solution centrale | Coût SaaS/mois | + Infra data (OVH VPS-3) | Total ≈ XOF/mois |
|---|---|---|---|---|
| 10 | Castr Starter 19,99 $ | 19,99 $ | 16,99 € | ~22 700 XOF |
| 50 | Castr Premium 199,99 $ (API+10 flux) | 199,99 $ | 16,99 € | ~126 000 XOF |
| 100 | Castr Premium 199,99 $ | 199,99 $ | 16,99 € | ~126 000 XOF |
| 500 | Castr Ultra 349,99 $ (15 flux, 60 To) | 349,99 $ | VPS dédié 35 € | ~224 000 XOF |
| 1 000 | Castr Ultra + Enterprise (custom) ou 2× Ultra | ~700 $ | 35 € | ~425 000 XOF |

Le limiteur n'est pas les Lives/mois mais la **concurrence de pics** et la bande passante egress. Add-ons (sièges, bande passante) à prévoir. Restream centralisé serait moins adapté (modèle fan-out mono-diffuseur).

**Modèle (b) — chaque influenceur apporte son abonnement.**

| Lives/mois | Coût Barkeelu (infra data uniquement) | Coût porté par influenceurs | Total Barkeelu ≈ XOF/mois |
|---|---|---|---|
| 10 | OVH VPS-3 16,99 € | Chacun son plan (Free/Standard) | ~11 150 XOF |
| 50 | VPS-3 16,99 € | idem | ~11 150 XOF |
| 100 | VPS-3 + Reverb scaling ~35 € | idem | ~23 000 XOF |
| 500 | 2× VPS + Redis managé ~70 € | idem | ~46 000 XOF |
| 1 000 | Cluster Reverb + monitoring ~120 € | idem | ~79 000 XOF |

**Postes additionnels (les deux modèles)** : développement initial (Live Data API + Overlay + 1–2 adapters) mobilisant l'équipe existante ; monitoring (Sentry/Prometheus/Grafana, ~0–50 $/mois) ; support ; stockage recordings (si conservés, sur S3-compatible ~3,99 €/To/mois).

**Verdict économique tranché : le modèle (b) est ~5 à 10× moins cher pour Barkeelu et scale indépendamment du nombre de Lives**, car le coût vidéo (le plus lourd) est externalisé chez le fournisseur de chaque influenceur. Le modèle (a) n'a de sens que pour un service premium « clé en main » facturé à l'influenceur.

## 12. Architecture recommandée

Deux chaînes strictement séparées.

**Chaîne vidéo (Barkeelu ne la touche jamais) :**
`Influenceur (OBS / mobile / studio fournisseur) → [Restream / Castr / YouTube API / futur self-hosted] → TikTok + YouTube + Facebook + Instagram`

**Chaîne données (cœur de Barkeelu) :**
`Barkeelu Campaign → Barkeelu Live Engine → Live Data API → Live Overlay (Browser Source) → OBS/Restream/Castr`

Le **Live Engine** orchestre optionnellement la création de Live via **Provider Adapters** (pattern Strategy) : `YouTubeAdapter` (officiel, prioritaire), `RestreamAdapter` (OAuth + WebSocket), `CastrAdapter` (REST + webhooks), `NullAdapter/NativeAdapter` (TikTok/Instagram natifs = pas de pilotage, seulement QR/lien). **Anti-vendor-lock-in** : interface `LiveProviderInterface` commune (`createBroadcast`, `bind`, `start`, `stop`, `getStats`), configuration par campagne, aucune dépendance dure à un fournisseur.

## 13. Live Data API

Endpoint conceptuel confirmé :
```
GET /api/v1/campaigns/{campaign}/live-data
{ "campaign": "Soutien aux Daaras 2026", "goal": 50000000, "collected": 37450000,
  "percentage": 74.9, "donors": 8452, "remaining": 12550000, "currency": "XOF", "status": "LIVE" }
```
**Auth :** clés API (overlay public en lecture seule via token signé à courte durée, scope minimal) + OAuth 2.0 pour l'espace influenceur. **Webhooks/events** sortants (don confirmé, objectif atteint, Live démarré/terminé) signés HMAC. **Redis** : cache de l'état de campagne + pub/sub. **WebSockets** : diffusion `DonationConfirmed`/`LiveDataUpdated`. **Idempotence** : clé d'idempotence par transaction de don (le ledger est la source de vérité ; l'overlay ne fait que refléter le ledger). **Rate limiting** : throttle par token overlay (ex. 60 req/min en fallback polling). **Audit & observabilité** : journal des accès, métriques de latence de diffusion, traçage des events. **Sécurité XOF** : entiers zéro décimale, jamais de flottant pour les montants (le `percentage` seul est calculé).

## 14. Live Overlay (dont solution mobile natif)

**Bonnes pratiques temps réel :** WebSocket (Laravel Echo + Reverb) en canal principal ; **fallback polling** toutes 5–10 s si la socket tombe ; reconnexion avec backoff exponentiel ; debounce des mises à jour (agréger par fenêtre de ~1 s sur les gros pics de dons) ; horodatage serveur ; affichage optimiste borné par réconciliation avec le ledger. Overlay servi en page web transparente (Browser Source), déclinée **16:9** (desktop/OBS) et **9:16** (vertical), avec QR code généré côté serveur (lien tracké UTM/short-link).

**WebSocket layer — comparaison (vérifié 2 sept. 2026).**
- **Laravel Reverb** (recommandé) : first-party (Laravel 11+), gratuit, self-hosted, protocole Pusher, scaling horizontal via Redis. Coût = opérationnel (process supervisé). Intégration Forge disponible.
- **Pusher** : SaaS managé, zéro ops, mais facturation par connexions ; pertinent si l'on veut externaliser l'infra.
- **Soketi** : open-source compatible Pusher (uWebSockets.js) ; économique mais **question de maintenance** en 2026 que Reverb évite.
- Tous parlent le protocole Pusher ⇒ **code client identique (laravel-echo)**, ce qui est en soi l'assurance anti-lock-in.

**Recommandation : Laravel Reverb par défaut** (cohérence stack, coût, contrôle), migration possible vers Pusher/managé si l'ops devient un fardeau.

**PROBLÈME MOBILE NATIF (TikTok, Instagram) — le Browser Source ne fonctionne pas.** Sur un Live natif mobile, aucun overlay web n'est incrustable. Options évaluées :

| Option | Faisabilité | Verdict |
|---|---|---|
| **Écran compagnon dans l'app Flutter** (2ᵉ téléphone/tablette affichant l'overlay, posé dans le champ) | Élevée | **Recommandé.** Simple, robuste, aucun accès vidéo requis ; l'influenceur cadre le QR/compteur. |
| **QR code physique + lien en bio/épinglé** | Élevée | **Recommandé (complément).** Toujours valide, hors dépendance technique. |
| **Incrustation côté encodeur mobile** (apps type Streamlabs mobile avec overlays) | Moyenne | Possible sur Android via app supportant overlays web, mais bute sur l'accès stream key TikTok. |
| **Partage d'écran** | Faible | Casse l'expérience Live natif. |
| **Overlay natif dans l'app Flutter de Barkeelu diffusant elle-même** | Faible/hors principe | Reviendrait à transporter la vidéo. À éviter. |

**Solution mobile retenue : mode « écran compagnon » Flutter + QR/lien tracké**, l'influenceur affichant physiquement le compagnon (deuxième écran) dans le cadre de son Live natif. C'est la seule voie conforme au principe et indépendante des plateformes.

## 15. Sécurité / Scalabilité

Tokens overlay à courte durée de vie, scope lecture seule, révocables par campagne. Secrets fournisseurs (OAuth Restream/Castr/YouTube) chiffrés au repos (Laravel encrypted casts / vault). Webhooks entrants (dons) validés par signature. Rate limiting + WAF sur l'API publique. Reverb derrière TLS, scaling horizontal via Redis pub/sub ; PostgreSQL en lecture répliquée si besoin. Idempotence sur toute la chaîne don→ledger→overlay. Observabilité : logs structurés, métriques de latence de diffusion, alerting sur décrochage WebSocket. Conformité : respecter les ToS de chaque plateforme (la distribution vers TikTok/YouTube/FB reste soumise à leurs règles).

## 16. Risques

- **TikTok (élevé) :** pas d'API LIVE, pas de stream key non-gaming, disponibilité gifting Sénégal non confirmée officiellement. → Mitigation : QR/lien natif, écran compagnon ; ne jamais coder d'adapter LIVE TikTok.
- **Quota YouTube partagé (moyen-élevé) :** 40 Lives/j/projet. → Sharding multi-projets + demande d'extension + programmation manuelle pour gros volumes.
- **Contradiction principe vs self-hosted (moyen) :** tout relais transporte la vidéo. → Abandon par défaut, exception V3 documentée.
- **Vendor lock-in / changements tarifaires (moyen) :** Restream se réserve le droit de modifier prix/facturer des services gratuits sans préavis ; hausses OVH/Hetzner 2026 (RAM/NAND). → Interface adapter commune, contrats mensuels, multi-cloud.
- **Prix SaaS contradictoires entre agrégateurs (faible-moyen) :** vérifier systématiquement sur pages officielles (Restream page officielle non fetchable ici → à reconfirmer manuellement avant contractualisation).
- **Latence Europe↔Afrique (faible) :** overlay data léger, peu sensible ; vidéo hors périmètre Barkeelu.

## 17. Roadmap

- **V1 (fondation, sans fournisseur) :** Live Data API + Live Overlay 16:9 & 9:16 + Reverb/Redis/WebSocket + fallback polling + QR/lien tracké + écran compagnon Flutter. Testable avec **OBS seul**. Modèle économique (b).
- **V2 (adapter officiel gratuit) :** `YouTubeAdapter` (OAuth, create/bind/transition, stats), sharding multi-projets Google Cloud, demande d'extension de quota.
- **V3 (adapter SaaS conditionné à l'économie) :** `RestreamAdapter` (OAuth + WebSocket statut) et/ou `CastrAdapter` (REST + webhooks) pour l'offre premium « clé en main » (modèle a). Décision go/no-go sur ROI.
- **V4 (exception éventuelle) :** relais self-hosted (MediaMTX/Ant Media Enterprise) uniquement si besoin souveraineté/latence avéré et dérogation validée.

**Benchmarks de décision :** passer à (a)/Castr si >30 % des influenceurs demandent une diffusion clé en main ; demander l'extension de quota YouTube si >30 Lives/j en moyenne ; rouvrir self-hosted seulement si la latence overlay/vidéo devient un blocage mesuré.

## 18. Recommandation finale

1. **Construire Barkeelu Live comme une plateforme de DONNÉES temps réel, jamais de vidéo.** Live Data API + Overlay + Reverb, indépendants de tout fournisseur.
2. **Adopter le modèle économique (b)** — chaque influenceur apporte son abonnement — comme défaut : coût Barkeelu ~11 000–79 000 XOF/mois quelle que soit la volumétrie. Réserver le modèle (a)/Castr Premium à une offre premium facturée.
3. **YouTube = seul adapter officiel prioritaire** (gratuit, documenté), avec gestion du quota partagé.
4. **Restream/Castr = optionnels, via interface adapter anti-lock-in** ; Castr préféré pour l'orchestration multi-flux programmatique.
5. **StreamYard et self-hosted écartés** comme adapters (pas d'API ; violation du principe vidéo).
6. **TikTok = LIVE natif mobile + QR/lien tracké + écran compagnon Flutter uniquement.** Ne jamais présumer d'une API LIVE ; ne pas dépendre d'agences non vérifiées.
7. **Valider le séquencement proposé** (Overlay → YouTube → Restream/Castr → TikTok natif) : il est confirmé comme optimal.

## Caveats
- La page tarifaire officielle restream.io/pricing n'a pas pu être capturée intégralement ici ; les tarifs Restream (Standard 16 $, Professional 39 $, Business 199 $ annuels) proviennent du centre d'aide officiel et de sources datées juin 2026 mais **doivent être reconfirmés sur la page officielle avant contractualisation** (les agrégateurs SEO se contredisent fortement).
- Disponibilité de TikTok LIVE gifting au Sénégal : **probable mais non confirmée officiellement** par TikTok.
- Taux USD/XOF : divergence entre feeds (565–575) ; parité EUR/XOF fixe à 655,957 (certaine).
- Existence d'une API OneStream Live : non confirmée officiellement.
- Les chiffres économiques sont des estimations d'ordre de grandeur fondées sur des hypothèses de bitrate/durée explicites ; à affiner avec les volumétries réelles.