# Barkeelu — Plan d’intégration UI Laravel SSR V1

**Version :** 0.1  
**Statut :** spécification proposée  
**Références :** `DESIGN.md`, maquettes Stitch validées, ADR-011  
**Périmètre :** fondations UI et homepage publique uniquement

## 1. État réel du dépôt

Le frontend Laravel contient actuellement :

- une route web `/` retournant `welcome.blade.php` ;
- Tailwind CSS 4 via Vite ;
- `resources/css/app.css` et `resources/js/app.js` minimaux ;
- aucune dépendance Livewire ou Alpine ;
- aucune authentification web finalisée ;
- aucune interface Donation/Payment/Wave implémentée.

Le cœur Campaign et ses routes API publiques existent. Les domaines Donation, Payment, Category et les fonctions financières restent hors périmètre livré.

## 2. Décisions d’intégration

1. Utiliser une seule vue responsive pour desktop et mobile.
2. Intégrer la homepage en Blade SSR afin de préserver SEO, partage social et performance initiale.
3. Utiliser Tailwind CSS 4 déjà installé, sans ajouter de dépendance.
4. Employer des composants Blade pour les éléments réutilisables.
5. Utiliser des SVG inline pour les icônes essentielles.
6. Ne pas copier le CDN Tailwind, les scripts inline ni les URLs d’images Google de Stitch.
7. Ne pas créer de faux parcours de paiement ou d’authentification.
8. Ne pas modifier le domaine Campaign pour répondre à une exigence purement visuelle.

## 3. Arborescence cible

```text
apps/api/resources/
├── css/
│   └── app.css
├── js/
│   └── app.js
└── views/
    ├── components/
    │   ├── brand/
    │   │   └── logo.blade.php
    │   ├── campaign/
    │   │   ├── card.blade.php
    │   │   ├── progress.blade.php
    │   │   └── verification-badge.blade.php
    │   ├── icon.blade.php
    │   └── ui/
    │       ├── button.blade.php
    │       └── notice.blade.php
    ├── layouts/
    │   └── public.blade.php
    ├── partials/
    │   ├── public-header.blade.php
    │   ├── public-footer.blade.php
    │   └── mobile-navigation.blade.php
    └── pages/
        └── home.blade.php

apps/api/public/images/
├── brand/
│   ├── barkeelu-horizontal.png
│   └── barkeelu-square.png
└── placeholders/
    └── campaign-default.svg
```

Les photographies Stitch ne doivent pas être reprises tant que leur licence, leur consentement et leur origine ne sont pas validés.

## 4. Tokens Tailwind CSS 4

Les tokens sont déclarés dans `resources/css/app.css` via `@theme` :

```css
@import url('https://fonts.googleapis.com/css2?family=Kodchasan:wght@500;600&display=swap');
@import 'tailwindcss';

@theme {
    --font-sans: 'Kodchasan', system-ui, sans-serif;
    --color-brand-primary: #6257e2;
    --color-brand-primary-hover: #5146ce;
    --color-brand-primary-soft: #efedff;
    --color-brand-secondary: #fea500;
    --color-brand-secondary-hover: #e89200;
    --color-brand-secondary-soft: #fff4dc;
    --color-ink: #17171c;
    --color-muted: #4e4d58;
    --color-border: #d8d6e0;
    --color-surface-muted: #f5f4f8;
}
```

Une version locale de Kodchasan pourra remplacer Google Fonts avant production afin de réduire la dépendance externe et mieux contrôler performance et confidentialité.

## 5. Layout public

`layouts/public.blade.php` porte :

- `lang="fr"` ;
- titre et description configurables ;
- canonical et métadonnées Open Graph minimales ;
- favicon carré ;
- ressources Vite uniquement ;
- lien d’évitement vers le contenu ;
- header, contenu principal et footer ;
- aucune dépendance CDN d’exécution.

La bannière « Maquette — contenus et données fictifs » appartient uniquement aux environnements de démonstration. Elle n’est pas affichée sur une homepage alimentée par de vraies campagnes publiées.

## 6. Données de la homepage

### 6.1 Phase initiale

La homepage peut afficher :

- un hero statique ;
- le fonctionnement de Barkeelu ;
- les principes de contrôle et de conformité ;
- des emplacements de campagnes clairement identifiés comme démonstration si la base est vide.

### 6.2 Campagnes réelles

La future action web doit réutiliser les règles de visibilité du domaine :

- statut `PUBLISHED` ;
- visibilité `PUBLIC` ;
- tri explicite ;
- pagination ou limite stricte ;
- aucune campagne `UNLISTED`, `PRIVATE` ou `TARGETED` sur la homepage.

Les montants exposés proviennent uniquement des projections serveur autoritatives :

- `net_collected_nominal` ;
- `goal_amount` ;
- `donation_count` lorsque réellement alimenté.

Le pourcentage est calculé côté serveur ou composant à partir d’entiers, plafonné visuellement sans modifier la valeur métier. Aucun nombre fictif n’est mélangé aux données réelles.

## 7. Contrats des composants

### `campaign.card`

Entrées minimales :

- titre ;
- slug ;
- image ou placeholder ;
- owner affichable ;
- beneficiary affichable si autorisé ;
- montant collecté ;
- objectif ;
- nombre de dons ;
- état de vérification public autorisé.

