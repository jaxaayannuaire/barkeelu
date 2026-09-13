# Prompt Codex — UI-01 Fondations Laravel SSR

## Prompt à transmettre à Codex

```text
Tu interviens comme développeur Laravel frontend expérimenté sur Barkeelu.com.

MISSION
Implémenter UI-01 — Fondations du frontend public Laravel SSR.

Cette mission est volontairement limitée aux fondations visuelles et structurelles. Ne pas intégrer toute la homepage Stitch, ne pas créer de parcours de don, ne pas modifier les domaines métier et ne pas ajouter de dépendance.

RÈGLES OBLIGATOIRES
1. Lire intégralement avant toute modification :
   - AGENTS.md ;
   - docs/design/DESIGN.md ;
   - docs/design/INTEGRATION_LARAVEL_UI_V1.md ;
   - docs/decisions/ADR-011-campaigns.md ;
   - README.md ;
   - ROADMAP.md.
2. Inspecter l’état réel du dépôt, les vues, routes, tests, package.json, Vite et app.css.
3. Respecter la branche active et préserver toute modification utilisateur.
4. Aucun package Composer/npm supplémentaire.
5. Aucun changement de migration, modèle, API, Policy, KYC, Campaign, Payment, Ledger, Wave ou infrastructure.
6. Aucun commit ni push sans autorisation explicite.
7. Tous les commentaires, rapports et descriptions en français ; identifiants techniques en anglais.

ÉTAT DE RÉFÉRENCE
- Laravel 13 / PHP 8.3.
- Tailwind CSS 4 et Vite sont déjà installés.
- La route web `/` retourne actuellement `welcome.blade.php`.
- Instrument Sans est encore présent dans le squelette.
- Aucune dépendance Livewire ou Alpine n’est installée.
- Les fonctionnalités financières ne sont pas implémentées.

PÉRIMÈTRE AUTORISÉ

A. ASSETS DE MARQUE
- Créer `apps/api/public/images/brand/` si nécessaire.
- Ajouter les deux logos officiels fournis :
  - `barkeelu-horizontal.png` ;
  - `barkeelu-square.png`.
- Ne pas modifier, recadrer ou recolorer les logos.
- Ne pas conserver d’URL Google Stitch dans le code.

B. TOKENS CSS
- Mettre à jour `apps/api/resources/css/app.css` pour utiliser Tailwind CSS 4.
- Remplacer Instrument Sans par Kodchasan.
- Charger Kodchasan 500 et 600 depuis Google Fonts pour cette première étape.
- Définir au minimum les tokens validés :
  - primaire `#6257E2` ;
  - primaire hover `#5146CE` ;
  - primaire soft `#EFEDFF` ;
  - secondaire `#FEA500` ;
  - secondaire hover `#E89200` ;
  - secondaire soft `#FFF4DC` ;
  - texte fort `#17171C` ;
  - texte courant `#4E4D58` ;
  - bordure `#D8D6E0` ;
  - surface secondaire `#F5F4F8`.
- Ne jamais utiliser `#493BC8`.
- Ne pas utiliser le vert du logo comme couleur fonctionnelle.
- Ne pas utiliser de règle globale `font-family: ... !important`.

C. LAYOUT PUBLIC
- Créer `resources/views/layouts/public.blade.php`.
- Inclure : langue française, viewport, titre paramétrable, description paramétrable, favicon carré, ressources Vite, lien d’évitement, header, contenu principal et footer.
- Prévoir des emplacements Blade simples pour titre et métadonnées sans introduire de package SEO.
- Aucun CDN Tailwind ni script inline Stitch.

D. PARTIALS
- Créer :
  - `resources/views/partials/public-header.blade.php` ;
  - `resources/views/partials/public-footer.blade.php`.
- Header desktop : logo horizontal, navigation minimale vers des routes ou ancres réellement présentes, CTA uniquement s’il ne pointe pas vers une fonctionnalité inexistante.
- Header mobile : logo carré, structure compacte, bouton menu accessible seulement si son comportement est réel ; sinon ne pas afficher un bouton inerte.
- Ne pas afficher d’avatar lorsque l’utilisateur n’est pas authentifié.
- Footer minimal : À propos, Comment ça marche, Sécurité et vérification, Conditions générales, Confidentialité, Mentions légales, Aide en ligne.
- Ne pas inventer de téléphone, certification, garantie, partenaire ou moyen de paiement disponible.
- Afficher `© 2026 Barkeelu.com` ou une année générée côté serveur si elle produit 2026 actuellement.

