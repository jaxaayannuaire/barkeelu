# BARKEELU.COM — DOCUMENT MAÎTRE DU PROJET
## Vision, périmètre et décisions validées

**Version : 1.0 — 2 septembre 2026**

## 1. Vision
Barkeelu.com vise à devenir une infrastructure numérique sénégalaise de collecte de fonds, solidarité, transparence et gestion de programmes sociaux — au-delà d’un simple clone de GoFundMe.

Cibles : citoyens, familles, dahiras, associations, ONG, fondations, entreprises/RSE, sponsors, administrations, établissements de santé, mosquées, églises, écoles, bailleurs et partenaires.

## 2. Socle technique
- Laravel 13 / PHP 8.3
- PostgreSQL
- Redis / queues / events
- API REST
- WebSockets pour temps réel
- réutilisation du SaaS Core générique de Yessal ERP
- Laravel SSR pour pages publiques/SEO/partage
- Flutter : Android, iOS, Windows, macOS

Le business logic Yessal Caisse/POS n’est pas importé dans Barkeelu.

## 3. Moteurs
- Fundraising Engine
- Payment & Financial Engine
- Content & Media Engine
- Social / Influencer / Affiliate Engine
- Directory & Social Impact Engine
- Trust & Safety Engine
- Notification Engine
- API Platform
- Live Engine

## 4. Acteurs
User, Donor, Campaign Owner, Beneficiary, Fundraiser, Ambassador, SocialInfluencer.

Influenceurs : philanthropes, footballeurs/sportifs, artistes, créateurs de contenu, religieux, personnalités publiques, politiques, médias/pages communautaires, entrepreneurs, etc.

## 5. Organisations
Une entité Organization avec type :
NGO, ASSOCIATION, DAHIRA, FOUNDATION, COMPANY, RSE_NETWORK, MOSQUE, CHURCH, HOSPITAL, SCHOOL, GOVERNMENT, SOCIAL_SERVICE, COMMUNITY_GROUP, OTHER.

Une organisation peut gérer programmes, projets, campagnes, membres, contenus, sponsors, financements et statistiques.

## 6. Programmes / projets / campagnes
Program = regroupement de projets/campagnes, notamment pour État, ONG, fondations, RSE et bailleurs.

Project = action concrète : construction, rénovation, équipement, soins, alimentation, formation, urgence, etc.

Campaign = moteur de collecte.

Visibilités :
PUBLIC, UNLISTED, PRIVATE, TARGETED.

Statuts :
DRAFT, SUBMITTED, UNDER_REVIEW, REJECTED, PUBLISHED, PAUSED, SUSPENDED, GOAL_REACHED, ENDED, CLOSED, CANCELLED.

## 7. Collectes privées/ciblées
Cas : familles, groupes fermés, dahiras, associations, anciens élèves, groupes professionnels.

Flux :
Contacts → CSV/mobile → groupes → campagne ciblée → invitations → lien privé/QR → paiement.

Une campagne privée n’est pas automatiquement publiée dans l’annuaire public.

## 8. Paiements et finance
Moyens : Wave, Orange Money, cartes bancaires, autres PSP.

Confirmation des paiements par webhook fiable, jamais uniquement par retour navigateur.

Ledger double-entry dès MVP.

Flux :
Payment → Donation → Ledger → Fees → Available Balance → Payout.

Frais validés à ce stade :
- 4 % Barkeelu, payés par le donateur ;
- provision/coût de transfert Wave vers bénéficiaire/promoteur : 1 % selon modalités à confirmer contractuellement ;
- affichage indicatif total : 5 %.

Éviter toute marge cachée sur les frais de payout.

Séparer clairement Campaign Owner/Bénéficiaire, Fundraiser et Ambassador/Affiliate.

## 9. KYC / Trust & Safety
Entités : KYCProfile, RiskAssessment, ModerationCase, AuditLog.

Séparation des responsabilités financières obligatoire.

