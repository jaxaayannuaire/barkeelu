# Barkeelu — Recherche stratégique approfondie (Sénégal / Afrique de l'Ouest / Diaspora)

# Executive Summary
Barkeelu peut battre Kopar Express, mais **pas** en l'attaquant frontalement sur le prix (Kopar affiche « 0 % de commission plateforme + pourboire volontaire »). La victoire viendra d'un modèle **« confiance + décaissement rapide et fiable + diaspora + segmentation ONG/associations payantes »**, adossé à une architecture qui **ne détient PAS les fonds elle-même** (pour éviter l'agrément BCEAO lourd), construite sur un moteur **Laravel custom** avec un **site public en SSR** (pas Flutter Web) et des apps Flutter pour les utilisateurs connectés.

Trois corrections majeures au brief initial, à trancher en priorité :

1. **Le « 1 % Wave » du brief est mal attribué.** Ce n'est pas un frais de transfert vers le promoteur. Wave facture **1 % de commission marchand à l'ENCAISSEMENT, plafonné à 5 000 FCFA par transaction** ; le cash-out marchand vers banque est un **forfait plat** (~250 FCFA selon la documentation d'intégration Kolonell, à confirmer contractuellement). Il faut donc séparer strictement **frais d'encaissement PSP** (côté collecte, à la transaction) et **frais de payout/retrait** (côté décaissement) — deux moments et deux acteurs différents.

2. **L'effet de plafond ne joue QUE par transaction unitaire.** Une campagne de 10 000 000 FCFA composée de nombreux petits dons paie de fait ~1 % de frais PSP (chaque don < 500 000 FCFA n'atteint pas le plafond), **pas 0,05 %**. Le plafond de 5 000 FCFA ne protège que les gros dons unitaires. Toute modélisation qui suppose un coût PSP à 0,05 % sur une campagne à 10 M sera fausse.

3. **Détenir les fonds en escrow est une activité réglementée BCEAO depuis le 1er mai 2025.** Il faut soit un agrément (établissement de paiement, capital 10–100 M FCFA ; ou EME, 300 M FCFA), soit s'appuyer sur un partenaire agréé (Wave, Orange Money, PayDunya/Peach, InTouch). Barkeelu doit choisir la seconde voie au MVP.

## 1. Conclusion stratégique
- **VALIDÉ** : le marché est réel et massif — mobile money omniprésent et transferts de la diaspora à **2 211 milliards FCFA en 2024** (contre 1 600 Md en 2023, ~12 % du PIB), selon la BCEAO citée par l'APS (17 déc. 2025) : « Les transferts d'argent des expatriés sénégalais ont atteint des niveaux records de 2 211 milliards de francs CFA en 2024, plus que l'aide publique au développement ».
- **RECOMMANDÉ** : ne pas détenir les fonds ; s'appuyer sur Wave + un agrégateur (PayDunya/InTouch) ; site public en Laravel SSR ; app Flutter pour promoteurs/donateurs connectés ; ledger double-entrée dès le MVP.
- **À ÉVITER** : le 4 % linéaire non plafonné ; Flutter Web pour les pages publiques ; la confrontation « prix » directe avec Kopar ; l'hébergement de cagnottes politiques (risque documenté Kopar).
- **Différenciation** : décaissement rapide et fiable (le point faible documenté de Kopar), transparence de type ledger (inspirée d'Open Collective), et une couche diaspora + Telegram/WhatsApp.

## 2. Marché sénégalais
- **Comptes mobile money** : GSMA via Agence Ecofin : « entre 2013 et 2023, le nombre de comptes Mobile Money enregistrés au Sénégal a plus que quintuplé, passant de 7 à 38 millions » ; pénétration passée de 45 % à 210 % ; contribution de 8,6 % au PIB sénégalais en 2023 (Forbes Afrique).
- **Volume mobile money 2025** : ~15 300 milliards FCFA (~27 Md$) (Forbes Afrique).
- **Wave** : 7,18 M d'utilisateurs actifs au Sénégal, selon Coura Carine Sène, DG de Wave Sénégal (CIO Mag) : « près de 11 millions de clients actifs, dont environ 7,18 millions d'utilisateurs actifs, soit 90 % de la population adulte disposant d'un compte chez Wave Mobile Money ». Wave est décrit comme détenant ~70 % du mobile money sénégalais et 40 000+ marchands (Kolonell — source prestataire, à qualifier).
- **Orange Money** : 13 M de clients actifs et ~3,8 milliards de transactions en 2025, selon les résultats financiers 2025 du groupe Sonatel : « Orange Money totalise 13 millions de clients actifs, avec près de 3,8 milliards de transactions réalisées en 2025 » ; Orange détient ~55–57 % de part de marché télécom au Sénégal.
- **Pénétration mobile** : 128,69 % fin 2025 (ARTP via TechAfrica), marché essentiellement prépayé (98,3 %).
- **Bancarisation** : < 30 % des adultes ont un compte bancaire classique (BCEAO) ; 55 % des adultes utilisent des services financiers numériques et 59 % des femmes financièrement incluses dépendent exclusivement du mobile money (AFI).
- **Nouvelle taxe mobile money** : loi n° 2025-17 du 27 septembre 2025 modifiant le Code général des Impôts, en vigueur depuis octobre 2025 (Agence Ecofin) : « prélèvement à la source de 0,5 % du montant de chaque paiement reçu par les commerçants via des solutions électroniques ». Les sources divergent sur les taux exacts (Forbes évoque 0,5 % plafonné par décret ; Finance in Africa évoque un empilement 0,5 % transfert + 1,5 % marchand + 2 % additionnel). **À surveiller** : impact direct sur les coûts d'encaissement de Barkeelu, car un paiement à code marchand pourrait être taxé.

## 3. Fundraising au Sénégal
Les comportements de collecte existent déjà massivement : cagnottes médicales, religieuses (Touba Ca Kanam, daaras), scolaires/universitaires (UCAD, bourses étudiantes), GIE de femmes (Fatick, Saint-Louis), aide aux sinistrés d'inondations, rapatriements, diaspora. Aujourd'hui, la collecte se fait surtout via des **numéros Wave/Orange Money partagés sur WhatsApp, Facebook et TikTok** — informel, sans traçabilité, sans preuve d'usage des fonds, sans reçu. Le besoin réel des utilisateurs : **confiance, transparence, preuve d'affectation, et facilité de contribution en mobile money**. Le recours persistant à l'informel s'explique par l'absence d'alternative locale de confiance et par les frictions des plateformes existantes (payout lent chez Kopar). C'est précisément l'opportunité de Barkeelu : formaliser sans alourdir.

## 4. Analyse Kopar Express
**Entreprise** : fintech sénégalaise multi-services (cagnotte « Kopar Collect », paiement, transfert d'argent, factures, forfaits, marketplace « Vente Privée », interopérabilité). Co-fondateur/actionnaire **Seydou Nourou Ba** (~25 % du capital). Pays couverts : Sénégal, Côte d'Ivoire, Gabon ; **Tchad annoncé** (« bientôt »). Se présente comme opérant « avec des entités juridiques locales, en conformité avec les réglementations BCEAO et BEAC » — **auto-déclaration non vérifiée indépendamment**.

**Contexte judiciaire (FAIT vérifié / allégations, instruction en cours — pas de condamnation définitive confirmée)** : Seydou Nourou Ba a été incarcéré en 2023 dans le cadre de l'« affaire Hannibal Djim » (cagnottes liées à Pastef), sous des chefs de complot/atteinte à la sûreté de l'État. Litiges de rétention de fonds : **GIE pour la promotion des non-voyants** (non-reversement allégué de 10 966 948 FCFA, Ba invoquant un signalement de fraude carte via Stripe) ; **étudiants de l'UASZ** (2026, accusation de rétention d'une cagnotte de rapatriement). Cagnotte Pastef (oct. 2024) : objectif 500 M FCFA, incidents techniques dus à « plusieurs millions d'accès simultanés » (communiqué Kopar).

**Produit** : création de collecte en quelques minutes ; catégories (événement, cotisation, urgence, santé, éducation…) ; médias, storytelling, partage, mises à jour, prolongation de durée ; **décaissement partiel dès qu'un seuil est atteint** ; vérification d'identité du bénéficiaire une seule fois.

**Paiements** : Wave, Orange Money, Free Money, MTN, Moov CI, Visa/Mastercard ; contributeurs depuis l'Afrique et la diaspora.

**Modèle économique** : 0 % de commission plateforme + pourboire volontaire. Verbatim koparexpress.org : « À part les frais de l'opérateur de paiement ou de retrait, Kopar Express ne prend pas des frais supplémentaire… Nous préférons vous laisser le choix de nous laisser un pourboire pour nos frais (amélioration du site, vérification des collectes…) ». **Analyse critique** : ce modèle « 0 % + pourboire » n'est soutenable qu'à grande échelle et avec un coût d'encaissement très bas ; il explique probablement la pression sur la trésorerie et les délais de décaissement observés. Il n'est **pas** attaquable frontalement par un concurrent qui afficherait 4–6 %.

**Payout** : confirmé sur koparexpress.org/crowdfunding : mobile money (Wave/Orange/Free/MTN) « Délai : entre 3 et 7 jours. Frais : 1 % » ; banque UEMOA/CEMAC « Délai : 3 à 7 jours ouvrés. Frais : 1,5 % ». Vérification d'identité unique ; décaissement partiel dès seuil.

**Réputation (SIGNAL utilisateur — à ne PAS présenter comme preuve d'un problème systémique)** : avis récurrents sur App Store/Google Play évoquant lenteurs ou absence de décaissement et support injoignable — verbatim : « Tu peux facilement créer une collecte mais tu ne pourras jamais décaisser l'argent », « Pourquoi votre délai de décaissement est trop long ? ». Ces signaux, combinés aux litiges civils documentés, désignent **le décaissement comme la faiblesse stratégique n°1** à exploiter.

**Technologie** : apps natives iOS/Android + site web ; app annexe « koparma » (interop Wave↔Orange Money à 50 FCFA). Détails de stack non vérifiables publiquement — **ne rien déduire**.

## 5. Analyse GoFundMe
0 % de frais plateforme (US) ; frais de traitement **2,9 % + 0,30 $/don** (2,2 % + 0,30 $ pour les organismes caritatifs éligibles) ; revenus via **pourboires optionnels** du donateur ; dons récurrents à 5 %. Équipe Trust & Safety dédiée, payout via Stripe, pas de durée imposée. **Leçon** : le « 0 % + tip » fonctionne à très grande échelle avec un processeur bon marché ; difficile à répliquer avec les coûts africains sans volume.

## 6. Analyse Ulule
Modèle **tout-ou-rien** ; commission porteur **5 % (virement/chèque) à 8 % (CB)** ; **frais de service contributeur ~2,3 %** (100 € → 102,30 € TTC, depuis le 14 avril 2025) ; remboursement intégral si l'objectif n'est pas atteint (aucune commission alors) ; **validation éditoriale** des projets ; accompagnement (« Camp de Base »). Certifiée B Corp. **Leçon** : contreparties/prévente + curation = qualité et engagement, mais lourd pour un marché dominé par les dons de solidarité — à réserver au segment créatif/entrepreneurial (V2).

## 7. Analyse RiseLab
Script CodeCanyon de **ViserLab** : Laravel + Bootstrap + jQuery ; KYC, 40+ passerelles automatiques + passerelles manuelles, **retraits manuels avec approbation admin**, multi-devise, page builder, SEO manager, tickets support. Dernière version majeure connue ~mars 2023, PHP 8.x. **Verdict : À ÉVITER comme fondation.** Architecture jQuery datée, aucune passerelle mobile money ouest-africaine native (Wave/OM/Free), pas de ledger double-entrée, dette technique et sécurité non auditables, licence d'enveloppe verrouillante. **Utile uniquement comme référentiel d'inventaire fonctionnel**, pas comme socle de production.

## 8. Analyse Donorbox
Plateforme orientée **dons récurrents** ; frais plateforme **2,95 % (Standard), dégressif à 1,75 % (Pro/Premium)** + traitement Stripe/PayPal (cumul pouvant atteindre ~5,15 % + 0,30 $ sur un don de 100 $) ; formulaires embarquables, P2P, CRM, intégrations (Salesforce, Mailchimp, Zapier). **Leçon** : le triptyque « formulaire + récurrence + CRM » est exactement la brique **ONG/association** que Barkeelu peut monétiser en abonnement (segment payant).

## 9. Analyse FundRazr
Deux modes au choix du porteur : « Optional Tips » (0 % plateforme) ou « Fee Recovery » (5 %) ; traitement 2,9 % + 0,30 $ ; **keep-what-you-raise ou tout-ou-rien** ; **Crowdfunding-as-a-Service** en marque blanche ; partenariat PayPal ; 4 000+ nonprofits. **Leçon** : laisser AU PORTEUR le choix du modèle de frais est un design pertinent et différenciant.

## 10. Analyse Open Collective
Transparence via un **ledger public en double-entrée** ; fiscal hosts ; dépenses soumises/approuvées visibles publiquement ; remboursements et frais de processeur enregistrés comme **transactions séparées** (depuis janv. 2024). Tarification passée de 10 % à des plans non-% (nombre de collectifs/dépenses), ou 5 % crowdfunding, ou pourboires. Gouvernance par OFiCo (non-profit) depuis oct. 2024. **Leçon centrale pour Barkeelu : le ledger transparent est le meilleur générateur de confiance et doit être une entité de premier plan de l'architecture, pas un simple journal comptable.**

## 11. Analyse concurrentielle
Matrice (0 = absent → 5 = excellent) — estimation experte, Barkeelu en cible MVP→V1 :

| Critère | Barkeelu (cible) | Kopar | GoFundMe | Ulule | RiseLab | Donorbox | FundRazr | Open Collective |
|---|---|---|---|---|---|---|---|---|
| Création campagne | 4 | 4 | 5 | 4 | 3 | 4 | 4 | 3 |
| Dons | 5 | 4 | 5 | 4 | 3 | 5 | 4 | 4 |
| Dons récurrents | 3 | 1 | 4 | 2 | 1 | 5 | 4 | 5 |
| Contreparties | 2 | 1 | 1 | 5 | 2 | 2 | 3 | 1 |
| Mobile money | 5 | 5 | 0 | 1 | 1 | 0 | 0 | 0 |
| Carte | 4 | 4 | 5 | 5 | 3 | 5 | 5 | 5 |
| Diaspora | 4 | 3 | 5 | 3 | 1 | 4 | 4 | 4 |
| KYC/vérification | 4 | 2 | 5 | 4 | 2 | 3 | 3 | 4 |
| Modération | 4 | 2 | 5 | 4 | 1 | 3 | 3 | 4 |
| Payout fiable/rapide | 5 | 2 | 4 | 4 | 2 | 4 | 4 | 4 |
| Transparence/ledger | 5 | 2 | 3 | 3 | 1 | 3 | 3 | 5 |
| Frais compétitifs | 3 | 5 | 4 | 2 | 3 | 3 | 4 | 3 |
| Referral | 3 | 1 | 2 | 2 | 2 | 3 | 3 | 1 |
| Gamification | 2 | 0 | 1 | 1 | 1 | 1 | 1 | 1 |
| Telegram | 4 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| WhatsApp | 4 | 2 | 1 | 1 | 0 | 1 | 1 | 0 |
| Analytics | 3 | 2 | 4 | 3 | 2 | 4 | 4 | 4 |
| API | 3 | 2 | 3 | 2 | 2 | 4 | 4 | 5 |
| SEO | 4 | 3 | 5 | 4 | 3 | 4 | 4 | 4 |
| Mobile | 5 | 4 | 5 | 4 | 3 | 4 | 4 | 3 |
| Confiance | 5 | 2 | 5 | 4 | 2 | 4 | 4 | 5 |

Lecture : Barkeelu ne peut gagner sur « frais » (Kopar = 5) ; il gagne sur **payout fiable, transparence, Telegram/WhatsApp, KYC/modération et confiance**.

## 12. Modèle économique Barkeelu
Sources de revenus possibles : (1) frais plateforme sur dons ; (2) pourboire donateur optionnel (modèle Kopar/GoFundMe/FundRazr) ; (3) **abonnements SaaS ONG/associations** (formulaires récurrents, CRM, reçus — modèle Donorbox) ; (4) services premium (campagne vérifiée, mise en avant, marque blanche façon FundRazr).

**Recommandation : modèle hybride segmenté** — gratuit + pourboire pour collectes personnelles de solidarité (parité perçue avec Kopar), et payant (abonnement + petit %) pour associations/ONG qui ont besoin d'outils (CRM, reçus, reporting). C'est la réponse à « Kopar frontalement ou segmenter ? » : **segmenter**.

## 13. Analyse des frais 4 % + 1 %
**Incohérence interne à trancher** : Business Model Canvas = 6 % ; brief = 4 % + 1 % = 5 % ; production (Paymattic/GiveWP) = 4 % + 2 % = 6 % avec gross-up donateur.

**Coûts réels d'ENCAISSEMENT (côté collecte)** :
- Wave : **1 % plafonné à 5 000 FCFA/transaction** (Kolonell : « 1% merchant fee, capped at XOF 5,000 »).
- Orange Money marchand : ~1 % de commission, reversé J+5 sur compte bancaire (Orange Business SN).
- Free Money : « tout à 0 F » sur paiement marchand annoncé (Réussir Business) — **à confirmer contractuellement** (sources tierces divergentes).
- Agrégateurs : 1,5–3 % (SenePay 1,8 % flat ; PayDunya/CinetPay/InTouch variables selon pays et opérateur).

**Coûts de PAYOUT (côté décaissement)** :
- Wave cash-out vers banque : forfait ~250 FCFA (documentation Kolonell, à confirmer) ; Bulk Pay 1 %.
- Orange Money : reversement J+5.

Le « 1 % Wave » du brief est donc **mal placé** : c'est un frais d'encaissement marchand plafonné, pas un transfert vers le promoteur (les transferts Wave↔Wave grand public sont gratuits ; Wave se rémunère sur le retrait et la commission marchand).

**Recommandation sur le chiffre à retenir** : afficher un **frais plateforme Barkeelu de 4 %, plafonné, côté donateur (gross-up transparent), avec pourboire optionnel**, et refacturer le coût PSP en transparence. **Abandonner le 6 %** (détruit l'argument face au 0 % de Kopar) et **ne jamais empiler** 4 %+2 % côté prod. Cohérence retrouvée entre BMC/brief/prod = **4 % plafonné**.

**Scénarios par transaction** (Wave encaissement = min(1 %, 5 000) ; Barkeelu 4 % gross-up ; payout mutualisé) :

| Don | Barkeelu 4 % | Wave in (cap 5 000) | Payout (banque ~250 F) | Marge brute Barkeelu |
|---|---|---|---|---|
| 10 000 | 400 | 100 | ~250 mutualisé | ~300 |
| 50 000 | 2 000 | 500 | " | ~1 500 |
| 100 000 | 4 000 | 1 000 | " | ~3 000 |
| 500 000 | 20 000 | 5 000 (cap) | " | ~15 000 |
| 1 000 000 | 40 000 | 5 000 (0,5 %) | " | ~35 000 |
| 10 000 000 (1 gros don) | 400 000 | 5 000 (0,05 %) | " | ~395 000 |
| 10 000 000 (200 × 50 000) | 400 000 | 100 000 (1 %) | " | ~300 000 |

**Effet de plafond** : il n'aide QUE sur les gros dons unitaires. En crowdfunding réel (multitude de petits dons), le coût PSP reste ~1 %. Un **4 % linéaire sur une campagne de 10 M = 400 000 FCFA facturés au donateur**, perçu comme abusif → **justifie un barème dégressif/plafonné côté frais plateforme** (ex. 4 % jusqu'à un plafond par campagne, ou dégressivité au-delà de X FCFA collectés). Mais **ne pas** modéliser le coût PSP à 0,05 % sauf gros dons unitaires.

**Scénarios de volume** (panier moyen 25 000 FCFA, ~20 dons/campagne, marge brute plateforme indicative) :
- 10 campagnes/mois : ~5 M FCFA collectés → ~200 000 FCFA de marge brute.
- 100 : ~50 M → ~2 M.
- 500 : ~250 M → ~10 M.
- 1 000 : ~500 M → ~20 M.
- 10 000 : ~5 Md → ~200 M.

**Seuil de rentabilité** : fonction de l'opex fixe (dev, hébergement, support, conformité). Avec un opex mensuel de l'ordre de 3–5 M FCFA, la rentabilité est atteinte autour de **200–500 campagnes/mois** — **hypothèse à valider** avec les coûts réels (chiffres opex non sourcés). Analyse de sensibilité : la marge est très sensible (a) au taux de pourboire réel vs 4 % imposé, (b) au mix mobile money/carte (la carte diaspora coûte 2,9 %+), (c) à la nouvelle taxe marchand 0,5 %.

## 14. Commission promoteur 1–1,5 %
**Ambiguïté critique — À VALIDER.** Si « promoteur » = **porteur de campagne**, lui prélever 1–1,5 % est incohérent : il reçoit déjà les fonds nets de frais. Si « promoteur » = **apporteur/ambassadeur** recrutant des campagnes, alors 1–1,5 % est un **coût d'acquisition (CAC)** à imputer sur la marge, **pas un revenu**. L'exemple du brief (don 100 000 → net 93 575 après commission promoteur 1 425) traite la commission comme une ponction supplémentaire sur le bénéficiaire — ce qui **aggrave la non-compétitivité face au 0 % de Kopar**. **Recommandation** : réserver toute commission « promoteur » à un **programme d'ambassadeurs/referral mesurable et plafonné, financé par la marge plateforme**, jamais prélevé sur le bénéficiaire.

## 15. Réglementation
- **Détenir des fonds en escrow = activité réglementée BCEAO.** Depuis le **1er mai 2025** (Avis BCEAO n°004-03-2025, fin de période transitoire), toute structure non agréée doit cesser d'offrir des services de paiement. **Instruction n°001-01-2024** (23 janv. 2024) : établissement de paiement, **capital 10–100 M FCFA** selon le service, forme SA/SARL/coopérative (société unipersonnelle interdite), **délai d'instruction 6 mois**. **Instruction n°008-05-2015** (EME) : **capital 300 M FCFA**, objet social exclusif, **cantonnement des fonds** (art. 32, ≥75 % en dépôts à vue). En février 2026, la BCEAO a agréé 31 établissements de paiement dans l'UEMOA, dont 11 au Sénégal.
- **Recommandation MVP (RECOMMANDÉ)** : **ne pas détenir les fonds.** S'appuyer sur un partenaire agréé — Wave (EME) et/ou Orange Money (EME), et/ou un PSP/agrégateur : **PayDunya**, dont Peach Payments (Afrique du Sud) a annoncé le rachat le 3 avril 2025 (PayDunya, fondée en 2015 à Dakar par Aziz Yérima, « revendique 70 000 transactions journalières et 4 000 clients B2B » dans 6 pays — Agence Ecofin), ou InTouch/CinetPay. **L'escrow doit être techniquement porté par le partenaire agréé**, sinon risque de requalification de Barkeelu en établissement de paiement/EME.
- **CDP (loi n°2008-12 du 25 janvier 2008)** : déclaration préalable obligatoire (récépissé ~1 mois, art. 18) ; **autorisation préalable** si données sensibles — or une cagnotte politique révèle des opinions politiques (catégorie sensible). Sanctions de 1 M à **100 M FCFA**.
- **CENTIF / LBC-FT** : statut d'assujetti → KYC, vigilance sur le bénéficiaire effectif, déclaration d'opérations suspectes, **conservation 10 ans**, dispositif interne. La CENTIF a signalé fin 2024 une hausse des typologies de fraude en ligne.
- **Niveau de confiance** : élevé sur les textes (sources BCEAO/CDP officielles) ; moyen sur la qualification exacte de la cagnotte (établissement de paiement vs EME). **Chaque conclusion doit être validée par un conseil bancaire à Dakar et/ou une consultation directe de la BCEAO — pas de conseil juridique définitif ici.**

## 16. Trust & Safety
Fraudes à couvrir : fausse campagne, faux bénéficiaire, usurpation d'identité, fraude mobile money, blanchiment, campagne médicale frauduleuse, auto-dons, faux commentaires/donateurs, manipulation de dons, chargebacks carte, détournement post-payout. **Pipeline recommandé** : Risk Score → KYC → Campaign Review → Payment Monitoring → Payout Controls → Audit. **MVP minimal** : KYC bénéficiaire (unique), revue manuelle avant le 1er décaissement, décaissement conditionnel, journal d'audit immuable, signalement/blocage. Le cas Kopar (fraude carte Stripe ayant bloqué un décaissement) plaide pour **privilégier le mobile money local** et **limiter la carte internationale à la diaspora avec contrôle renforcé**.

## 17. Laravel vs Django
**Laravel** : écosystème de packages riche (paiements, multi-tenant, permissions, queues, Sanctum), rapidité MVP, vivier de développeurs PHP au Sénégal, et surtout **réutilisation du Yessal Core** (Laravel, PHP 8.3, Sanctum, PostgreSQL, multi-tenancy). **Django** : excellent pour data/IA/Python mais rupture avec l'existant. **Recommandation : Laravel pour le cœur transactionnel** ; Python réservé au service Telegram et à l'IA (anti-fraude, scoring de risque). Cohérent avec l'existant, minimise le temps-jusqu'au-MVP et le risque de recrutement.

## 18. Aimeos vs Lunar vs RiseLab vs Custom
- **Aimeos / Lunar** : moteurs e-commerce (catalogue/panier/commande) — **mauvais fit** ; le modèle don→ledger→payout diffère fondamentalement du modèle produit→commande.
- **RiseLab** : à éviter (cf. §7).
- **Recommandation : construire le « Barkeelu Fundraising Engine » custom sur Laravel**, en réutilisant le Yessal Core SaaS (Organizations, Plans, Subscriptions, Entitlements, Quotas, Payments, Multi-tenancy) — **sans présumer** la réutilisation des modules Caisse. Le ledger, le moteur de dons et le moteur de payout sont spécifiques et doivent être bâtis sur mesure.

## 19. Flutter multi-plateforme
**Flutter Web** : SEO faible (rendu canvas/JS peu indexable), première charge lourde, partage social et deep links moins naturels, OpenGraph limité — **inadapté aux pages publiques de campagne** qui doivent être indexées et partagées viralement (WhatsApp/Facebook). **Recommandation (architecture hybride)** :
- **Pages publiques de campagne + tunnel de don en Laravel SSR (Blade/Inertia)** — pour SEO, OpenGraph, performance, partage.
- **Flutter réservé aux apps promoteur/donateur connectés** (Android/iOS d'abord ; desktop/web plus tard si besoin interne).
Pas de « tout Flutter Web » pour le public.

## 20. Telegram / Python / automatisation
Architecture validée : **Laravel → Events/Queue/API → Python Telegram Service → Telegram** ; le bot **n'accède jamais directement à PostgreSQL** (correct — découplage et sécurité). Usages : nouvelle campagne, nouveau don, campagne tendance, objectif atteint/proche, campagne urgente, rappels, promotion, statistiques, alertes admin, notifications promoteur. **Étendre à WhatsApp (Cloud API) — canal dominant au Sénégal —, email et push.** **Priorité d'acquisition : WhatsApp > Telegram** pour le marché local ; Telegram utile pour les communautés et les alertes admin.

## 21. Gamification
**Post-MVP**, inspiré de GamiPress : badges donateur, niveaux d'ambassadeur, classements de campagnes, challenges communautaires, referrals récompensés. Objectifs : acquisition, partage, rétention, confiance. **Ne pas surcharger le MVP** — commencer par referral + badges de partage en V1.

## 22. Architecture recommandée
Conserver l'ossature proposée avec ajustements : **Yessal Core SaaS + Fundraising Engine + Donation Engine + Payment Engine** (adaptateurs Wave / Orange Money / Free Money / agrégateur) **+ Financial Ledger** (double-entrée, idempotent, réconciliation) **+ Trust & Safety + Notification Engine** (Telegram/WhatsApp/email/push) **+ Gamification** (later). Stack : **Laravel, PostgreSQL, Redis, Queues, Flutter (apps), Python (Telegram/IA), site public Laravel SSR.**
Entités ledger : **Payment, Donation, Fee, Commission, Ledger, LedgerEntry, Payout, Refund, Chargeback, Reconciliation** — avec **idempotency keys** (Wave retente jusqu'à 5 fois sur 24 h : stocker l'event_id pour ne pas traiter deux fois une transaction) et **audit trail** complet. Confirmation d'encaissement par **webhook signé** uniquement (jamais sur le retour navigateur).

## 23. MVP (MUST / SHOULD / COULD / LATER)
- **MUST** : compte/profil ; création campagne (objectif, durée, image, catégorie, bénéficiaire) ; KYC bénéficiaire ; revue admin avant 1er payout ; don via **Wave + Orange Money** ; page publique **SSR** ; partage **WhatsApp** ; dashboard promoteur ; payout mobile money ; notifications ; **ledger** ; journal d'audit.
- **SHOULD** : Free Money ; carte (diaspora) ; commentaires ; mises à jour ; décaissement partiel ; pourboire donateur ; reçus.
- **COULD** : bot Telegram ; referral ; QR codes ; vidéo.
- **LATER** : dons récurrents ; contreparties/prévente ; tout-ou-rien ; gamification ; API publique ; multi-devises ; abonnements ONG/CRM ; marque blanche.

## 24. V1
Free Money + carte diaspora avec contrôle renforcé ; décaissement partiel ; pourboire ; reçus ; **automation Telegram + WhatsApp** ; **referral/ambassadeurs** ; analytics promoteur ; **campagnes vérifiées** (badge de confiance) ; premiers badges de partage.

## 25. V2
Dons récurrents ; **abonnements ONG + CRM/ERP léger** (segment payant, brique Donorbox) ; contreparties/prévente/tout-ou-rien (segment créatif, brique Ulule) ; API publique ; multi-devises/internationalisation diaspora ; gamification complète ; **marque blanche (Crowdfunding-as-a-Service, brique FundRazr)**.

## 26. SWOT
- **Forces** : réutilisation du Yessal Core (time-to-market), mobile money natif, focus confiance/payout, orientation diaspora, canaux WhatsApp/Telegram.
- **Faiblesses** : nouveau venu sans notoriété ; pas d'agrément propre (dépendance PSP) ; besoin de capital/opex ; exécution technique du ledger.
- **Opportunités** : faiblesse de payout de Kopar ; diaspora « or financier » (2 211 Md FCFA) ; segment ONG/associations mal servi ; taxe mobile money qui rebat les cartes.
- **Menaces** : réplique de Kopar/opérateurs ; évolution réglementaire (taxe, agrément) ; fraude/réputation (surtout carte diaspora) ; dépendance à Wave.

## 27. Positionnement face à Kopar Express
« Pourquoi un Sénégalais choisirait Barkeelu plutôt que Kopar ? » → **parce que l'argent arrive vite et de façon fiable, et parce que l'usage des fonds est prouvé et transparent** — là où Kopar accumule plaintes de décaissement et litiges de rétention.
- **3 avantages immédiats** : (1) décaissement rapide/fiable annoncé ET tenu (SLA public) ; (2) transparence de type ledger public ; (3) UX de contribution mobile money sans friction + partage WhatsApp natif.
- **3 avantages difficiles à copier** : (1) confiance construite par la preuve d'usage des fonds et le suivi post-collecte ; (2) intégration diaspora (KYC/devise/preuve/suivi) ; (3) communauté + ambassadeurs.
- **3 avantages à construire à moyen terme** : (1) suite ONG/associations (CRM/abonnement) ; (2) automation Telegram/WhatsApp ; (3) gamification/referral.
Barkeelu comme **infrastructure communautaire** : Fundraising + Mobile Money + Diaspora + Communauté + Telegram + WhatsApp + Gamification + Trust.

## 28. Business Model
Hybride segmenté : (1) **collectes personnelles/solidarité = gratuit + pourboire optionnel** (parité perçue avec Kopar) ; (2) **associations/ONG = abonnement SaaS + petit %** (outils CRM, reçus, reporting) ; (3) **premium** (vérification, mise en avant, marque blanche). Frais plateforme cible **4 % plafonné, côté donateur, transparent**, PSP refacturé en transparence. **Ne pas détenir les fonds.**

## 29. Risques
- **Réglementaire** : détention de fonds (agrément), nouvelle taxe marchand 0,5 %, statut CDP (données sensibles/politiques).
- **Réputationnel** : payout, fraude, hébergement involontaire de cagnottes politiques (leçon Kopar).
- **Dépendance** : Wave/PSP (concentration du risque).
- **Acquisition** : notoriété face à Kopar installé.
- **Fraude/chargebacks** : surtout carte diaspora.
- **Exécution technique** : ledger/idempotence/réconciliation.

## 30. Recommandations finales (VALIDÉ / RECOMMANDÉ / À TESTER / À VALIDER / À ÉVITER / HORS MVP)
1. **VALIDÉ** : le marché et le besoin (mobile money massif, diaspora à 2 211 Md FCFA, informel à formaliser).
2. **RECOMMANDÉ** : ne pas détenir les fonds ; partenariat Wave + agrégateur (PayDunya/InTouch) ; Laravel custom + Yessal Core ; site public SSR ; Flutter pour apps connectées ; ledger double-entrée dès le MVP ; segmentation gratuit(personnel)/payant(ONG).
3. **À TESTER** : 4 % plafonné vs pourboire volontaire (A/B sur perception et conversion) ; WhatsApp vs Telegram pour l'acquisition ; SLA de décaissement affiché.
4. **À VALIDER (juridique)** : qualification de l'escrow (établissement de paiement vs EME) ; obligations CDP (données sensibles/politiques) ; obligations CENTIF/KYC ; **définition exacte de la « commission promoteur »**.
5. **À ÉVITER** : RiseLab comme socle ; Flutter Web pour le public ; frais à 6 % ; 4 % linéaire non plafonné ; le « 1 % Wave payout » du brief (mal attribué) ; les cagnottes politiques (risque Kopar).
6. **HORS MVP** : dons récurrents, contreparties, gamification, API publique, multi-devises, marque blanche.

**Réponse à la question stratégique finale** : Barkeelu bat Kopar Express en combinant **fiabilité et rapidité de décaissement + transparence de type ledger + mobile money natif + diaspora + communauté WhatsApp/Telegram + un segment ONG payant à forte valeur**, le tout sur une **architecture Laravel custom réutilisant le Yessal Core, ne détenant pas les fonds** (partenaires agréés Wave/agrégateur), avec des **frais lisibles et plafonnés (4 % plafonné + pourboire)**. Ce n'est pas la guerre des prix qui fera gagner Barkeelu, mais la **confiance opérationnelle** : tenir la promesse que Kopar peine à tenir — que l'argent collecté arrive vite, en entier, et de façon prouvée, à la bonne personne.

## Bibliographie / Sources
- BCEAO — bceao.int : Instruction n°001-01-2024 (services de paiement) ; Instruction n°008-05-2015 (monnaie électronique) ; Avis n°004-03-2025 (fin période transitoire) ; guide agrément EME ; liste EME/établissements de paiement (fév. 2026).
- Wave — wave.com (CGU UBA Sénégal) ; Wave Sénégal (X, oct. 2024, frais banque plafonnés 500 F) ; Coura Carine Sène, DG Wave Sénégal (CIO Mag, 7,18 M actifs) ; Kolonell (guide API Wave Business 2026 — source prestataire).
- Orange Business Sénégal — orangebusiness.sn (paiement marchand/Internet/Pro, commission 1 %, J+5) ; Sonatel résultats 2025 (allAfrica, 13 M clients Orange Money, 3,8 Md transactions).
- Free Money — Réussir Business (« tout à 0 F ») ; MomoCalc (1 % retrait) ; Social Net Link.
- Agrégateurs — SenePay (1,8 % flat) ; PayDunya (developers.paydunya.com) ; CinetPay ; CB Insights (PayDunya/CinetPay/SudPay) ; Agence Ecofin (rachat PayDunya par Peach Payments, 3 avril 2025) ; Kolonell (comparatif agrégateurs 2026).
- Kopar Express — koparexpress.org/.com (frais, crowdfunding, payout 1 %/1,5 %, 3–7 j) ; App Store / Google Play (avis, versions) ; Senego / SENTV / Exclusif / Dakaractu / Seneweb / Rewmi (affaire Hannibal Djim, litiges GIE non-voyants et UASZ, cagnotte Pastef).
- Marché / diaspora — APS, allAfrica, Financial Afrik, Le Soleil, Xinhua (transferts diaspora 2 211 Md FCFA 2024, BCEAO) ; GSMA via Ecofin (7→38 M comptes) ; Forbes Afrique (15 300 Md FCFA 2025, taxe) ; Finance in Africa / AFI ; TechAfrica (pénétration mobile) ; Agence Ecofin (loi n°2025-17, taxe 0,5 % marchand).
- Benchmarks — GoFundMe (gofundme.com pricing/transparency) ; Ulule (outils.ulule.com, Clubic, Mac4Ever, Blog du Modérateur) ; Donorbox (softwareconnect, betterworld, pricingnow) ; FundRazr (fundrazr.com, Merchant Maverick, Wikipedia) ; Open Collective (docs/documentation.opencollective.com, pricing, Medium) ; RiseLab (CodeCanyon/ViserLab).
- Technologie — comparatifs Laravel vs Django (Redberry, InVerita, DevTechnosys) ; Absitech / Kolonell (intégration paiements Sénégal).
- Réglementation données/AML — CDP (cdp.sn, loi 2008-12) ; African Legal Factory ; CENTIF ; analyses Cabinet Houda ; droitmediasfinance.com.

*Note de méthode : les tarifs mobile money proviennent en partie de blogs de prestataires (Kolonell, SenePay, MomoCalc) et non des grilles officielles Wave/OM ; ils sont cohérents entre eux mais doivent être confirmés contractuellement (business.wave.com, contrat marchand Orange). Les signaux d'avis utilisateurs sur Kopar sont des SIGNAUX, non des preuves de défaillance systémique. Le volet pénal Kopar relève d'une instruction, sans condamnation définitive confirmée.*