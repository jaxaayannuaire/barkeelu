# Barkeelu — Rapport stratégique (Sénégal / Afrique de l'Ouest / Diaspora)

**Version 2.0 — 1er septembre 2026**
Révision intégrant les corrections opérationnelles du porteur de projet :
modèle 5 % validé, sens du 1 % Wave corrigé, positionnement transparence + communauté mouride + diaspora.

---

## Journal des corrections apportées à la v1.0

| # | Point v1.0 | Correction v2.0 | Source de la correction |
|---|---|---|---|
| 1 | Trois modèles de frais en concurrence (6 % BMC / 5 % brief / 4 %+2 % prod) | **Modèle tranché : 4 % Barkeelu + 1 % Wave = 5 %** | Décision porteur de projet |
| 2 | 1 % Wave présenté comme frais d'**encaissement** marchand plafonné | **Faux. Encaissement vers compte marchand Wave = gratuit. Le 1 % s'applique à l'envoi compte marchand → particulier (payout)** | Expérience opérationnelle du compte marchand Barkeelu |
| 3 | Effet de plafond majeur côté collecte, modèle non linéaire | **Le plafond ne joue plus côté collecte. Le modèle devient strictement linéaire et lisible.** Le plafond joue désormais côté payout, en faveur de Barkeelu | Découle de la correction n°2 |
| 4 | Positionnement générique « confiance + payout » | **Positionnement précisé : transparence outillée (Telegram, statistiques, preuves photo/vidéo, remerciements) + communauté mouride + diaspora, sur l'humanitaire et le social** | Décision porteur de projet |
| 5 | Frais présentés comme un handicap face au 0 % de Kopar | **Les 5 % sont assumés et vendus comme explicites, clairs et détaillés pour le donateur** | Décision porteur de projet |

Note de méthode : les sources publiques consultées se contredisaient sur le point n°2 (une source de prestataire évoquait des frais marchands de 1 % à l'encaissement, une autre indiquait que les opérateurs encaissent gratuitement et que le coût se situe uniquement au retrait). L'expérience directe du compte marchand tranche en faveur de la seconde lecture. **Ce point reste à confirmer par écrit dans le contrat marchand Wave** avant industrialisation.

---

# Executive Summary

Barkeelu ne gagne pas contre Kopar Express sur le prix — Kopar affiche 0 % de commission plateforme avec pourboire volontaire. Barkeelu gagne sur ce que Kopar ne tient pas : **la redevabilité**. Le positionnement retenu est une plateforme de collecte **transparente et outillée**, adossée à la **communauté mouride** et à la **diaspora sénégalaise**, pour les causes **humanitaires et sociales**.

Le modèle économique est fixé et assumé : **5 % au total, 4 % Barkeelu + 1 % de frais de transfert Wave**, affichés au donateur de façon explicite, ligne par ligne. Ce n'est pas un modèle low-cost : c'est un modèle de confiance payante, dont la contrepartie est un dispositif de preuve que la collecte informelle par numéro Wave partagé sur WhatsApp ne peut pas offrir.

Les trois arbitrages structurants du rapport :

1. **Ne pas détenir les fonds en propre.** Depuis le 1er mai 2025, la détention de fonds relève d'une activité réglementée BCEAO (agrément établissement de paiement ou établissement de monnaie électronique). L'escrow doit être techniquement porté par le partenaire agréé — le compte marchand Wave — et non par une structure de cantonnement Barkeelu.
2. **Site public en SSR, apps en Flutter.** Les pages de campagne doivent être indexables et partageables sur WhatsApp ; Flutter Web ne le permet pas correctement. Flutter est réservé aux espaces connectés promoteur et donateur.
3. **Ledger double-entrée dès le MVP.** Barkeelu n'est pas un e-commerce. La chaîne Payment → Donation → Ledger → Fees → Available Balance → Payout doit être auditée et idempotente dès la première ligne de code.

Le risque principal du positionnement choisi n'est pas commercial, il est opérationnel : **la preuve photo/vidéo est aussi un vecteur de fraude**. Une preuve non vérifiée qui s'avère truquée détruirait en une fois le capital de confiance construit sur le segment communautaire. Le dispositif de vérification des preuves est donc une fonctionnalité critique, pas un ornement.

---

## 1. Conclusion stratégique

**VALIDÉ** — Le marché existe et il est massif. Les transferts de la diaspora sénégalaise ont atteint 2 211 milliards FCFA en 2024, soit environ 12 % du PIB et davantage que l'aide publique au développement (BCEAO, citée par l'APS, décembre 2025). Le mobile money est omniprésent : les comptes enregistrés sont passés de 7 à 38 millions entre 2013 et 2023 (GSMA), Wave revendique environ 7,18 millions d'utilisateurs actifs au Sénégal et Orange Money 13 millions de clients actifs pour 3,8 milliards de transactions en 2025 (résultats Sonatel).

**VALIDÉ** — Le modèle 4 % + 1 % = 5 %, affiché de façon explicite au donateur.

**RECOMMANDÉ** — Ne pas détenir les fonds ; escrow porté par le compte marchand Wave ; Laravel custom réutilisant le Yessal Core ; site public SSR ; Flutter pour les apps ; ledger double-entrée dès le MVP ; segmentation gratuit/payant à envisager en V2 pour les ONG structurées.

**À VALIDER** — L'assiette exacte du 1 % Wave (déduit ou en sus), le plafonnement éventuel du payout, l'application de la taxe de 0,5 % sur les paiements marchands, et la qualification réglementaire de la rétention temporaire des fonds sur le compte marchand.

**À ÉVITER** — RiseLab comme socle technique ; Flutter Web pour les pages publiques ; le 4 % strictement linéaire sur les très grosses campagnes ; l'hébergement de cagnottes politiques.

**Différenciation** — Là où Kopar est gratuit mais opaque et lent au décaissement, Barkeelu est payant mais rapide, traçable et redevable. Le prix est le prix de la preuve.

---

## 2. Marché sénégalais

**Mobile money.** Entre 2013 et 2023, le nombre de comptes mobile money enregistrés au Sénégal a plus que quintuplé, passant de 7 à 38 millions, avec une pénétration passée de 45 % à 210 % et une contribution de 8,6 % au PIB en 2023 (GSMA, via Agence Ecofin et Forbes Afrique). Le volume transactionnel 2025 est estimé autour de 15 300 milliards FCFA.

**Wave.** Selon Coura Carine Sène, directrice générale de Wave Sénégal, l'opérateur compte près de 11 millions de clients dont environ 7,18 millions d'utilisateurs actifs, soit environ 90 % de la population adulte disposant d'un compte (CIO Mag). Wave est décrit comme détenant environ 70 % du marché du mobile money sénégalais et 40 000 marchands actifs — chiffres issus de sources prestataires, à qualifier.

**Orange Money.** 13 millions de clients actifs et près de 3,8 milliards de transactions réalisées en 2025 (résultats financiers Sonatel 2025).

**Bancarisation.** Moins de 30 % des adultes disposent d'un compte bancaire classique (BCEAO), tandis que 55 % des adultes utilisent des services financiers numériques. 59 % des femmes financièrement incluses dépendent exclusivement du mobile money (AFI). Le mobile money n'est pas un canal parmi d'autres : c'est **le** canal.

**Fiscalité — point d'alerte.** La loi n° 2025-17 du 27 septembre 2025 modifiant le Code général des Impôts, en vigueur depuis octobre 2025, instaure un prélèvement à la source de 0,5 % du montant de chaque paiement reçu par les commerçants via des solutions électroniques (Agence Ecofin). Les sources divergent sur l'empilement exact des taux. **Impact direct sur Barkeelu : si ce prélèvement s'applique aux encaissements du compte marchand, il consomme un huitième de la marge de 4 %.** À faire confirmer par Wave et par un fiscaliste avant de figer le tarif public.

