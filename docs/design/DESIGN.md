# Barkeelu — Design System UI/UX

**Version :** 0.1  
**Statut :** proposition initiale à valider  
**Périmètre :** site public, parcours de don, espaces authentifiés et administration  
**Références :** GoFundMe (simplicité et conversion), Ulule (découverte et narration), sans reproduction de leur identité.

## 1. Principes produit

Barkeelu doit paraître simple, humain, fiable et local. L’interface privilégie :

1. la compréhension immédiate de la cause ;
2. la confiance avant l’action ;
3. un don réalisable rapidement sur mobile ;
4. la transparence sur le bénéficiaire, la collecte et les frais ;
5. la découverte éditoriale des campagnes et de leur impact.

Le design ne doit jamais suggérer qu’un paiement, un contrôle KYC ou un décaissement est confirmé avant validation par le serveur.

## 2. Identité visuelle

### 2.1 Logos

- **Logo horizontal :** en-tête desktop, pied de page, communications officielles.
- **Logo carré :** favicon, icône d’application, avatar, navigation mobile compacte.
- Conserver les proportions, les couleurs et une zone de protection égale à environ 25 % de la hauteur du symbole.
- Ne pas déformer, recolorer, incliner ou placer le logo sur un fond insuffisamment contrasté.
- Prévoir ultérieurement des variantes vectorielles SVG et monochromes validées.

### 2.2 Palette principale

| Jeton | Valeur | Usage principal |
|---|---:|---|
| `brand-primary` | `#6257E2` | CTA principal, liens actifs, focus, progression |
| `brand-primary-hover` | `#5146CE` | Survol du CTA principal |
| `brand-primary-soft` | `#EFEDFF` | Fonds légers et sélections |
| `brand-secondary` | `#FEA500` | Accent, mise en avant et actions secondaires |
| `brand-secondary-hover` | `#E89200` | Survol secondaire |
| `brand-secondary-soft` | `#FFF4DC` | Badges et fonds d’accent |
| `brand-symbol-green` | `#39C43A` | Couleur patrimoniale du symbole, usage limité |

Le violet demeure la couleur fonctionnelle dominante. L’orange sert d’accent. Le vert du logo ne devient pas une troisième couleur d’action concurrente.

### 2.3 Neutres et états

| Jeton | Valeur | Usage |
|---|---:|---|
| `neutral-950` | `#17171C` | Titres et texte fort |
| `neutral-700` | `#4E4D58` | Texte courant |
| `neutral-500` | `#777582` | Métadonnées |
| `neutral-300` | `#D8D6E0` | Bordures |
| `neutral-100` | `#F5F4F8` | Fonds secondaires |
| `neutral-0` | `#FFFFFF` | Surface principale |
| `success` | `#168A48` | Succès confirmé |
| `warning` | `#B86A00` | Attention |
| `danger` | `#C73535` | Erreur ou action destructive |
| `info` | `#2563B8` | Information neutre |

Les états ne reposent jamais uniquement sur la couleur : ajouter icône, libellé ou message.

## 3. Typographie

Police principale : **Kodchasan**, Google Fonts.

- Poids courant recommandé : `500` (Medium), identité officielle.
- Prévoir `600` pour les titres et CTA si le fichier de police est chargé.
- Fallback : `Kodchasan, system-ui, sans-serif`.
- Corps mobile : 16 px minimum ; petits libellés : 13–14 px minimum.
- Interligne : 1,45 à 1,6 pour le contenu narratif.
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

- Conception mobile-first à partir de 320 px.
- Conteneur desktop : largeur maximale 1200 px, gouttières 24 px.
- Grille desktop : 12 colonnes ; tablette : 8 ; mobile : 4.
- Échelle d’espacement : 4, 8, 12, 16, 24, 32, 48, 64, 96 px.
- Rayon : 10 px pour champs/boutons, 16 px pour cartes, 24 px pour blocs éditoriaux.
- Ombres discrètes ; privilégier bordure et espace blanc.
- Cibles tactiles : 44 × 44 px minimum.

## 5. Composants fondamentaux

### 5.1 Boutons

- **Primaire :** fond violet, texte blanc ; action principale unique par zone.
- **Secondaire :** fond blanc, bordure violette, texte violet.
- **Accent :** orange, réservé à une mise en avant non concurrente avec « Faire un don ».
- **Destructif :** rouge, avec confirmation explicite.
- États obligatoires : défaut, hover, focus visible, pressé, chargement, désactivé.

### 5.2 Formulaires

- Libellé persistant au-dessus du champ ; placeholder uniquement comme exemple.
- Erreur placée sous le champ et résumée en haut pour les longs formulaires.
- Sauvegarde de brouillon pour la création de campagne.
- Montants saisis et affichés en FCFA, mais transmis selon le contrat API en entier XOF.
- Aucun secret ou document KYC ne doit apparaître dans une URL, une notification ou un aperçu public.

### 5.3 Carte campagne

Contenu minimal : image, catégorie future, titre, organisateur, localisation éventuelle, montant collecté, objectif, barre de progression et état de vérification explicite.

La carte entière est cliquable, mais les liens internes restent accessibles au clavier. Éviter les compteurs ou badges non encore calculés par le backend.

### 5.4 Confiance

Composants distincts :

- identité/organisation vérifiée ;
- bénéficiaire identifié ;
- KYC en cours, validé ou refusé ;
- progression de collecte ;
- transparence et preuves, futures fonctionnalités.

