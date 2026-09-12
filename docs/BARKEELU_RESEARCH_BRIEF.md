# Barkeelu — Brief de recherche stratégique Fundraising Sénégal

**Version : 0.1 — 1 septembre 2026**

## 1. Objet

Barkeelu est une plateforme sénégalaise de fundraising/crowdfunding inspirée de GoFundMe, Ulule et des meilleures plateformes internationales, mais conçue prioritairement pour l'Afrique de l'Ouest et la diaspora.

Ce document sert de brief commun pour des recherches indépendantes avec Claude Opus et Google DeepSearch. Les rapports produits seront ensuite comparés et consolidés.

## 2. Vision

Construire une infrastructure de collecte permettant à un particulier, promoteur, association, ONG, organisation ou porteur de projet de :

- créer une campagne ;
- recevoir des dons ;
- mobiliser sa communauté ;
- suivre les contributions ;
- publier des mises à jour ;
- recevoir les fonds selon des règles vérifiées ;
- utiliser des paiements locaux et internationaux ;
- promouvoir sa campagne via Telegram, WhatsApp et réseaux sociaux.

## 3. Modèle économique retenu

Hypothèse commerciale actuelle :

- **4 % Barkeelu sur chaque transaction payée par le donateur** ;
- **1 % pour le transfert Wave vers le promoteur/bénéficiaire** ;
- soit **5 % de frais au total** dans le modèle standard ;
- le promoteur de campagne pourrait recevoir une rémunération de **1 à 1,5 % de la somme recueillie après les frais**, selon les règles commerciales à confirmer.

Exemple indicatif pour 100 000 FCFA :

```text
Don brut                         100 000
Frais Barkeelu 4 %                4 000
Frais transfert Wave 1 %          1 000
Solde après frais                95 000

Commission promoteur 1 %            950
Commission promoteur 1,5 %        1 425
```

**Point à vérifier dans l'étude :** déterminer juridiquement, comptablement et commercialement la base exacte de calcul de la commission promoteur et éviter toute double comptabilisation.

## 4. Références concurrentielles

### Principal concurrent Sénégal : Kopar Express

La recherche initiale montre que Kopar Express se positionne comme fintech africaine reliant l'Afrique et sa diaspora, avec notamment financement participatif, transfert d'argent et services financiers.

Sa page crowdfunding met en avant :

- création gratuite de collecte ;
- vérification d'identité ;
- objectif et durée ;
- paiements Wave, Orange Money, Free Money et carte ;
- utilisation depuis l'Afrique et la diaspora ;
- retrait des fonds.

La page Kopar Collect annonce actuellement l'absence de frais de plateforme sur les collectes et un modèle de pourboire volontaire, avec frais d'agrégateurs annoncés séparément.

Des pages de collecte observées montrent également :
- paiement Orange Money ;
- paiement Wave ;
- Sendwave ;
- virement bancaire ;
- pourboire volontaire à Kopar ;
- commentaires/contributions ;
- catégories de collecte.

**Ces informations doivent être vérifiées et datées dans l'étude approfondie.**

### Références internationales

Étudier en profondeur :

- GoFundMe ;
- Ulule ;
- Donorbox ;
- FundRazr ;
- Open Collective ;
- GiveWP ;
- Charitable ;
- Kickstarter si pertinent ;
- Patreon/Ko-fi uniquement pour les modèles récurrents ;
- autres plateformes africaines pertinentes.

### Référence technique

- RiseLab Crowdfunding Platform — CodeCanyon.

RiseLab doit être analysé comme accélérateur/référence fonctionnelle potentielle, et non comme architecture imposée à Barkeelu.

## 5. Stack technique envisagée

### Backend

**Laravel + PostgreSQL**

Raison principale : Barkeelu doit pouvoir réutiliser les fondations Yessal ERP déjà développées :

```text
Core SaaS
├── Users
├── Organizations
├── Plans
├── Subscriptions
├── Entitlements
├── Quotas
├── Payments
└── Multi-tenancy
```

Yessal ERP utilise actuellement Laravel 13, PHP 8.3, Sanctum et PostgreSQL.

### Frontend

**Flutter**

Cibles :

- Android ;
- iOS ;
- Windows ;
- macOS ;
- Web.

Le Web devra être évalué séparément pour les besoins SEO des pages publiques de campagnes.

### Design

Google Stitch sera utilisé pour explorer/prototyper le design system et les interfaces avant implémentation Flutter.

### Bot

**Python + Telegram Bot**

Le bot servira notamment à :

- informer ;
- alerter ;
- notifier ;
- promouvoir les campagnes ;
- diffuser les campagnes tendances ;
- notifier les promoteurs ;
- automatiser certaines actions marketing.

Le bot ne doit pas accéder directement à PostgreSQL : communication via API/events/queues.

### Gamification

Prévue ultérieurement, inspirée de GamiPress :

