# Barkeelu â€” Design System UI/UX

**Version :** 0.1  
**Statut :** proposition initiale Ã  valider  
**PÃ©rimÃ¨tre :** site public, parcours de don, espaces authentifiÃ©s et administration  
**RÃ©fÃ©rences :** GoFundMe (simplicitÃ© et conversion), Ulule (dÃ©couverte et narration), sans reproduction de leur identitÃ©.

## 1. Principes produit

Barkeelu doit paraÃ®tre simple, humain, fiable et local. Lâ€™interface privilÃ©gie :

1. la comprÃ©hension immÃ©diate de la cause ;
2. la confiance avant lâ€™action ;
3. un don rÃ©alisable rapidement sur mobile ;
4. la transparence sur le bÃ©nÃ©ficiaire, la collecte et les frais ;
5. la dÃ©couverte Ã©ditoriale des campagnes et de leur impact.

Le design ne doit jamais suggÃ©rer quâ€™un paiement, un contrÃ´le KYC ou un dÃ©caissement est confirmÃ© avant validation par le serveur.

## 2. IdentitÃ© visuelle

### 2.1 Logos

- **Logo horizontal :** en-tÃªte desktop, pied de page, communications officielles.
- **Logo carrÃ© :** favicon, icÃ´ne dâ€™application, avatar, navigation mobile compacte.
- Conserver les proportions, les couleurs et une zone de protection Ã©gale Ã  environ 25 % de la hauteur du symbole.
- Ne pas dÃ©former, recolorer, incliner ou placer le logo sur un fond insuffisamment contrastÃ©.
- PrÃ©voir ultÃ©rieurement des variantes vectorielles SVG et monochromes validÃ©es.

### 2.2 Palette principale

| Jeton | Valeur | Usage principal |
|---|---:|---|
| `brand-primary` | `#6257E2` | CTA principal, liens actifs, focus, progression |
| `brand-primary-hover` | `#5146CE` | Survol du CTA principal |
| `brand-primary-soft` | `#EFEDFF` | Fonds lÃ©gers et sÃ©lections |
| `brand-secondary` | `#FEA500` | Accent, mise en avant et actions secondaires |
| `brand-secondary-hover` | `#E89200` | Survol secondaire |
| `brand-secondary-soft` | `#FFF4DC` | Badges et fonds dâ€™accent |
| `brand-symbol-green` | `#39C43A` | Couleur patrimoniale du symbole, usage limitÃ© |

Le violet demeure la couleur fonctionnelle dominante. Lâ€™orange sert dâ€™accent. Le vert du logo ne devient pas une troisiÃ¨me couleur dâ€™action concurrente.

### 2.3 Neutres et Ã©tats

| Jeton | Valeur | Usage |
|---|---:|---|
| `neutral-950` | `#17171C` | Titres et texte fort |
| `neutral-700` | `#4E4D58` | Texte courant |
| `neutral-500` | `#777582` | MÃ©tadonnÃ©es |
| `neutral-300` | `#D8D6E0` | Bordures |
| `neutral-100` | `#F5F4F8` | Fonds secondaires |
| `neutral-0` | `#FFFFFF` | Surface principale |
| `success` | `#168A48` | SuccÃ¨s confirmÃ© |
| `warning` | `#B86A00` | Attention |
| `danger` | `#C73535` | Erreur ou action destructive |
| `info` | `#2563B8` | Information neutre |

Les Ã©tats ne reposent jamais uniquement sur la couleur : ajouter icÃ´ne, libellÃ© ou message.

## 3. Typographie

Police principale : **Kodchasan**, Google Fonts.

- Poids courant recommandÃ© : `500` (Medium), identitÃ© officielle.
- PrÃ©voir `600` pour les titres et CTA si le fichier de police est chargÃ©.
- Fallback : `Kodchasan, system-ui, sans-serif`.
- Corps mobile : 16 px minimum ; petits libellÃ©s : 13â€“14 px minimum.
- Interligne : 1,45 Ã  1,6 pour le contenu narratif.
- Les montants utilisent des chiffres tabulaires si disponibles.

| Style | Mobile | Desktop | Poids |
|---|---:|---:|---:|
| Display | 36/42 | 56/64 | 600 |
| H1 | 30/38 | 44/52 | 600 |
| H2 | 25/32 | 34/42 | 600 |
| H3 | 21/28 | 26/34 | 600 |
| Body | 16/24 | 16/26 | 500 |
| Small | 14/20 | 14/20 | 500 |

## 4. Grille, espacements et formes

- Conception mobile-first Ã  partir de 320 px.
- Conteneur desktop : largeur maximale 1200 px, gouttiÃ¨res 24 px.
- Grille desktop : 12 colonnes ; tablette : 8 ; mobile : 4.
- Ã‰chelle dâ€™espacement : 4, 8, 12, 16, 24, 32, 48, 64, 96 px.
- Rayon : 10 px pour champs/boutons, 16 px pour cartes, 24 px pour blocs Ã©ditoriaux.
- Ombres discrÃ¨tes ; privilÃ©gier bordure et espace blanc.
- Cibles tactiles : 44 Ã— 44 px minimum.