Ne jamais confondre vérification, note utilisateur, Trust Score et Transparency Score.

## 6. Architecture de navigation

### Public

- Logo
- Découvrir
- Catégories (lorsque le domaine existe)
- Comment ça marche
- À propos
- Rechercher
- Se connecter
- **Lancer une collecte**

### Authentifié

- Vue d’ensemble
- Mes campagnes
- Bénéficiaires
- Organisations
- Vérification/KYC
- Dons et paiements (futur)
- Décaissements (futur)
- Paramètres

### Administration

- Tableau de bord
- Campagnes à examiner
- KYC et bénéficiaires
- Utilisateurs et organisations
- Paiements, ledger, remboursements et payouts (futurs)
- Signalements et modération (futurs)
- Audit et paramètres

## 7. Écrans prioritaires

### Phase Design 1 — acquisition et conversion

1. page d’accueil desktop et mobile ;
2. composants du design system ;
3. liste/découverte des campagnes ;
4. fiche campagne ;
5. parcours de don ;
6. confirmation ou traitement du paiement.

### Phase Design 2 — création et gestion

1. inscription/connexion ;
2. choix du propriétaire et du bénéficiaire ;
3. assistant de création de campagne ;
4. tableau de bord promoteur ;
5. gestion du contenu et soumission à examen ;
6. KYC et documents privés.

### Phase Design 3 — opérations

1. file de validation des campagnes ;
2. revue KYC ;
3. opérations financières ;
4. rapprochement et incidents ;
5. modération, audit et rapports.

## 8. Homepage — structure initiale

1. en-tête simple et rassurant ;
2. hero avec promesse claire, recherche et CTA « Lancer une collecte » ;
3. campagnes urgentes ou mises en avant ;
4. découverte par causes, lorsque les catégories seront disponibles ;
5. fonctionnement en trois étapes ;
6. bloc confiance : validation, transparence, paiements adaptés au Sénégal ;
7. histoires et impact, futur contenu éditorial ;
8. CTA final ;
9. pied de page institutionnel et légal.

Le hero évite le carrousel automatique. Une photographie humaine authentique ou une composition éditoriale sobre est préférable à une illustration générique.

## 9. Fiche campagne — structure initiale

- Fil d’Ariane discret.
- Visuel principal et galerie future.
- Titre, organisateur, bénéficiaire et états de confiance.
- Montant collecté en FCFA, objectif, progression, nombre de dons confirmé.
- CTA « Faire un don » toujours accessible sur mobile.
- Récit de la campagne.
- Actualités, preuves et commentaires lorsqu’ils existeront.
- Partage, signalement et informations de sécurité.
- Carte de don sticky sur desktop ; barre d’action basse sur mobile.

Les campagnes `UNLISTED` sont accessibles par lien direct mais absentes de la découverte. Les campagnes `PRIVATE` ne sont jamais rendues publiquement.

## 10. Parcours de don — cible UX

Parcours prévu, à ne pas confondre avec l’état actuel du code :

1. choix du montant ;
2. identité publique ou anonymat selon politique future ;
3. récapitulatif clair : don, frais Barkeelu, éventuels frais confirmés, total ;
4. choix du moyen de paiement, Wave prioritaire ;
5. redirection ou autorisation provider ;
6. écran « paiement en cours de vérification » ;
7. confirmation uniquement après réponse serveur fiable ;
8. reçu, partage et suivi.

Prévoir les états : expiré, annulé, refusé, doublon, retard webhook, montant incohérent et reprise sans double débit.

## 11. Responsive et accessibilité

- Priorité aux réseaux mobiles et appareils modestes.
- Images responsives, compression et chargement différé.
- Contraste WCAG AA au minimum.
- Navigation complète au clavier et focus visible.
- Respect de `prefers-reduced-motion`.
- Textes alternatifs utiles ; aucune information essentielle dans une image.
- Français en langue initiale ; textes suffisamment souples pour de futures traductions.

## 12. Règles éditoriales

- Français simple, phrases courtes, ton digne et rassurant.
- Ne pas dramatiser artificiellement les situations.
- Afficher les responsabilités : organisateur, propriétaire et bénéficiaire.
- Employer « Faire un don », « Lancer une collecte », « Montant collecté » et « Objectif » de manière cohérente.
- Distinguer clairement brouillon, soumis, en examen, publié, suspendu, terminé et fermé.

## 13. Jetons CSS de départ

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

Ces jetons sont une base de travail. Leur contraste et leur rendu doivent être vérifiés sur les maquettes avant stabilisation.

## 14. Règles d’intégration

- Google Stitch produit les explorations, écrans et prototypes.
- Le présent document reste la source de vérité des règles validées.
- Le code exporté est une référence visuelle, pas une architecture Laravel à accepter automatiquement.
- L’intégration cible Laravel SSR ; les interactions progressives doivent respecter l’API `/api/v1` et les Policies serveur.
- Aucun écran ne doit inventer une route, une permission ou un statut comme fonctionnalité livrée.
- Les données financières et KYC sont toujours contrôlées côté backend.

## 15. Points à valider

- valeur exacte du vert du logo à partir du fichier source officiel ;
- variantes SVG/monochromes et règles détaillées du logo ;
- style photographique et règles de consentement ;
- catégories de campagne et taxonomie ;
- wording légal des frais et du paiement Wave ;
- politique d’anonymat des donateurs ;
- langues futures, notamment wolof et anglais.