---

## 3. Fundraising au Sénégal

La pratique de la collecte existe déjà, massivement, en dehors de toute plateforme : collectes médicales (évacuations sanitaires, chimiothérapies, aplasies médullaires), collectes religieuses et communautaires, collectes scolaires et universitaires, GIE de femmes, aide aux sinistrés d'inondations, rapatriements de corps, soutien aux talibés.

Le mécanisme dominant reste **un numéro Wave ou Orange Money partagé sur WhatsApp, Facebook ou TikTok**. Ses limites sont exactement les vôtres à combler :

| Faiblesse de la collecte informelle | Réponse Barkeelu |
|---|---|
| Aucune traçabilité des montants collectés | Compteur public temps réel + ledger |
| Aucune preuve de l'usage des fonds | Preuves photo/vidéo vérifiées + mises à jour |
| Le donateur ne sait pas s'il a été le 3e ou le 300e | Statistiques publiques, liste des contributions |
| Aucun reçu, aucun remerciement | Remerciements automatisés, reçus |
| Aucune information après la clôture | Notifications Telegram, rapport de clôture |
| Confiance basée uniquement sur la réputation personnelle du collecteur | KYC + vérification de campagne + badge vérifié |

C'est la raison pour laquelle le recours à l'informel persiste : non par préférence, mais faute d'alternative crédible. Kopar occupe la place mais échoue sur le décaissement. **La fenêtre est ouverte.**

---

## 4. Analyse Kopar Express

**Entreprise.** Fintech sénégalaise multi-services : cagnotte (Kopar Collect), paiement, transfert d'argent, factures, forfaits, marketplace « Vente Privée », interopérabilité. Co-fondateur et actionnaire à environ 25 % : Seydou Nourou Ba. Pays couverts : Sénégal, Côte d'Ivoire, Gabon, avec le Tchad annoncé. La société se présente comme opérant avec des entités juridiques locales, en conformité avec les réglementations BCEAO et BEAC — **auto-déclaration non vérifiée indépendamment**.

**Contexte judiciaire.** À traiter avec prudence : instruction en cours, pas de condamnation définitive confirmée. Seydou Nourou Ba a été incarcéré en 2023 dans le cadre de l'affaire dite « Hannibal Djim », liée à des cagnottes politiques. Deux litiges civils de rétention de fonds sont documentés dans la presse sénégalaise : un GIE pour la promotion des non-voyants (non-reversement allégué d'environ 11 millions FCFA, Ba invoquant un signalement de fraude carte via Stripe) et des étudiants de l'UASZ en 2026 (cagnotte de rapatriement). Une cagnotte politique d'octobre 2024 a connu des incidents techniques attribués par Kopar à des accès simultanés massifs.

**Enseignement pour Barkeelu :** ne pas héberger de cagnottes politiques. Le risque n'est pas seulement réglementaire (données sensibles au sens de la loi CDP), il est existentiel — il expose la plateforme à un blocage administratif qui gèlerait aussi les collectes humanitaires.

**Produit.** Création de collecte en quelques minutes ; catégories (événement, cotisation, urgence, santé, éducation) ; médias, storytelling, partage, mises à jour, prolongation ; décaissement partiel dès qu'un seuil est atteint ; vérification d'identité du bénéficiaire une seule fois.

**Paiements.** Wave, Orange Money, Free Money, MTN, Moov CI, Visa/Mastercard ; contributions depuis l'Afrique et la diaspora.

**Modèle économique.** 0 % de commission plateforme, pourboire volontaire. Verbatim du site : hors frais de l'opérateur de paiement ou de retrait, Kopar ne prend pas de frais supplémentaires et laisse le choix d'un pourboire pour couvrir ses frais de fonctionnement et de vérification des collectes.

**Analyse critique du modèle 0 %.** Ce modèle n'est soutenable qu'à très grande échelle, avec un coût d'encaissement quasi nul et une monétisation croisée par les autres services (transfert, factures, marketplace). Sur la seule cagnotte, il ne finance ni une équipe de modération, ni une vérification sérieuse des campagnes, ni un support réactif — ce que confirment les signaux utilisateurs. **Il n'est pas attaquable frontalement et il ne doit pas l'être.**

**Payout.** Confirmé sur le site : mobile money (Wave, Orange, Free, MTN) en 3 à 7 jours, frais 1 % ; compte bancaire UEMOA/CEMAC en 3 à 7 jours ouvrés, frais 1,5 %. Décaissement partiel possible dès seuil atteint ; vérification d'identité unique.

**Réputation — signaux utilisateurs, à ne pas présenter comme preuve d'une défaillance systémique.** Les avis App Store et Google Play font état de façon récurrente de lenteurs ou d'absence de décaissement et d'un support difficile à joindre. Verbatim représentatifs : la facilité à créer une collecte contrastée avec l'impossibilité de décaisser, et des interrogations directes sur la longueur des délais de décaissement.

**Conclusion concurrentielle.** La faiblesse stratégique de Kopar est le **décaissement**, et par extension la **redevabilité**. C'est précisément l'axe de Barkeelu.

---

## 5. Analyse GoFundMe

0 % de frais plateforme aux États-Unis ; frais de traitement de 2,9 % + 0,30 $ par don (2,2 % + 0,30 $ pour les organismes caritatifs éligibles) ; revenus assis sur les **pourboires optionnels** du donateur ; dons récurrents à 5 %. Équipe Trust & Safety dédiée, garantie de remboursement, payout via Stripe, pas de durée imposée.

**Enseignement.** Le pourboire volontaire fonctionne à très grande échelle, avec un processeur peu coûteux et une base de donateurs habituée au don en ligne. Aucune de ces trois conditions n'est réunie au Sénégal aujourd'hui. **Le pourboire n'est pas transposable comme modèle principal** — mais il peut être ajouté en complément optionnel des 5 %, en V1.

---

## 6. Analyse Ulule

Modèle tout-ou-rien ; commission porteur de 5 % (virement) à 8 % (carte bancaire) ; frais de service contributeur d'environ 2,3 % depuis avril 2025 ; remboursement intégral si l'objectif n'est pas atteint, sans commission ; validation éditoriale des projets ; accompagnement des porteurs. Certifiée B Corp.

**Enseignement.** Deux points transposables. D'abord, **la validation éditoriale préalable** : Ulule assume de refuser des projets, ce qui protège la marque — Barkeelu devra faire de même sur les campagnes médicales et les urgences. Ensuite, **le double affichage des frais** (côté porteur et côté contributeur) est exactement le modèle de transparence tarifaire que vous voulez porter. Le tout-ou-rien et les contreparties, en revanche, sont inadaptés au don de solidarité et relèvent de la V2.

---

## 7. Analyse RiseLab

Script CodeCanyon édité par ViserLab : Laravel avec Bootstrap et jQuery, KYC, plus de 40 passerelles automatiques et des passerelles manuelles, retraits manuels avec approbation administrateur, multi-devise, page builder, gestionnaire SEO, système de tickets. Dernière version majeure connue vers mars 2023.

**Verdict : À ÉVITER comme fondation.** Architecture front datée, aucune passerelle mobile money ouest-africaine native, pas de ledger double-entrée, dette technique et sécurité non auditables, licence d'enveloppe contraignante. **Utile uniquement comme inventaire fonctionnel de référence** pour ne rien oublier dans le cahier des charges.

---

## 8. Analyse Donorbox

Plateforme orientée dons récurrents. Frais plateforme de 2,95 % en offre Standard, dégressifs jusqu'à 1,75 % en Pro/Premium, auxquels s'ajoutent les frais Stripe ou PayPal — le cumul pouvant approcher 5,15 % + 0,30 $ sur un don de 100 $. Formulaires embarquables, collecte pair-à-pair, CRM, intégrations Salesforce, Mailchimp et Zapier.