## 5. Composants fondamentaux

### 5.1 Boutons

- **Primaire :** fond violet, texte blanc ; action principale unique par zone.
- **Secondaire :** fond blanc, bordure violette, texte violet.
- **Accent :** orange, rÃ©servÃ© Ã  une mise en avant non concurrente avec Â« Faire un don Â».
- **Destructif :** rouge, avec confirmation explicite.
- Ã‰tats obligatoires : dÃ©faut, hover, focus visible, pressÃ©, chargement, dÃ©sactivÃ©.

### 5.2 Formulaires

- LibellÃ© persistant au-dessus du champ ; placeholder uniquement comme exemple.
- Erreur placÃ©e sous le champ et rÃ©sumÃ©e en haut pour les longs formulaires.
- Sauvegarde de brouillon pour la crÃ©ation de campagne.
- Montants saisis et affichÃ©s en FCFA, mais transmis selon le contrat API en entier XOF.
- Aucun secret ou document KYC ne doit apparaÃ®tre dans une URL, une notification ou un aperÃ§u public.

### 5.3 Carte campagne

Contenu minimal : image, catÃ©gorie future, titre, organisateur, localisation Ã©ventuelle, montant collectÃ©, objectif, barre de progression et Ã©tat de vÃ©rification explicite.

La carte entiÃ¨re est cliquable, mais les liens internes restent accessibles au clavier. Ã‰viter les compteurs ou badges non encore calculÃ©s par le backend.

### 5.4 Confiance

Composants distincts :

- identitÃ©/organisation vÃ©rifiÃ©e ;
- bÃ©nÃ©ficiaire identifiÃ© ;
- KYC en cours, validÃ© ou refusÃ© ;
- progression de collecte ;
- transparence et preuves, futures fonctionnalitÃ©s.

Ne jamais confondre vÃ©rification, note utilisateur, Trust Score et Transparency Score.

## 6. Architecture de navigation

### Public

- Logo
- DÃ©couvrir
- CatÃ©gories (lorsque le domaine existe)
- Comment Ã§a marche
- Ã€ propos
- Rechercher
- Se connecter
- **Lancer une collecte**

### AuthentifiÃ©

- Vue dâ€™ensemble
- Mes campagnes
- BÃ©nÃ©ficiaires
- Organisations
- VÃ©rification/KYC
- Dons et paiements (futur)
- DÃ©caissements (futur)
- ParamÃ¨tres

### Administration

- Tableau de bord
- Campagnes Ã  examiner
- KYC et bÃ©nÃ©ficiaires
- Utilisateurs et organisations
- Paiements, ledger, remboursements et payouts (futurs)
- Signalements et modÃ©ration (futurs)
- Audit et paramÃ¨tres

## 7. Ã‰crans prioritaires

### Phase Design 1 â€” acquisition et conversion

1. page dâ€™accueil desktop et mobile ;
2. composants du design system ;
3. liste/dÃ©couverte des campagnes ;
4. fiche campagne ;
5. parcours de don ;
6. confirmation ou traitement du paiement.

### Phase Design 2 â€” crÃ©ation et gestion

1. inscription/connexion ;
2. choix du propriÃ©taire et du bÃ©nÃ©ficiaire ;
3. assistant de crÃ©ation de campagne ;
4. tableau de bord promoteur ;
5. gestion du contenu et soumission Ã  examen ;
6. KYC et documents privÃ©s.

### Phase Design 3 â€” opÃ©rations

1. file de validation des campagnes ;
2. revue KYC ;
3. opÃ©rations financiÃ¨res ;
4. rapprochement et incidents ;
5. modÃ©ration, audit et rapports.

## 8. Homepage â€” structure initiale

1. en-tÃªte simple et rassurant ;
2. hero avec promesse claire, recherche et CTA Â« Lancer une collecte Â» ;
3. campagnes urgentes ou mises en avant ;
4. dÃ©couverte par causes, lorsque les catÃ©gories seront disponibles ;
5. fonctionnement en trois Ã©tapes ;
6. bloc confiance : validation, transparence, paiements adaptÃ©s au SÃ©nÃ©gal ;
7. histoires et impact, futur contenu Ã©ditorial ;
8. CTA final ;
9. pied de page institutionnel et lÃ©gal.

Le hero Ã©vite le carrousel automatique. Une photographie humaine authentique ou une composition Ã©ditoriale sobre est prÃ©fÃ©rable Ã  une illustration gÃ©nÃ©rique.

## 9. Fiche campagne â€” structure initiale