- points ;
- badges ;
- niveaux ;
- achievements ;
- classements ;
- challenges ;
- récompenses ;
- referrals.

## 6. Architecture métier cible

```text
Barkeelu
│
├── Yessal Core SaaS
│
├── Fundraising Engine
│   ├── Campaign
│   ├── Beneficiary
│   ├── Promoter
│   ├── Team
│   ├── Update
│   ├── Referral
│   └── Verification
│
├── Donation Engine
│   ├── Donor
│   ├── Donation
│   └── Recurring Donation
│
├── Payment Engine
│   ├── Payment
│   ├── Provider
│   ├── Webhook
│   └── Reconciliation
│
├── Financial Engine
│   ├── Ledger
│   ├── Ledger Entry
│   ├── Fee
│   ├── Commission
│   └── Payout
│
├── Trust & Safety
│   ├── KYC
│   ├── Verification
│   ├── Moderation
│   └── Fraud/Risk
│
├── Notification Engine
│   ├── Telegram
│   ├── WhatsApp
│   ├── Email
│   └── Push
│
└── Gamification
```

## 7. Principe financier fondamental

Ne pas traiter le crowdfunding comme un simple module e-commerce.

Le modèle financier doit séparer :

```text
Payment
   ↓
Donation
   ↓
Ledger
   ↓
Fees / Commissions
   ↓
Available Balance
   ↓
Payout
```

Chaque transaction doit être traçable et réconciliable.

## 8. Questions prioritaires pour la recherche

### Marché sénégalais

1. Taille actuelle du marché du crowdfunding/fundraising au Sénégal.
2. Nombre et profil des utilisateurs potentiels.
3. Causes les plus fréquentes.
4. Montants moyens des collectes.
5. Taux de réussite des campagnes.
6. Diaspora comme source de financement.
7. Paiements locaux les plus utilisés.
8. Freins à l'adoption.
9. Confiance et fraude.
10. Besoins spécifiques des associations/ONG.
11. Besoins des campagnes médicales.
12. Besoins des campagnes scolaires.
13. Besoins religieux/communautaires.
14. Besoins entrepreneuriaux.
15. Besoins événementiels.
16. Besoins politiques/associatifs, en tenant compte des contraintes juridiques.

### Kopar Express

Analyser :

- positionnement ;
- historique ;
- fondateurs/actionnaires si information publique fiable ;
- modèle économique ;
- tarifs actuels ;
- moyens de paiement ;
- UX ;
- application mobile ;
- pages publiques ;
- création de campagne ;
- KYC ;
- validation/modération ;
- décaissement ;
- délais ;
- support client ;
- API éventuelle ;
- technologie détectable ;
- SEO ;
- acquisition ;
- réseaux sociaux ;
- diaspora ;
- catégories ;
- fonctionnalités ;
- avantages ;
- faiblesses ;
- avis utilisateurs ;
- incidents publics ;
- conformité réglementaire ;
- partenariats ;
- évolutions récentes.

**Ne jamais présenter des avis utilisateurs comme des faits établis. Les classer comme signaux de perception.**

### Modèle économique

Comparer :

- frais plateforme ;
- frais paiement ;
- frais retrait ;
- frais donateur ;
- pourboires ;
- commissions promoteurs ;
- dons récurrents ;
- abonnements ;
- frais de change ;
- frais diaspora.

## 9. Questions produit

Déterminer si Barkeelu doit proposer :

### MVP

- campagne ;
- objectif ;
- durée ;
- image/vidéo ;
- don unique ;
- paiement Wave ;
- Orange Money ;
- Free Money ;
- carte ;
- page publique ;
- partage ;
- commentaires ;
- mises à jour ;
- KYC ;
- modération ;
- dashboard promoteur ;
- payout ;
- notifications.

### V1

- dons récurrents ;
- équipes ;
- fundraiser individuel ;
- referral tracking ;
- QR code ;
- WhatsApp ;
- Telegram ;
- campagnes vérifiées ;
- badges ;
- statistiques avancées ;
- multi-bénéficiaires ;
- diaspora.

### V2

- contreparties ;
- crowdfunding tout-ou-rien ;
- précommandes ;
- abonnements ;
- gamification ;
- API publique ;
- intégrations ONG ;
- campagnes internationales ;
- multi-devises.

## 10. Comparaison Laravel / Django

Évaluer objectivement Laravel et Django pour :

- fundraising ;
- paiements ;
- ledger ;
- multi-tenant ;
- sécurité ;
- KYC ;
- queues ;
- notifications ;
- API ;
- Flutter ;
- administration ;
- IA ;
- intégration Python ;
- maintenance ;
- recrutement ;
- coûts ;
- réutilisation de Yessal ERP.

L'hypothèse actuelle est **Laravel**, mais elle doit être contestée par la recherche.

## 11. Aimeos / Lunar / RiseLab

Comparer :