Le composant ne déduit jamais qu’une campagne est vérifiée à partir de son seul statut `PUBLISHED`.

### `campaign.progress`

- reçoit uniquement des entiers ;
- gère objectif positif ;
- affiche le montant en FCFA ;
- produit un libellé accessible indépendant de la couleur ;
- gère un objectif dépassé sans casser la largeur.

### `campaign.verification-badge`

Valeurs UI possibles uniquement si elles correspondent à une donnée publique réelle :

- identité vérifiée ;
- examen en cours ;
- informations à compléter ;
- non vérifié.

Le mapping métier exact doit être validé avant branchement au KYC. Aucun détail KYC privé n’est exposé.

### `ui.button`

Variantes : primary, secondary, accent, destructive. Le composant gère lien ou bouton, état désactivé et focus visible.

## 8. Comportement des CTA

| CTA | Comportement avant implémentation | Comportement futur |
|---|---|---|
| Lancer une collecte | connexion ou page informative réelle uniquement | assistant de création |
| Découvrir | ancre vers les campagnes publiques | index filtrable |
| Soutenir ce projet | fiche campagne, jamais paiement direct fictif | fiche puis parcours Donation |
| Se connecter | masqué ou route existante uniquement | authentification web |

Aucun bouton actif ne pointe vers `#` en version intégrée. Une fonctionnalité absente est masquée ou présentée explicitement comme à venir.

## 9. Responsive

- Un seul markup principal partagé entre desktop et mobile.
- Header desktop à partir de `lg`.
- Header mobile compact sous `lg`.
- Cartes : une colonne mobile, deux tablette, trois desktop.
- Navigation basse mobile seulement lorsqu’elle mène à des routes existantes.
- Aucun carrousel obligatoire ; la liste verticale reste le fallback accessible.
- Aucun texte tronqué sans alternative accessible.

## 10. Accessibilité

- WCAG AA minimum.
- Focus visible sur chaque élément interactif.
- Cible tactile minimale 44 × 44 px.
- SVG décoratif avec `aria-hidden="true"`.
- Bouton icône seul avec `aria-label`.
- Barre de progression avec rôle et valeurs accessibles.
- Images avec texte alternatif contextuel ; `alt=""` pour une image purement décorative.
- Respect de `prefers-reduced-motion`.
- Navigation et formulaires entièrement utilisables au clavier.

## 11. Performance et sécurité

- Aucun script Tailwind CDN en production.
- Aucun script inline copié depuis Stitch.
- Images locales ou stockage média approuvé, formats responsifs et dimensions réservées.
- Chargement différé hors premier écran.
- CSP compatible avec les ressources retenues.
- Aucun secret, identifiant KYC ou donnée financière privée dans Blade ou JavaScript.
- Le navigateur n’est jamais la source de vérité d’un paiement.

## 12. SEO

- HTML SSR indexable.
- Un seul `h1` par page.
- Titre et description propres à la page.
- Liens crawlables vers les campagnes `PUBLIC` et `PUBLISHED`.
- Données structurées ajoutées seulement après validation du modèle et des informations réelles.
- Pages `UNLISTED` avec stratégie d’indexation explicite ; pages `PRIVATE` jamais publiques.

## 13. Tests attendus

### Tests Feature

- `/` répond 200 ;
- seules les campagnes `PUBLIC` et `PUBLISHED` sont affichées ;
- `PRIVATE` et `UNLISTED` n’apparaissent pas ;
- la homepage fonctionne avec zéro campagne ;
- les montants sont rendus en FCFA ;
- les CTA n’exposent pas de route inexistante.

### Vérifications frontend

- `npm run build` ;
- absence de CDN Tailwind dans les vues ;
- absence d’URL d’image Google Stitch ;
- contrôles responsive à 320, 390, 768, 1024 et 1440 px ;
- navigation clavier et focus ;
- vérification Lighthouse indicative après intégration.

## 14. Séquençage recommandé

### UI-01 — Fondations

- importer les logos officiels ;
- remplacer Instrument Sans par Kodchasan ;
- définir les tokens ;
- créer layout, header et footer ;
- ajouter les composants bouton et icône ;
- tester le build.

### UI-02 — Homepage statique fidèle

- intégrer hero et sections institutionnelles ;
- créer carte, progression et badges avec données de démonstration isolées ;
- vérifier desktop/mobile et accessibilité.

### UI-03 — Branchement Campaign

- ajouter l’action web dédiée ;
- charger uniquement les campagnes publiques publiées ;
- mapper les projections autoritatives ;
- ajouter les tests de visibilité et de rendu.

### UI-04 — Fiche campagne

- spécifier et concevoir la page avant implémentation ;
- brancher la route publique par slug existante ou une projection SSR cohérente ;
- ne pas commencer le paiement avant le domaine Donation/Payment.

## 15. Hors périmètre

- intégration Donation/Payment/Wave ;
- authentification web complète ;
- moteur Category ;
- témoignages ou statistiques réels ;
- dashboard promoteur et administration ;
- Livewire, Alpine, Vue ou React ;
- reprise directe du JavaScript Stitch.

Toute dépendance supplémentaire fera l’objet d’une décision séparée et d’une autorisation explicite.