- Fil dâ€™Ariane discret.
- Visuel principal et galerie future.
- Titre, organisateur, bÃ©nÃ©ficiaire et Ã©tats de confiance.
- Montant collectÃ© en FCFA, objectif, progression, nombre de dons confirmÃ©.
- CTA Â« Faire un don Â» toujours accessible sur mobile.
- RÃ©cit de la campagne.
- ActualitÃ©s, preuves et commentaires lorsquâ€™ils existeront.
- Partage, signalement et informations de sÃ©curitÃ©.
- Carte de don sticky sur desktop ; barre dâ€™action basse sur mobile.

Les campagnes `UNLISTED` sont accessibles par lien direct mais absentes de la dÃ©couverte. Les campagnes `PRIVATE` ne sont jamais rendues publiquement.

## 10. Parcours de don â€” cible UX

Parcours prÃ©vu, Ã  ne pas confondre avec lâ€™Ã©tat actuel du code :

1. choix du montant ;
2. identitÃ© publique ou anonymat selon politique future ;
3. rÃ©capitulatif clair : don, frais Barkeelu, Ã©ventuels frais confirmÃ©s, total ;
4. choix du moyen de paiement, Wave prioritaire ;
5. redirection ou autorisation provider ;
6. Ã©cran Â« paiement en cours de vÃ©rification Â» ;
7. confirmation uniquement aprÃ¨s rÃ©ponse serveur fiable ;
8. reÃ§u, partage et suivi.

PrÃ©voir les Ã©tats : expirÃ©, annulÃ©, refusÃ©, doublon, retard webhook, montant incohÃ©rent et reprise sans double dÃ©bit.

## 11. Responsive et accessibilitÃ©

- PrioritÃ© aux rÃ©seaux mobiles et appareils modestes.
- Images responsives, compression et chargement diffÃ©rÃ©.
- Contraste WCAG AA au minimum.
- Navigation complÃ¨te au clavier et focus visible.
- Respect de `prefers-reduced-motion`.
- Textes alternatifs utiles ; aucune information essentielle dans une image.
- FranÃ§ais en langue initiale ; textes suffisamment souples pour de futures traductions.

## 12. RÃ¨gles Ã©ditoriales

- FranÃ§ais simple, phrases courtes, ton digne et rassurant.
- Ne pas dramatiser artificiellement les situations.
- Afficher les responsabilitÃ©s : organisateur, propriÃ©taire et bÃ©nÃ©ficiaire.
- Employer Â« Faire un don Â», Â« Lancer une collecte Â», Â« Montant collectÃ© Â» et Â« Objectif Â» de maniÃ¨re cohÃ©rente.
- Distinguer clairement brouillon, soumis, en examen, publiÃ©, suspendu, terminÃ© et fermÃ©.

## 13. Jetons CSS de dÃ©part

```css
:root {
  --color-brand-primary: #6257e2;
  --color-brand-primary-hover: #5146ce;
  --color-brand-primary-soft: #efedff;
  --color-brand-secondary: #fea500;
  --color-brand-secondary-hover: #e89200;
  --color-brand-secondary-soft: #fff4dc;
  --color-brand-symbol-green: #39c43a;
  --color-text: #17171c;
  --color-text-muted: #4e4d58;
  --color-border: #d8d6e0;
  --color-surface-muted: #f5f4f8;
  --color-surface: #ffffff;
  --font-family-brand: "Kodchasan", system-ui, sans-serif;
  --radius-control: 10px;
  --radius-card: 16px;
  --content-max-width: 1200px;
}
```

Ces jetons sont une base de travail. Leur contraste et leur rendu doivent Ãªtre vÃ©rifiÃ©s sur les maquettes avant stabilisation.

## 14. RÃ¨gles dâ€™intÃ©gration

- Google Stitch produit les explorations, Ã©crans et prototypes.
- Le prÃ©sent document reste la source de vÃ©ritÃ© des rÃ¨gles validÃ©es.
- Le code exportÃ© est une rÃ©fÃ©rence visuelle, pas une architecture Laravel Ã  accepter automatiquement.
- Lâ€™intÃ©gration cible Laravel SSR ; les interactions progressives doivent respecter lâ€™API `/api/v1` et les Policies serveur.
- Aucun Ã©cran ne doit inventer une route, une permission ou un statut comme fonctionnalitÃ© livrÃ©e.
- Les donnÃ©es financiÃ¨res et KYC sont toujours contrÃ´lÃ©es cÃ´tÃ© backend.

## 15. Points Ã  valider

- valeur exacte du vert du logo Ã  partir du fichier source officiel ;
- variantes SVG/monochromes et rÃ¨gles dÃ©taillÃ©es du logo ;
- style photographique et rÃ¨gles de consentement ;
- catÃ©gories de campagne et taxonomie ;
- wording lÃ©gal des frais et du paiement Wave ;
- politique dâ€™anonymat des donateurs ;
- langues futures, notamment wolof et anglais.