### Aimeos
- marketplace ;
- paiement ;
- multi-vendeur ;
- catalogue ;
- extensibilité ;
- complexité ;
- pertinence crowdfunding.

### Lunar
- headless ;
- panier ;
- commandes ;
- paiements ;
- catalogue ;
- multi-channel ;
- extensibilité ;
- pertinence crowdfunding.

### RiseLab
- campagnes ;
- dons ;
- KYC ;
- payouts ;
- commissions ;
- referral ;
- notifications ;
- gateways ;
- administration ;
- code Laravel ;
- qualité du code ;
- maintenabilité ;
- possibilité d'intégration avec Yessal.

**Question principale :**
Faut-il adapter RiseLab, utiliser Lunar/Aimeos comme composant, ou construire un Barkeelu Fundraising Engine custom ?

## 12. Analyse concurrentielle attendue

Produire une matrice :

```text
Fonction
Barkeelu
Kopar Express
GoFundMe
Ulule
RiseLab
Donorbox
FundRazr
Open Collective
```

Notation :

```text
0 = absent
1 = faible
2 = basique
3 = correct
4 = avancé
5 = excellent
```

Évaluer au minimum :

- UX ;
- campagne ;
- dons ;
- paiements ;
- mobile money ;
- diaspora ;
- KYC ;
- vérification ;
- modération ;
- payouts ;
- transparence ;
- frais ;
- récurrence ;
- referral ;
- gamification ;
- social ;
- Telegram ;
- WhatsApp ;
- analytics ;
- API ;
- administration ;
- SEO ;
- confiance.

## 13. Analyse SWOT Barkeelu

Produire :

- forces ;
- faiblesses ;
- opportunités ;
- menaces.

Puis une stratégie :

```text
Comment Barkeelu peut-il prendre une position
différenciée au Sénégal face à Kopar Express ?
```

## 14. Questions critiques

La recherche doit particulièrement répondre à :

1. Le modèle 4 % + 1 % est-il compétitif ?
2. La commission promoteur 1–1,5 % est-elle économiquement viable ?
3. Qui doit réellement supporter les frais ?
4. Le payout doit-il être automatique ou manuel ?
5. Quel délai de payout est optimal ?
6. Comment réduire le risque de fraude ?
7. Quel niveau de KYC est nécessaire ?
8. Comment gérer les collectes médicales urgentes ?
9. Comment gérer la diaspora ?
10. Comment gérer les remboursements ?
11. Comment gérer les chargebacks cartes ?
12. Comment gérer les paiements mobile money non confirmés ?
13. Comment gérer les campagnes frauduleuses après publication ?
14. Comment construire la confiance ?
15. Quel avantage Barkeelu doit-il offrir que Kopar n'offre pas ?
16. Quelle niche lancer en premier ?

## 15. Sources exigées

Priorité :

1. sites officiels ;
2. documentation officielle ;
3. tarifs officiels ;
4. App Store / Google Play ;
5. registres et sources réglementaires ;
6. presse économique fiable ;
7. interviews ;
8. avis utilisateurs, uniquement comme signal ;
9. forums/réseaux sociaux en dernier recours.

Chaque donnée importante doit comporter :

- source ;
- URL ;
- date d'accès/publication si disponible ;
- niveau de confiance.

## 16. Livrables attendus du chercheur

Produire :

1. Executive Summary.
2. Étude du marché sénégalais.
3. Étude détaillée de Kopar Express.
4. Analyse GoFundMe.
5. Analyse Ulule.
6. Analyse RiseLab.
7. Analyse Donorbox.
8. Analyse FundRazr.
9. Analyse Open Collective.
10. Comparaison Laravel/Django.
11. Comparaison Aimeos/Lunar/custom.
12. Analyse économique du modèle Barkeelu.
13. Analyse réglementaire.
14. SWOT.
15. Positionnement.
16. MVP recommandé.
17. V1/V2 recommandées.
18. Risques.
19. Recommandations finales.
20. Bibliographie complète.

## 17. Règles méthodologiques

- Distinguer faits, hypothèses et recommandations.
- Ne pas inventer les chiffres.
- Signaler les données contradictoires.
- Donner les dates.
- Privilégier les informations récentes.
- Vérifier les informations sensibles auprès de plusieurs sources.
- Ne pas considérer une fonctionnalité détectée comme réellement disponible sans preuve.
- Ne pas confondre avis utilisateur et preuve de défaillance.
- Identifier les informations qui nécessitent une vérification juridique ou réglementaire.

## 18. Question finale

À partir de l'ensemble de la recherche :

> **Quelle architecture, quel modèle économique, quelles fonctionnalités et quel positionnement permettront à Barkeelu de devenir une plateforme de fundraising de référence au Sénégal puis en Afrique de l'Ouest, tout en conservant une architecture SaaS évolutive compatible avec l'écosystème Yessal ?**