**Enseignement.** Le triptyque formulaire embarquable + récurrence + CRM est la brique à vendre en **abonnement aux dahiras structurés, associations et ONG** — segment payant naturel pour Barkeelu en V2, distinct de la collecte grand public.

---

## 9. Analyse FundRazr

Deux modes au choix du porteur : pourboires optionnels (0 % plateforme) ou récupération de frais (5 %) ; traitement à 2,9 % + 0,30 $ ; keep-what-you-raise ou tout-ou-rien ; offre de crowdfunding en marque blanche ; partenariat PayPal ; plus de 4 000 organisations à but non lucratif.

**Enseignement.** Laisser au porteur le choix du modèle de frais est un design pertinent. Applicable en V2 : les dahiras et associations qui veulent que le donateur voie « 100 % de votre don va à la cause » pourraient absorber les 4 % côté bénéficiaire plutôt que de les faire porter au donateur.

---

## 10. Analyse Open Collective

Transparence assise sur un **ledger public en double-entrée** ; fiscal hosts ; dépenses soumises, approuvées et visibles publiquement ; remboursements et frais de processeur enregistrés comme transactions séparées depuis janvier 2024. Tarification passée de 10 % à des plans non proportionnels, ou 5 % en crowdfunding, ou pourboires. Gouvernance confiée à une structure à but non lucratif depuis octobre 2024.

**Enseignement central — c'est la référence la plus importante pour Barkeelu.** Open Collective démontre que **la transparence financière n'est pas un affichage marketing mais une architecture**. Le ledger doit être une entité de premier plan du système, exposée publiquement, où chaque frais, chaque remboursement et chaque décaissement est une transaction distincte et consultable. C'est ce qui rend les 5 % défendables : le donateur ne les subit pas, il les voit.

---

## 11. Analyse concurrentielle

Notation de 0 (absent) à 5 (excellent). Barkeelu évalué en cible MVP → V1.

| Critère | Barkeelu (cible) | Kopar | GoFundMe | Ulule | RiseLab | Donorbox | FundRazr | Open Collective |
|---|---|---|---|---|---|---|---|---|
| Création campagne | 4 | 4 | 5 | 4 | 3 | 4 | 4 | 3 |
| Dons | 5 | 4 | 5 | 4 | 3 | 5 | 4 | 4 |
| Dons récurrents | 3 | 1 | 4 | 2 | 1 | 5 | 4 | 5 |
| Contreparties | 2 | 1 | 1 | 5 | 2 | 2 | 3 | 1 |
| Mobile money | 5 | 5 | 0 | 1 | 1 | 0 | 0 | 0 |
| Carte | 4 | 4 | 5 | 5 | 3 | 5 | 5 | 5 |
| Diaspora | 5 | 3 | 5 | 3 | 1 | 4 | 4 | 4 |
| KYC / vérification | 4 | 2 | 5 | 4 | 2 | 3 | 3 | 4 |
| Modération | 4 | 2 | 5 | 4 | 1 | 3 | 3 | 4 |
| Payout fiable et rapide | 5 | 2 | 4 | 4 | 2 | 4 | 4 | 4 |
| Transparence / ledger | 5 | 2 | 3 | 3 | 1 | 3 | 3 | 5 |
| **Preuve d'usage des fonds** | **5** | **1** | **2** | **3** | **0** | **1** | **1** | **4** |
| Frais compétitifs | 3 | 5 | 4 | 2 | 3 | 3 | 4 | 3 |
| Referral | 3 | 1 | 2 | 2 | 2 | 3 | 3 | 1 |
| Gamification | 2 | 0 | 1 | 1 | 1 | 1 | 1 | 1 |
| Telegram | 5 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| WhatsApp | 4 | 2 | 1 | 1 | 0 | 1 | 1 | 0 |
| Analytics | 4 | 2 | 4 | 3 | 2 | 4 | 4 | 4 |
| API | 3 | 2 | 3 | 2 | 2 | 4 | 4 | 5 |
| SEO | 4 | 3 | 5 | 4 | 3 | 4 | 4 | 4 |
| Mobile | 5 | 4 | 5 | 4 | 3 | 4 | 4 | 3 |
| **Ancrage communautaire local** | **5** | **3** | **1** | **2** | **0** | **1** | **1** | **2** |
| Confiance | 5 | 2 | 5 | 4 | 2 | 4 | 4 | 5 |

**Lecture.** Barkeelu perd sur la ligne « frais compétitifs » et ne cherche pas à la gagner. Il domine sur quatre lignes où Kopar est à 1 ou 2 : preuve d'usage des fonds, transparence, Telegram, ancrage communautaire. Ce sont les quatre lignes du positionnement.

---

## 12. Modèle économique Barkeelu

### 12.1 Le modèle retenu — VALIDÉ

**5 % au total, décomposés et affichés :**

- **4 % — commission Barkeelu**, supportée par le donateur en gross-up
- **1 % — frais de transfert Wave** du compte marchand vers le promoteur/bénéficiaire

Le montant enregistré comme don reste la valeur faciale ; les frais sont ajoutés au-dessus et facturés au donateur, de façon visible.

### 12.2 Mécanique corrigée des flux Wave

```
DONATEUR
   │  paie 105 000 (100 000 + 4 000 + 1 000)
   ▼
COMPTE MARCHAND WAVE BARKEELU        ← encaissement GRATUIT
   │  (fonds retenus en escrow jusqu'à
   │   fin de campagne + validation admin)
   ▼
PROMOTEUR / BÉNÉFICIAIRE (particulier)  ← 1 % prélevé par Wave sur ce transfert
```

Point clé corrigé par rapport à la v1.0 : **l'encaissement vers le compte marchand est gratuit**. Le 1 % Wave s'applique **uniquement** au transfert sortant du compte marchand vers un particulier. Le coût Wave est donc un coût de **payout**, pas de collecte.

### 12.3 Conséquences de la correction

**a) Le modèle devient strictement linéaire, donc parfaitement lisible.**

Aucun effet de plafond ne vient brouiller l'affichage côté donateur. Quel que soit le montant, le donateur voit la même règle :

```
Votre don                        100 000 FCFA
Commission Barkeelu (4 %)          4 000 FCFA
Frais de transfert Wave (1 %)      1 000 FCFA
─────────────────────────────────────────────
Total débité                     105 000 FCFA
Reçu par le bénéficiaire         100 000 FCFA
```

C'est exactement l'argument « explicite, clair et détaillé » du positionnement. Le donateur sait que **100 % de son don nominal arrive au bénéficiaire**.

**b) Le payout groupé devient une optimisation légitime.**

Si le 1 % Wave sortant est plafonné (le plafond usuel constaté chez Wave est de 5 000 FCFA par transaction), alors un décaissement unique en fin de campagne coûte nettement moins que la somme des 1 % collectés auprès des donateurs.

Exemple sur une campagne de 10 000 000 FCFA :

```
Collecté auprès des donateurs au titre du 1 %      100 000 FCFA
Coût réel d'un décaissement unique (si plafonné)     5 000 FCFA
Écart                                               95 000 FCFA
```

**C'est un point de décision éthique et commercial, pas un détail technique.** Trois options :

| Option | Description | Effet sur la confiance |
|---|---|---|
| A — Marge silencieuse | Barkeelu conserve l'écart sans le dire | Incohérent avec le positionnement transparence. **À éviter.** |
| B — Restitution | L'écart est reversé au bénéficiaire | Argument marketing très fort : « nous vous rendons ce que nous n'avons pas payé » |
| C — Frais réels | Le 1 % est facturé au coût réel constaté, affiché après coup dans le ledger | Le plus transparent, mais le plus complexe à afficher au moment du don |