Preuves : PHOTO, VIDEO, DOCUMENT, RECEIPT, INVOICE, THIRD_PARTY_CONFIRMATION.

Prévoir Trust Score / Verification Status / Transparency Score séparément des notes utilisateurs.

## 10. Annuaire social
Composant stratégique listant notamment :
- meilleurs donateurs ;
- ambassadeurs ;
- influenceurs sociaux ;
- organisations ;
- sponsors ;
- ONG/associations ;
- dahiras ;
- fondations/RSE ;
- services sociaux ;
- administrations ;
- mosquées ;
- églises ;
- hôpitaux ;
- écoles ;
- structures communautaires.

Chaque fiche peut avoir profil, localisation, coordonnées, vérification, campagnes, programmes, statistiques, partenaires, services et contenus.

Le référentiel pourra progressivement intégrer des structures comme les daaras, sous réserve de validation institutionnelle des données.

## 11. Influenceurs
Entités : SocialInfluencer, SocialAccount, SocialPlatform, Referral, AffiliateLink.

Dashboard : campagnes, montant collecté, donateurs, portée, clics, conversions, partages, performances par réseau, QR codes, liens trackés et commissions.

Le barème commercial d’affiliation sera étudié séparément.

## 12. Contenu / actualités
Moteur de contenu inspiré des CMS :
Article, Update, Announcement, Report, Story, Live.

Association avec campagne/projet/programme/organisation/influenceur.

Fonctions : contenu riche, images, galeries, vidéos, liens, documents, tags, catégories, programmation, commentaires, partages, vues, notation et modération.

Une campagne doit rester vivante grâce à ses publications.

## 13. Notes et commentaires
Campagnes : notation 1–5 étoiles, commentaires, signalement et modération.

Rating ≠ Trust Score ≠ Verification Status ≠ Transparency Score.

## 14. Communauté / distribution
Un utilisateur peut suivre campagnes, organisations, influenceurs et programmes.

Canaux :
- newsletter ;
- email ;
- Telegram ;
- WhatsApp Channels ;
- push ;
- SMS.

Social Publishing Engine prévu pour publier automatiquement certains contenus vers Telegram, WhatsApp, Facebook et autres réseaux selon règles/API disponibles.

## 15. Barkeelu Live
Principe non négociable : Barkeelu ne doit pas héberger/distribuer le flux vidéo.

Fournisseurs/outils possibles :
- Restream — candidat SaaS principal provisoire ;
- Castr — alternative ;
- StreamYard — solution secondaire ;
- YouTube natif ;
- TikTok natif ;
- futurs fournisseurs/self-hosted.

Architecture :
Barkeelu Live Engine
├── Restream Adapter
├── Castr Adapter
├── YouTube Adapter
├── TikTok Adapter
└── Future Self-hosted Adapter

## 16. Live Data API / Overlay
Barkeelu expose les données dynamiques :
objectif, collecté, %, donateurs, restant, devise, statut, QR, URL.

Endpoint conceptuel :
GET /api/v1/campaigns/{campaign}/live-data

Live Overlay léger utilisable comme Browser Source dans OBS/Restream/Castr :
- 16:9 ;
- 9:16 ;
- transparent ;
- QR ;
- progression ;
- ticker ;
- branding.

Après donation confirmée :
Donation → Laravel Event → Redis/Queue → WebSocket → Overlay.

Fallback : polling.

Le flux vidéo ne passe pas par Barkeelu.

## 17. Réseaux prioritaires
TikTok et YouTube sont particulièrement importants pour l’audience sénégalaise.

Facebook, Instagram et autres restent des canaux complémentaires.

StreamYard et les Lives natifs YouTube/TikTok restent des solutions secondaires pour les organisateurs souhaitant diffuser sur une seule plateforme.

Les capacités TikTok LIVE/API doivent être vérifiées officiellement et ne doivent jamais être supposées.

## 18. API Barkeelu
API prévue dès V1.