E. COMPOSANTS BLADE DE BASE
- Créer :
  - `resources/views/components/brand/logo.blade.php` ;
  - `resources/views/components/ui/button.blade.php` ;
  - `resources/views/components/icon.blade.php` uniquement si une API d’icônes simple et sûre est justifiée.
- Le logo accepte au minimum les variantes `horizontal` et `square` avec dimensions et texte alternatif appropriés.
- Le bouton accepte au minimum les variantes `primary`, `secondary` et `accent`, ainsi que lien/bouton, état disabled et classes de focus visibles.
- Pour les icônes essentielles, utiliser des SVG inline locaux. Aucun Material Symbols dépendant d’une police externe.
- Refuser ou gérer proprement toute variante inconnue.

F. PAGE DE VALIDATION MINIMALE
- Remplacer le squelette Laravel par une page publique minimale utilisant le nouveau layout.
- Cette page sert uniquement à vérifier header, typographie, boutons, surfaces et footer.
- Ne pas reproduire encore toute la homepage Stitch.
- Ne pas afficher de campagne, statist de don, compteur ou donnée fictive comme réelle.
- Modifier la route `/` seulement dans la mesure nécessaire pour rendre cette vue.

G. ACCESSIBILITÉ
- Lien « Aller au contenu ».
- Focus visible.
- Cibles tactiles d’au moins 44 × 44 px.
- Logo avec alt approprié.
- SVG décoratifs avec `aria-hidden="true"`.
- Boutons icône seuls avec `aria-label`.
- Structure landmarks : header, nav, main, footer.
- Contraste WCAG AA.

H. TESTS
- Ajouter ou adapter des tests Feature ciblés pour vérifier au minimum :
  - GET `/` retourne 200 ;
  - la page contient Barkeelu ;
  - les assets Vite sont appelés par le layout dans l’environnement de test approprié ;
  - aucune promesse interdite n’est rendue ;
  - aucune URL Google Stitch ou CDN Tailwind n’est présente dans la réponse.
- Ne pas écrire de test fragile basé sur tout le HTML ou sur des classes décoratives nombreuses.

VÉRIFICATIONS OBLIGATOIRES
1. Lancer les tests ciblés.
2. Lancer la suite complète Laravel.
3. Lancer `npm run build`.
4. Lancer les outils de formatage déjà présents si nécessaire.
5. Exécuter une recherche confirmant l’absence dans les fichiers modifiés de :
   - `Plus Jakarta` ;
   - `Instrument Sans` ;
   - `#493BC8` ;
   - `cdn.tailwindcss.com` ;
   - `lh3.googleusercontent.com` ;
   - `garantie de versement` ;
   - `100% sécurisé` ;
   - `paiement instantané`.
6. Examiner `git diff --check` et le diff complet.

CRITÈRES D’ACCEPTATION
- La page `/` utilise le nouveau layout public.
- Kodchasan 500/600 est la police effective sans `!important`.
- Les couleurs principales sont strictement conformes à DESIGN.md.
- Les logos sont locaux, nets et non déformés.
- Header et footer sont responsive et accessibles.
- Aucun bouton inerte ou lien trompeur.
- Aucun code Stitch brut, CDN Tailwind ou URL Google Stitch.
- Aucun domaine métier ou financier modifié.
- Tests et build passent.

RAPPORT FINAL ATTENDU
- résumé de l’implémentation ;
- fichiers créés/modifiés ;
- décisions prises et éventuels écarts justifiés ;
- commandes exécutées et résultats exacts ;
- résultat des recherches de termes interdits ;
- état `git status --short` ;
- GO/NO-GO UI-01 ;
- proposition d’un message Conventional Commit en français, sans effectuer le commit.
```

## Message de commit proposé après validation

```text
feat(ui): ajouter les fondations du frontend public
```