**Recommandation : option B ou C.** L'option A est irréconciliable avec une plateforme qui vend la redevabilité et se ferait retourner contre vous au premier audit citoyen.

Le dispositif d'escrow que vous avez déjà conçu (rétention jusqu'à fin de campagne + validation admin) devient donc doublement justifié : **confiance et optimisation de coût**.

**c) Points à vérifier contractuellement — À VALIDER**

1. **Assiette du 1 %.** Est-il *déduit* du montant envoyé ou prélevé *en sus* sur le solde marchand ? Si déduit, un envoi de 100 000 fait recevoir 99 000 au promoteur, et il faut envoyer 101 010 pour qu'il touche 100 000 net. La promesse « le bénéficiaire reçoit 100 % de votre don » dépend entièrement de cette mécanique.
2. **Plafonnement du transfert sortant.** Existe-t-il, et à quel niveau ?
3. **Plafond de transaction sortante.** Un décaissement de plusieurs millions passe-t-il en une opération, ou faut-il fractionner (ce qui multiplierait le 1 % et annulerait le bénéfice du groupage) ?
4. **Bulk Pay.** Le décaissement de masse est-il tarifé différemment du transfert unitaire ?
5. **Taxe de 0,5 % sur les paiements marchands** (loi n° 2025-17) : s'applique-t-elle aux encaissements du compte marchand Barkeelu ? Si oui, elle réduit la marge de 4 % à 3,5 %.

### 12.4 Scénarios par montant de don

Hypothèse : encaissement gratuit, 1 % au payout, payout groupé en fin de campagne.

| Don nominal | Payé par le donateur | Commission Barkeelu | Provision transfert (1 %) | Reçu par le bénéficiaire |
|---|---|---|---|---|
| 10 000 | 10 500 | 400 | 100 | 10 000 |
| 50 000 | 52 500 | 2 000 | 500 | 50 000 |
| 100 000 | 105 000 | 4 000 | 1 000 | 100 000 |
| 500 000 | 525 000 | 20 000 | 5 000 | 500 000 |
| 1 000 000 | 1 050 000 | 40 000 | 10 000 | 1 000 000 |
| 10 000 000 | 10 500 000 | 400 000 | 100 000 | 10 000 000 |

**Marge brute Barkeelu = 4 % du collecté**, moins la taxe éventuelle de 0,5 %, moins les coûts de payout réels si inférieurs à la provision de 1 % (voir 12.3.b).

### 12.5 Scénarios de volume et seuil de rentabilité

Hypothèses : don moyen 25 000 FCFA, environ 20 dons par campagne, soit 500 000 FCFA collectés par campagne.

| Campagnes / mois | Collecté / mois | Marge brute Barkeelu (4 %) |
|---|---|---|
| 10 | 5 000 000 | 200 000 |
| 100 | 50 000 000 | 2 000 000 |
| 500 | 250 000 000 | 10 000 000 |
| 1 000 | 500 000 000 | 20 000 000 |
| 10 000 | 5 000 000 000 | 200 000 000 |

**Seuil de rentabilité.** Avec une charge d'exploitation mensuelle estimée entre 3 et 5 millions FCFA (développement, hébergement, support, modération, conformité), le point mort se situe autour de **150 à 250 campagnes par mois**. **Hypothèse à valider** : les charges d'exploitation n'ont pas été sourcées et doivent être budgétées précisément.

### 12.6 Analyse de sensibilité

Les trois variables qui déplacent le plus le résultat, par ordre d'impact :

1. **Taux d'abandon au moment du gross-up.** Le donateur voit 105 000 au lieu de 100 000. Si la conversion baisse de 15 %, l'effet dépasse largement tout gain de marge. **Variable la plus critique — à mesurer en A/B dès le MVP.**
2. **Taxe de 0,5 %** sur les paiements marchands : marge de 4 % ramenée à 3,5 %, soit −12,5 % de revenu.
3. **Mix de paiement.** La carte bancaire pour la diaspora coûte 2,9 % et plus, contre un encaissement mobile money gratuit. Chaque point de part de marché gagné par la carte coûte environ 0,03 point de marge. Sur un positionnement diaspora, ce n'est pas marginal — il faut privilégier les rails Wave diaspora quand ils existent.

---

## 13. Analyse des frais 4 % + 1 %

### 13.1 Cohérence documentaire — action requise

Le modèle est désormais fixé à **5 %**. Deux documents doivent être mis en conformité :

- **Business Model Canvas** : indique « 6 % prélevés sur les fonds collectés » → **à corriger en 5 %**.
- **Plugins Paymattic et GiveWP en production** : configurés à 4 % + 2 % = 6 % → **à corriger en 4 % + 1 %**. La configuration actuelle surfacture les donateurs d'un point complet. C'est un correctif prioritaire avant toute campagne réelle.

### 13.2 Compétitivité face à Kopar

Kopar est à 0 % de commission plateforme, avec 1 % de frais de retrait mobile money et 1,5 % vers compte bancaire. La comparaison brute est défavorable et le restera. **Elle ne doit donc pas être menée sur ce terrain.**

L'argumentaire de vente n'est pas « nous sommes moins chers », c'est :

> « Chez Barkeelu, vous savez exactement où va chaque franc. 100 % de votre don arrive au bénéficiaire. Les 5 % que vous payez en plus financent la vérification de la campagne, le suivi, et les preuves photo et vidéo de l'utilisation des fonds — que vous recevrez. »

C'est la différence entre un tuyau de paiement et une plateforme de redevabilité.

### 13.3 La question des très grosses collectes — RECOMMANDÉ

Sur une campagne à 50 millions FCFA, 4 % représentent 2 millions de commission. Les grands organisateurs communautaires — un dahira mobilisant sa base, une association levant pour un hôpital — feront le calcul et négocieront.

**Recommandation : annoncer d'emblée une dégressivité plutôt que la subir.** Par exemple :

| Tranche collectée par campagne | Commission Barkeelu |
|---|---|
| 0 – 5 000 000 FCFA | 4 % |
| 5 000 001 – 20 000 000 FCFA | 3 % |
| Au-delà de 20 000 000 FCFA | 2 % |

Cette grille reste explicite et détaillée, conforme au positionnement, tout en sécurisant les campagnes majeures. Elle transforme une objection prévisible en argument commercial. **À tester avant généralisation.**

---

## 14. Commission promoteur 1 à 1,5 %

**Ambiguïté à lever — À VALIDER.**

Le brief initial prévoyait une rémunération du promoteur de 1 à 1,5 % de la somme recueillie après frais, avec un exemple aboutissant à 93 575 FCFA nets sur un don de 100 000. **Cette formulation est incompatible avec le modèle retenu.**

Dans le modèle 5 % en gross-up, le bénéficiaire reçoit **100 % du don nominal**. Y prélever 1 à 1,5 % supplémentaires contredirait frontalement la promesse faite au donateur et détruirait l'argument central du positionnement.

Deux lectures possibles du terme « promoteur » :

| Lecture | Traitement recommandé |
|---|---|
| **Promoteur = porteur de campagne** (celui qui reçoit les fonds) | **Aucune commission.** Il reçoit 100 % du nominal. Toute ponction ici est à proscrire. |
| **Promoteur = ambassadeur / apporteur d'affaires** (celui qui amène des campagnes ou des dons) | **Coût d'acquisition**, financé **sur la marge de 4 % de Barkeelu**, jamais sur le bénéficiaire. Plafonné et mesurable. |

**Recommandation.** Réserver toute commission dite « promoteur » à un **programme d'ambassadeurs**, financé sur la marge Barkeelu, avec suivi par referral tracking. C'est cohérent avec le Business Model Canvas, qui prévoit déjà des commissions d'affiliation de 1 à 1,5 % sur les dons apportés par des partenaires. **Il s'agit d'une charge, pas d'un revenu.** À clarifier explicitement dans la documentation produit pour éviter toute ambiguïté comptable.