Objectifs :
- campagnes ;
- programmes ;
- organisations ;
- projets ;
- donations ;
- paiements ;
- payouts ;
- rapports ;
- statistiques ;
- événements ;
- webhooks ;
- intégrations ;
- Live Data.

Barkeelu doit proposer progressivement :
1. Barkeelu SaaS ;
2. Barkeelu API ;
3. Barkeelu Enterprise ;
4. White-label.

## 19. État / ONG / bailleurs / entreprises
Barkeelu doit pouvoir servir de système de campagne et d’infrastructure de données pour :
- programmes sociaux de l’État ;
- ONG ;
- fondations ;
- entreprises/RSE ;
- bailleurs ;
- partenaires.

Cas d’usage : programme national → projets → campagnes → paiements → ledger → décaissements → preuves → indicateurs → rapports.

L’API doit faciliter les services comptables avec des données fiables et temps réel, sans exposer les données sensibles par l’API publique.

## 20. Funding / Grants
Entités prévues :
Funder, Funding, Grant, Program, Project, Campaign, ImpactMetric, Report.

Flux :
FUNDER → FUNDING/GRANT → PROGRAM → PROJECT → CAMPAIGN → DONATIONS/PAYMENTS → IMPACT.

## 21. Impact
ImpactMetric : objectif, cible, réalisé, période, région, bénéficiaires, preuves.

## 22. Entités principales
User
Organization
OrganizationMember
Beneficiary
Program
Project
Campaign
CampaignMember
Contact
ContactGroup
CampaignInvitation
Donation
Payment
PaymentMethod
Fee
LedgerAccount
LedgerEntry
Payout
Refund
Chargeback
Proof
CampaignUpdate
Content
Article
Comment
Rating
Tag
SocialInfluencer
SocialAccount
SocialPlatform
Referral
AffiliateLink
Commission
Sponsor
Funding
Grant
ImpactMetric
Report
DirectoryListing
SocialService
QRCode
Live
LiveProvider
LiveDestination
LiveOverlay
LiveEvent
APIClient
APIKey
Webhook
Integration
ExternalReference
KYCProfile
RiskAssessment
ModerationCase
AuditLog
Notification

## 23. Roadmap

### MVP
Utilisateurs, organisations, bénéficiaires, campagnes, dons, paiements, ledger, payouts, KYC, modération, preuves, contenu de base, annuaire de base, Wave, Orange Money, cartes, partage social, QR.

### V1
API Barkeelu, programmes, funding/grants, contacts, collectes privées/ciblées, influenceurs, affiliation, Live Engine, Restream/Castr, Live Data API, Live Overlay, YouTube integration, TikTok selon APIs disponibles, social publishing, Telegram, WhatsApp Channels, analytics, reporting, annuaire avancé.

### V2
Zakat, Waqf, dons récurrents, abonnements, all-or-nothing, rewards/pre-sale, multi-bénéficiaires, multi-devises, diaspora avancée, gamification, API avancée, white-label, infrastructure Live self-hosted, Grants/Impact avancés.

## 24. Principes non négociables
1. Ledger fiable et auditable.
2. Webhooks pour confirmation des paiements.
3. Idempotence financière.
4. Pas de vidéo obligatoire sur Barkeelu.
5. Provider Live interchangeable.
6. API dès V1.
7. API publique et privée séparées.
8. Données sensibles protégées.
9. Permissions granulaires.
10. Séparation des responsabilités.
11. Audit des actions sensibles.
12. Pas de frais bénéficiaire cachés.
13. Rating séparé de la confiance.
14. Contenu pour maintenir les campagnes actives.
15. Réseaux sociaux = distribution, pas source de vérité.
16. Anti-vendor-lock-in.
17. Adapters pour intégrations externes.
18. Programmes/projets pouvant regrouper plusieurs campagnes.

## 25. Prochaine étape
Étudier et valider Restream/Castr/StreamYard/Open Source + YouTube/TikTok, puis :
Domain Model consolidé → champs → cardinalités → ERD PostgreSQL → migrations Laravel → API V1 → roadmap de développement.