---

## 15. Réglementation

### 15.1 Détention des fonds — point critique

Depuis le **1er mai 2025** (fin de la période transitoire prévue par l'avis BCEAO n° 004-03-2025), toute structure non agréée doit cesser d'offrir des services de paiement dans l'UMOA.

- **Instruction n° 001-01-2024** (23 janvier 2024, services de paiement) : capital de 10 à 100 millions FCFA selon les services, forme SA, SARL ou coopérative — la société unipersonnelle est exclue — délai d'instruction de 6 mois.
- **Instruction n° 008-05-2015** (monnaie électronique) : capital de 300 millions FCFA, objet social exclusif, cantonnement des fonds avec au moins 75 % en dépôts à vue.
- En février 2026, la BCEAO avait agréé 31 établissements de paiement dans l'UEMOA, dont 11 au Sénégal.

**Recommandation MVP — RECOMMANDÉ.** Barkeelu **ne doit pas détenir les fonds en propre**. L'escrow doit être techniquement porté par le **compte marchand Wave**, qui relève d'un établissement agréé. Barkeelu opère alors comme prestataire technique et non comme dépositaire.

**Nuance à faire valider — À VALIDER.** La rétention des fonds sur le compte marchand jusqu'à la fin de campagne et l'approbation administrative pourrait néanmoins être analysée comme une activité de détention de fonds de tiers. **Ce point exige une consultation juridique bancaire à Dakar et, idéalement, une position écrite de Wave.** C'est le principal risque réglementaire du projet.

### 15.2 Protection des données — CDP

Loi n° 2008-12 du 25 janvier 2008 : déclaration préalable obligatoire auprès de la Commission de protection des données personnelles, récépissé sous environ un mois. **Autorisation préalable** requise pour les données sensibles. Sanctions de 1 à 100 millions FCFA.

**Deux catégories sensibles concernent directement Barkeelu :**
- **Données de santé** — les campagnes médicales (diagnostics, évacuations sanitaires) en collectent par nature. Autorisation préalable probablement requise.
- **Opinions politiques** — raison supplémentaire de proscrire les cagnottes politiques.
- **Convictions religieuses** — une collecte pour un dahira révèle l'appartenance confessionnelle du donateur. **Point d'attention spécifique au positionnement mouride :** la liste publique des donateurs doit être opt-in, avec possibilité de don anonyme par défaut.

### 15.3 LBC-FT — CENTIF

Statut d'assujetti probable : obligations de connaissance client, vigilance sur le bénéficiaire effectif, déclaration des opérations suspectes, conservation des données pendant 10 ans, dispositif de contrôle interne. La CENTIF a signalé fin 2024 une hausse des typologies de fraude en ligne.

**Point d'attention diaspora :** les flux entrants depuis l'Europe et l'Amérique du Nord vers des bénéficiaires sénégalais, sur des montants agrégés significatifs, entrent dans le champ de la vigilance renforcée. Le dispositif KYC doit être calibré en conséquence dès le MVP.

### 15.4 Fiscalité

Loi n° 2025-17 du 27 septembre 2025 : prélèvement de 0,5 % à la source sur les paiements reçus par les commerçants via solutions électroniques. **Application aux encaissements Barkeelu à confirmer.** Par ailleurs, le statut fiscal des sommes transitant par la plateforme (produit d'exploitation ou fonds de tiers) doit être arbitré avec un expert-comptable : c'est une différence considérable en assiette de TVA et d'impôt sur les sociétés.

**Niveau de confiance global.** Élevé sur l'existence et le contenu des textes ; **moyen** sur la qualification exacte de l'activité de cagnotte avec escrow. Rien de ce qui précède ne constitue un conseil juridique.

---

## 16. Trust & Safety

### 16.1 Le risque n° 1 du positionnement : la preuve truquée

La preuve photo/vidéo est le cœur de votre proposition de valeur. **C'est donc aussi votre plus grande surface d'attaque.** Une photo peut être recyclée d'une autre collecte, empruntée à Internet, mise en scène, ou montrer un achat réel immédiatement revendu.

Un seul cas de preuve falsifiée sur une campagne médicale, relayé sur les réseaux, détruirait plus de capital de confiance que dix campagnes réussies n'en construisent — a fortiori sur un segment communautaire où la réputation circule vite et où la déception se vit collectivement.

**Dispositif de vérification recommandé, par ordre de robustesse :**

| Niveau | Mécanisme | Coût | MVP ? |
|---|---|---|---|
| 1 | Horodatage serveur de l'upload | Nul | MUST |
| 2 | Conservation des métadonnées EXIF (date, appareil, géolocalisation si disponible) | Faible | MUST |
| 3 | Détection de réutilisation d'image (hash perceptuel contre la base interne) | Faible | SHOULD |
| 4 | **Contre-signature d'un tiers de confiance** — le dahira, l'hôpital, l'école, l'imam, le chef de quartier | Organisationnel | **MUST sur les campagnes > seuil** |
| 5 | Vérification terrain par un ambassadeur Barkeelu local | Élevé | Campagnes majeures uniquement |

**Le niveau 4 est le plus important et le moins coûteux techniquement.** Il s'appuie précisément sur la structure communautaire que vous ciblez : dans l'écosystème mouride, les dahiras et les autorités religieuses locales constituent un réseau de validation préexistant, dense et crédible. **C'est un avantage difficile à copier pour un concurrent purement technologique.**

### 16.2 Typologies de fraude à couvrir

Fausse campagne, faux bénéficiaire, usurpation d'identité, fraude mobile money, blanchiment, campagne médicale frauduleuse, auto-dons destinés à créer un effet d'amorçage, faux commentaires et faux donateurs, chargebacks carte, détournement après décaissement, **preuve d'usage falsifiée** (voir 16.1).

### 16.3 Pipeline recommandé

```
Risk Score
    ↓
KYC bénéficiaire
    ↓
Campaign Review (validation éditoriale avant publication)
    ↓
Payment Monitoring
    ↓
Payout Controls (validation admin avant décaissement)
    ↓
Proof Verification (preuves d'usage vérifiées)
    ↓
Audit trail immuable
```

**Périmètre MVP minimal :** KYC bénéficiaire, revue manuelle avant publication, validation administrative avant le premier décaissement, journal d'audit immuable, signalement et blocage, horodatage des preuves.

### 16.4 Enseignement du cas Kopar

Le blocage d'un décaissement chez Kopar, attribué à un signalement de fraude carte via Stripe, plaide pour **privilégier le mobile money local** et **encadrer strictement la carte internationale** : plafonds par donateur, délai de rétention plus long sur les fonds d'origine carte, réserve pour chargebacks.

---

## 17. Laravel vs Django

| Critère | Laravel | Django |
|---|---|---|
| Réutilisation Yessal Core | **Directe** (Laravel, PHP 8.3, Sanctum, PostgreSQL) | Réécriture complète |
| Écosystème paiement / multi-tenant / permissions | Très riche | Riche |
| Vivier de développeurs au Sénégal | **Fort** (PHP dominant) | Plus restreint |
| Rapidité de MVP | **Élevée** | Moyenne (compte tenu de l'existant) |
| Data science / IA anti-fraude | Faible nativement | **Fort** |
| Queues, webhooks, events | Excellent (Horizon, Redis) | Bon (Celery) |

**Recommandation : Laravel pour le cœur transactionnel**, Python réservé au service Telegram et, plus tard, au scoring de risque anti-fraude — communication par API et files de messages. Cette architecture bimodale capte le meilleur des deux sans imposer de réécriture du Yessal Core.

**Décision : VALIDÉ.**

---

## 18. Aimeos vs Lunar vs RiseLab vs custom

- **Aimeos et Lunar** sont des moteurs e-commerce (catalogue, panier, commande). Le modèle don → ledger → payout diffère fondamentalement du modèle produit → commande. **Mauvais alignement.**
- **RiseLab** : à éviter comme socle (voir section 7).
- **Custom sur Laravel, réutilisant le Yessal Core** : recommandé.

Le Yessal Core apporte Organizations, Plans, Subscriptions, Entitlements, Quotas, Payments et le multi-tenancy. **Ne pas présumer la réutilisation des modules Caisse.** Le Fundraising Engine, le Donation Engine, le Ledger et le Payout Engine sont spécifiques et doivent être construits sur mesure.

**Décision : construire le Barkeelu Fundraising Engine custom — RECOMMANDÉ.**

---

## 19. Flutter multi-plateforme

**Flutter Web est inadapté aux pages publiques de campagne.** Le rendu canvas est mal indexé par les moteurs de recherche, la première charge est lourde, et surtout les métadonnées OpenGraph — donc l'aperçu enrichi lors d'un partage WhatsApp — sont difficiles à produire correctement.

Or, sur votre positionnement, **le partage WhatsApp est le principal canal d'acquisition**. Une campagne partagée dans un groupe de dahira doit afficher immédiatement la photo, le titre, le montant collecté et l'objectif. C'est non négociable.

**Architecture recommandée :**

| Surface | Technologie | Justification |
|---|---|---|
| Pages publiques de campagne + tunnel de don | **Laravel SSR** (Blade ou Inertia) | SEO, OpenGraph, performance sur réseau lent, partage WhatsApp |
| App promoteur (dashboard, preuves, statistiques) | **Flutter** (Android, iOS) | Expérience riche, upload de preuves, notifications push |
| App donateur | **Flutter** (Android, iOS) | Historique, suivi des campagnes soutenues |
| Desktop et Web connecté | Plus tard, si besoin interne avéré | Non prioritaire |

**Décision : architecture hybride — RECOMMANDÉ. Flutter Web pour le public : À ÉVITER.**

---

## 20. Telegram, Python et automatisation

### 20.1 Architecture

```
Laravel
   ↓  Events / Queue / API
Service Python Telegram
   ↓
Telegram
```

Le bot **n'accède jamais directement à PostgreSQL**. Ce choix est validé : découplage, sécurité, et possibilité de faire évoluer les deux services indépendamment.

### 20.2 Priorité des canaux

**WhatsApp avant Telegram pour l'acquisition grand public.** WhatsApp est le canal dominant au Sénégal et dans la diaspora ; Telegram est plus présent dans certaines communautés organisées et pour les usages d'alerte.

| Canal | Usage principal | Priorité |
|---|---|---|
| **WhatsApp** | Partage de campagne, invitation à donner, reçu, remerciement | MUST |
| **Telegram** | Suivi de campagne, notifications d'avancement, canaux de dahiras, alertes admin, campagnes tendances | MUST (différenciant : Kopar est à 0 sur ce critère) |
| Email | Reçus, rapports de clôture, diaspora | SHOULD |
| Push | Promoteurs, donateurs récurrents | SHOULD |

### 20.3 Cas d'usage Telegram

Nouvelle campagne, nouveau don, campagne tendance, objectif atteint, campagne proche de l'objectif, campagne urgente, rappel de fin de collecte, **publication d'une preuve d'usage des fonds**, statistiques hebdomadaires, alertes administrateur, notifications promoteur.

Le cas d'usage « publication d'une preuve » est celui qui sert directement le positionnement : le donateur qui a contribué reçoit, dans Telegram, la photo de ce que son don a permis. **C'est la boucle de confiance qui referme le cycle du don.**

---

## 21. Gamification

Post-MVP, sans surcharger. Séquencement recommandé :

- **V1** : referral tracking (essentiel pour mesurer les ambassadeurs), badges de partage, compteur de campagnes soutenues.
- **V2** : niveaux de donateur, classements de campagnes, challenges communautaires, récompenses symboliques.

**Précaution culturelle importante.** Sur des collectes religieuses et humanitaires, la gamification du don peut être perçue comme déplacée, voire irrespectueuse — le don discret est une valeur forte dans la tradition islamique. Les mécaniques doivent porter sur **le partage et la mobilisation** (« vous avez fait connaître cette campagne à 12 personnes ») plutôt que sur le montant donné, et rester **désactivables**. À tester auprès des relais communautaires avant déploiement.

---

## 22. Architecture recommandée

```
Barkeelu
│
├── Yessal Core SaaS
│     Users · Organizations · Plans · Subscriptions
│     Entitlements · Quotas · Multi-tenancy
│
├── Fundraising Engine
│     Campaign · Beneficiary · Promoter · Team
│     Update · Referral · Verification
│
├── Donation Engine
│     Donor · Donation · Recurring Donation
│
├── Payment Engine
│     Wave (principal) · Orange Money · Free Money
│     Agrégateur carte (diaspora) · Webhook · Reconciliation
│
├── Financial Ledger
│     Ledger · LedgerEntry · Fee · Commission
│     Payout · Refund · Chargeback
│
├── Trust & Safety
│     KYC · Campaign Review · Risk Score
│     Proof Verification · Moderation · Audit
│
├── Notification Engine
│     WhatsApp · Telegram · Email · Push
│
└── Gamification (post-MVP)
```

**Stack :** Laravel, PostgreSQL, Redis, files de messages, site public en SSR, apps Flutter, service Python pour Telegram et le scoring de risque.

**Exigences techniques non négociables :**

- **Idempotence.** Wave retente la livraison des webhooks jusqu'à 5 fois sur 24 heures. Stocker l'identifiant d'événement et rejeter les doublons.
- **Confirmation par webhook signé uniquement.** Ne jamais valider un don sur le seul retour navigateur.
- **Ledger en double-entrée.** Chaque frais, chaque remboursement, chaque décaissement est une transaction distincte et consultable — modèle Open Collective.
- **Journal d'audit immuable** sur toutes les opérations administratives, en particulier les validations de décaissement.

**Décision : architecture initiale conservée avec ajustements — VALIDÉ.**

---

## 23. MVP

### MUST
- Compte et profil
- Création de campagne : objectif, durée, image, catégorie, bénéficiaire
- **KYC bénéficiaire**
- **Validation éditoriale avant publication**
- Don via **Wave** et **Orange Money**
- **Page publique en SSR** avec OpenGraph correct
- **Partage WhatsApp** natif
- **Affichage détaillé des frais 4 % + 1 % au moment du don**
- Dashboard promoteur
- **Escrow** : rétention jusqu'à fin de campagne + validation administrative
- Payout mobile money
- **Ledger double-entrée**
- **Upload de preuves photo/vidéo avec horodatage et EXIF**
- **Contre-signature tiers de confiance** au-delà d'un seuil de collecte
- Notifications Telegram (avancement, objectif atteint, preuve publiée)
- Journal d'audit
- Remerciements automatisés au donateur
- Statistiques publiques de campagne

### SHOULD
- Free Money
- Carte bancaire pour la diaspora, avec plafonds et rétention renforcée
- Commentaires et mises à jour
- Décaissement partiel dès seuil
- Reçus téléchargeables
- Don anonyme par défaut, liste publique en opt-in

### COULD
- Pourboire optionnel en complément des 5 %
- Referral tracking
- QR codes pour les collectes physiques
- Bot Telegram enrichi (campagnes tendances)

### LATER
Dons récurrents, contreparties, tout-ou-rien, gamification, API publique, multi-devises, abonnements ONG et CRM, marque blanche.

---

## 24. V1

- Free Money et carte diaspora généralisées
- Décaissement partiel
- **Programme d'ambassadeurs** avec referral tracking, financé sur la marge (voir section 14)
- **Campagnes vérifiées** — badge de confiance adossé à la contre-signature communautaire
- Automatisation WhatsApp complète
- Analytics promoteur avancées
- Rapport de clôture de campagne envoyé à tous les donateurs
- Premiers badges de partage
- **Dégressivité des frais sur les grosses campagnes** (voir 13.3), après test

---

## 25. V2

- Dons récurrents
- **Abonnements dahiras, associations et ONG** avec CRM léger — segment payant distinct (brique Donorbox)
- Choix du modèle de frais par le porteur (brique FundRazr)
- Contreparties, prévente, tout-ou-rien pour le segment créatif et entrepreneurial (brique Ulule)
- API publique
- Multi-devises et internationalisation diaspora
- Gamification complète
- Marque blanche pour les grandes organisations

---

## 26. SWOT

**Forces**
- Réutilisation du Yessal Core : time-to-market réduit
- Mobile money natif, encaissement Wave gratuit
- Modèle de frais lisible et assumé
- Dispositif de preuve d'usage des fonds — inexistant chez Kopar
- Ancrage communautaire mouride et diaspora
- Telegram : critère où le concurrent est à zéro

**Faiblesses**
- Notoriété nulle face à un acteur installé
- 5 % face à 0 % affiché : objection immédiate à traiter à chaque don
- Pas d'agrément propre, dépendance à Wave
- Dispositif de vérification des preuves coûteux en temps humain
- Ledger et réconciliation : exigence technique élevée

**Opportunités**
- Faiblesse documentée de Kopar sur le décaissement
- Diaspora à 2 211 milliards FCFA de transferts annuels
- Réseau de dahiras comme infrastructure de confiance préexistante
- Segment ONG et associations mal servi
- Défiance envers la collecte informelle après plusieurs affaires de détournement

**Menaces**
- Réplique de Kopar ou d'un opérateur mobile money
- Évolution réglementaire (agrément, taxe mobile money)
- **Un seul scandale de preuve falsifiée sur le segment communautaire**
- Dépendance à un unique partenaire de paiement
- Chargebacks sur les flux carte diaspora

---

## 27. Positionnement face à Kopar Express

### La question centrale

> Pourquoi un Sénégalais choisirait-il Barkeelu plutôt que Kopar Express, alors que Kopar est gratuit ?

**Réponse.** Parce que sur Kopar, il donne et n'entend plus jamais parler de son argent. Sur Barkeelu, il paie 5 % et reçoit en échange : la certitude que la campagne a été vérifiée, le suivi de l'avancement, la preuve en photo et en vidéo de ce que son don a permis, et un remerciement nominatif. **Il n'achète pas un transfert, il achète une redevabilité.**

### Segment prioritaire : communauté mouride et diaspora

Le choix est stratégiquement solide pour trois raisons :

1. **La mobilisation existe déjà et à grande échelle.** Le précédent de Touba Ca Kanam démontre la capacité de la communauté à lever des montants considérables. Le besoin n'est pas de créer la générosité, mais de l'outiller.
2. **Le réseau de dahiras est une infrastructure de confiance préexistante**, dense au Sénégal comme dans la diaspora (Italie, France, Espagne, États-Unis). C'est simultanément un canal d'acquisition, un mécanisme de vérification (contre-signature) et une barrière à l'entrée pour un concurrent purement technologique.
3. **La diaspora a un besoin de preuve structurellement plus fort** que le donateur local. Elle envoie de loin, ne peut pas vérifier sur place, et a souvent été échaudée. Votre dispositif de preuve répond exactement à sa douleur principale.

**Exigence de contrepartie.** Une adhésion obtenue par voie communautaire est puissante mais impitoyable : la rigueur y est une condition d'existence, pas un avantage concurrentiel. Le premier détournement non détecté fermerait la porte durablement.

### Trois avantages compétitifs immédiats

1. **Décaissement rapide et fiable, avec engagement de délai public** — là où Kopar accumule les plaintes.
2. **Preuves d'usage des fonds** en photo et vidéo, horodatées et contre-signées.
3. **Notifications Telegram** de suivi de campagne — critère où Kopar est totalement absent.

### Trois avantages difficiles à copier

1. **Le réseau de contre-signature communautaire** (dahiras, structures religieuses, écoles, hôpitaux). Il se construit par la relation, pas par le code.
2. **La transparence financière de type ledger public** : elle exige une architecture pensée dès l'origine, pas un module ajouté après coup.
3. **La légitimité communautaire** : elle ne s'achète pas en publicité.

### Trois avantages à construire à moyen terme

1. **Suite pour dahiras et associations** : gestion des membres, cotisations, CRM, reçus — le prolongement naturel de l'ERP/CRM prévu au Business Model Canvas.
2. **Rails diaspora optimisés** : réduire le coût et la friction du don depuis l'Europe et l'Amérique du Nord.
3. **Gamification du partage** et programme d'ambassadeurs structuré.

### Formule de positionnement

```
Fundraising
+ Mobile Money natif
+ Diaspora
+ Communauté mouride
+ Telegram / WhatsApp
+ Preuve d'usage des fonds
+ Transparence tarifaire
= Infrastructure de redevabilité communautaire
```

---

## 28. Business Model

**Revenu principal — VALIDÉ**
Commission Barkeelu de 4 % en gross-up sur le donateur, avec 1 % de frais de transfert Wave affiché séparément. Total 5 %, explicite et détaillé.

**Dégressivité — RECOMMANDÉ, à tester**
4 % jusqu'à 5 M FCFA par campagne, 3 % de 5 à 20 M, 2 % au-delà.

**Revenus complémentaires**
- V1 : pourboire optionnel du donateur, en supplément des 5 %
- V2 : abonnements dahiras, associations et ONG (CRM, cotisations, reçus, reporting)
- V2 : services premium — campagne vérifiée renforcée, mise en avant, marque blanche
- Selon le Business Model Canvas : sponsoring, mécénat, fonds de solidarité nationale

**Charges spécifiques**
- Commissions d'affiliation aux ambassadeurs, de 1 à 1,5 %, **imputées sur la marge**
- Vérification des preuves : temps humain, principal poste variable
- Modération et validation éditoriale
- Conformité : KYC, LBC-FT, CDP

**Principe directeur.** Barkeelu ne détient pas les fonds. L'escrow est porté par le compte marchand Wave.

---

## 29. Risques

| Risque | Gravité | Traitement |
|---|---|---|
| **Preuve falsifiée non détectée** | **Critique** | Contre-signature communautaire obligatoire au-delà d'un seuil ; hash perceptuel ; EXIF |
| Requalification réglementaire de l'escrow | Élevée | Consultation juridique bancaire à Dakar + position écrite de Wave |
| Objection tarifaire face au 0 % de Kopar | Élevée | Argumentaire de redevabilité ; A/B test sur le gross-up ; dégressivité annoncée |
| Taxe de 0,5 % sur les paiements marchands | Moyenne | Confirmation Wave et fiscaliste ; provisionner 3,5 % de marge nette |
| Dépendance à Wave | Moyenne | Ajouter Orange Money dès le MVP ; agrégateur en V1 |
| Chargebacks carte diaspora | Moyenne | Plafonds, rétention renforcée, réserve dédiée |
| Cagnottes politiques par dérive d'usage | Élevée | Interdiction explicite en CGU + modération éditoriale |
| Perception de la gamification sur le don religieux | Faible à moyenne | Mécaniques orientées partage, désactivables, testées avec les relais |
| Exécution du ledger et de l'idempotence | Moyenne | Tests de charge, réconciliation quotidienne, journal d'audit |

---

## 30. Recommandations finales

### VALIDÉ
1. Modèle 4 % Barkeelu + 1 % Wave = 5 %, affiché de façon explicite et détaillée au donateur.
2. Encaissement gratuit sur compte marchand Wave ; le 1 % s'applique au transfert sortant vers le bénéficiaire.
3. Positionnement transparence et redevabilité, segment humanitaire et social, communauté mouride et diaspora.
4. Laravel custom avec réutilisation du Yessal Core.
5. Architecture cible conservée avec ajustements.

### RECOMMANDÉ
6. Ne pas détenir les fonds : escrow porté par le compte marchand Wave.
7. Site public en Laravel SSR ; Flutter réservé aux apps connectées.
8. Ledger double-entrée et journal d'audit dès le MVP.
9. Contre-signature par un tiers de confiance communautaire au-delà d'un seuil de collecte.
10. Restituer ou refacturer au réel l'écart entre le 1 % collecté et le coût de payout effectif (options B ou C, section 12.3).
11. Dégressivité annoncée sur les campagnes supérieures à 5 M FCFA.
12. Commission « promoteur » traitée comme un coût d'acquisition sur la marge, jamais comme une ponction sur le bénéficiaire.

### À TESTER
13. Taux de conversion avec gross-up affiché à 105 % — variable la plus critique du modèle.
14. Pourboire optionnel en complément des 5 %.
15. Grille de dégressivité.
16. Mécaniques de gamification auprès des relais communautaires.

### À VALIDER
17. Assiette exacte du 1 % Wave : déduit ou en sus.
18. Plafonnement du transfert sortant et plafond de transaction unitaire.
19. Application de la taxe de 0,5 % aux encaissements Barkeelu.
20. Qualification réglementaire de la rétention temporaire sur compte marchand.
21. Obligations CDP pour les données de santé et les convictions religieuses.
22. Statut fiscal des sommes transitant par la plateforme.

### À ÉVITER
23. RiseLab comme socle technique.
24. Flutter Web pour les pages publiques.
25. Toute ponction supplémentaire sur le bénéficiaire au-delà des 5 % annoncés.
26. Marge silencieuse sur l'écart de frais de payout.
27. Cagnottes politiques.
28. Confrontation tarifaire frontale avec Kopar.

### HORS MVP
Dons récurrents, contreparties, tout-ou-rien, gamification, API publique, multi-devises, abonnements ONG, marque blanche.

---

## Actions immédiates

1. **Corriger la configuration des plugins Paymattic et GiveWP** de 4 % + 2 % vers 4 % + 1 %. La configuration actuelle surfacture les donateurs d'un point.
2. **Mettre à jour le Business Model Canvas** : remplacer « 6 % prélevés sur les fonds collectés » par le modèle 4 % + 1 %.
3. **Obtenir par écrit de Wave** : assiette du 1 % sortant, plafonnement éventuel, plafond de transaction, tarification du décaissement de masse, applicabilité de la taxe de 0,5 %.
4. **Consulter un juriste bancaire à Dakar** sur la qualification de l'escrow sur compte marchand.
5. **Concevoir le workflow de vérification des preuves** avant d'écrire la première ligne du module — c'est le cœur du positionnement.
6. **Identifier trois à cinq dahiras pilotes** disposés à jouer le rôle de tiers de confiance contre-signataire.

---

## Question stratégique finale

> Quelle combinaison de produit, modèle économique, technologie, paiements locaux, confiance, diaspora, communauté et acquisition permettra à Barkeelu de battre Kopar Express, puis de devenir une infrastructure de fundraising de référence au Sénégal et en Afrique de l'Ouest ?

**Réponse.** Barkeelu ne bat pas Kopar sur le prix, et n'essaie pas. Il gagne en vendant ce que Kopar donne gratuitement mais ne fournit pas : **la certitude**. Un modèle à 5 % assumé, détaillé au franc près devant le donateur, adossé à un dispositif de preuve d'usage des fonds contre-signé par le réseau communautaire, financé par une architecture qui ne détient pas les fonds et qui trace chaque mouvement dans un ledger consultable.

Le segment d'entrée — humanitaire et social, communauté mouride, diaspora — n'est pas un compromis, c'est le segment où la douleur est la plus forte et où l'infrastructure de confiance existe déjà, hors de la plateforme. Barkeelu ne crée pas la confiance : il l'outille et la rend vérifiable.

**Ce n'est pas une guerre des prix. C'est une guerre de la preuve — et c'est celle-là qu'il faut mener.**

---

## Bibliographie et sources

**Réglementation**
- BCEAO — Instruction n° 001-01-2024 du 23 janvier 2024 relative aux services de paiement ; Instruction n° 008-05-2015 régissant la monnaie électronique ; Avis n° 004-03-2025 (fin de période transitoire au 1er mai 2025) — bceao.int
- Agence Ecofin — « UMOA : les prestataires de paiement ont jusqu'au 1er mai 2025 pour obtenir un agrément »
- Direction générale du Trésor de Côte d'Ivoire — agrément de 31 nouveaux établissements de monnaie électronique dans l'UEMOA (février 2026)
- Loi n° 2008-12 du 25 janvier 2008 sur la protection des données personnelles — cdp.sn
- Loi n° 2025-17 du 27 septembre 2025 modifiant le Code général des Impôts ; Agence Ecofin, « Sénégal : la taxe sur le Mobile Money, un choix audacieux aux retombées incertaines » ; Forbes Afrique ; Finance in Africa
- CENTIF — obligations des assujettis

**Marché et paiements**
- APS — « Les transferts d'argent de la diaspora s'élèvent à 2 211 milliards de FCFA en 2024 » (décembre 2025)
- GSMA via Agence Ecofin — évolution des comptes mobile money 2013-2023
- CIO Mag — déclarations de Coura Carine Sène, directrice générale de Wave Sénégal
- Sonatel — résultats financiers 2025 (Sikafinance, allAfrica)
- TechAfrica News — pénétration mobile au Sénégal, données ARTP
- AFI, BCEAO — inclusion financière
- Kolonell, AfroTools, MomoCalc — grilles tarifaires Wave, Orange Money, Free Money (**sources prestataires, à confirmer contractuellement**)
- Orange Business Sénégal — paiement marchand
- Agence Ecofin — rachat de PayDunya par Peach Payments (avril 2025)

**Concurrence**
- koparexpress.org et koparexpress.com — pages collectes, crowdfunding, conditions de décaissement
- App Store et Google Play — avis utilisateurs Kopar Express (**signaux de perception, non preuves**)
- Senego, Dakaractu, Seneweb, SENTV, Exclusif, Rewmi — affaire Hannibal Djim, litiges GIE non-voyants et UASZ (**instruction en cours, pas de condamnation définitive confirmée**)

**Benchmarks internationaux**
- gofundme.com — pricing et transparence ; Host Merchant Services ; Factually
- outils.ulule.com — frais et commissions ; Blog du Modérateur ; Clubic
- Donorbox — Software Connect, Crowded Finance
- fundrazr.com — Merchant Maverick
- documentation.opencollective.com — ledger, changelog, tarification
- resources.viserlab.com — RiseLab Crowdfunding Platform ; CodeCanyon

**Note méthodologique.** Les tarifs mobile money proviennent en partie de blogs de prestataires et non de grilles officielles Wave ou Orange ; ils sont cohérents entre eux mais doivent être confirmés contractuellement. Le sens du 1 % Wave a été corrigé sur la base de l'expérience opérationnelle directe du compte marchand Barkeelu, qui prime ici sur les sources publiques contradictoires. Les avis utilisateurs relatifs à Kopar Express sont des signaux de perception et ne constituent pas la preuve d'une défaillance systémique. Aucun élément de ce rapport ne constitue un conseil juridique, fiscal ou financier.
