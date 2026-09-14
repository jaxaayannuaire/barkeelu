# Prompt Codex — UI-04 Fiche campagne publique media-first

## Prompt à transmettre à Codex

```text
Tu interviens comme développeur Laravel expérimenté sur Barkeelu.com.

MISSION
Implémenter UI-04 — une fiche campagne publique SSR, responsive et media-first, accessible depuis la homepage.

La page doit reprendre la hiérarchie des maquettes Stitch V3 validées : titre, média principal, galerie, chiffres, actions, navigation de contenu, récit et zone organisateur/bénéficiaire. Elle doit cependant refléter strictement les capacités réellement présentes dans le dépôt.

PRINCIPE DE VÉRITÉ
Le dépôt ne possède pas encore de modèle CampaignMedia, CampaignUpdate, CampaignComment ou CampaignFollower, ni de parcours web de don finalisé. Ne pas inventer ces données et ne pas présenter ces fonctions comme opérationnelles.

LECTURES OBLIGATOIRES
Lire intégralement avant toute modification :
- AGENTS.md ;
- docs/design/DESIGN.md ;
- docs/design/INTEGRATION_LARAVEL_UI_V1.md ;
- docs/decisions/ADR-001-campaign-owner-beneficiary-representative.md ;
- docs/decisions/ADR-007-barkeelu-live-data-only.md ;
- docs/decisions/ADR-008-stockage-prive-kyc.md ;
- docs/decisions/ADR-011-campaigns.md ;
- README.md, ROADMAP.md et CHANGELOG.md.

Inspecter l'état réel de :
- Campaign, CampaignPolicy et CampaignResource ;
- Beneficiary, Organization et User ;
- Donation et Payment, sans les modifier ;
- HomeController et CampaignController API ;
- routes/web.php et routes/api.php ;
- layouts/public.blade.php ;
- composants et tests UI-01 à UI-03 ;
- migrations Campaign, Beneficiary, Organization, Donation et Payment.

Utiliser les captures Stitch V3 comme référence visuelle seulement. Ne jamais copier leur HTML, leur CDN Tailwind, leurs scripts inline, leurs URL Google ou leur DESIGN.md contradictoire.

PRÉCAUTIONS GIT
- Le working tree peut contenir des modifications concurrentes Finance, Refund, Payout ou documentation.
- Les préserver intégralement.
- Ne pas formater, stager, committer ou pousser hors du périmètre UI-04.
- Aucun commit ni push sans autorisation.

1. ROUTE ET VISIBILITÉ

Créer une route publique nommée :

`GET /collectes/{slug}` → `campaigns.show`

Créer un contrôleur web dédié, par exemple :

`App\Http\Controllers\Web\CampaignShowController`

Règles publiques strictes selon ADR-011 :
- `PUBLISHED + PUBLIC` : page accessible et indexable ;
- `PUBLISHED + UNLISTED` : page accessible par URL directe mais non indexable ;
- `PRIVATE` et `TARGETED` : réponse 404 pour un visiteur public ;
- tout statut autre que `PUBLISHED` : réponse 404 pour un visiteur public.

Ne pas détourner `publiclyListed()`, qui doit rester réservé à `PUBLIC + PUBLISHED` pour les listes.

Créer si utile un second scope clairement nommé, par exemple `publiclyViewable()`, limité à `PUBLISHED + (PUBLIC ou UNLISTED)`. Utiliser les enums existants. Éviter de dupliquer les règles entre contrôleur, policy et API sans justification.

La recherche se fait par slug stable. Une campagne soft-deleted reste introuvable.

2. PROJECTION PUBLIQUE MINIMALE

Sélectionner uniquement les colonnes nécessaires :
- public_id ;
- title ;
- slug ;
- description ;
- goal_amount ;
- currency ;
- net_collected_nominal ;
- donation_count ;
- distinct_donor_count ;
- fundraising_status ;
- visibility ;
- published_at, start_at et end_at si utilisés ;
- clés owner/beneficiary uniquement si nécessaires à un libellé générique.

Ne pas exposer dans UI-04 :
- email, téléphone ou identité privée d'un User ;
- nom personnel de l'owner individuel ;
- nom du bénéficiaire tant qu'aucun consentement/champ de publication explicite n'existe ;
- KYC, documents, représentants ou stockage privé ;
- metadata brute ;
- état ou montant de payout ;
- noms, emails ou historique des donateurs ;
- payloads provider, webhook ou paiement.

Afficher provisoirement des libellés factuels génériques :
- owner organization : « Collecte portée par une organisation » ;
- owner user : « Collecte individuelle » ;
- bénéficiaire : « Bénéficiaire de la collecte ».

Ne créer aucune prétendue vérification publique.

3. LIEN DEPUIS LA HOMEPAGE

Adapter la carte campagne réelle pour qu'elle mène vers `route('campaigns.show', ['slug' => ...])`.

Contraintes :
- lien englobant ou titre lié, accessible au clavier ;
- intitulé accessible explicite ;
- aucune carte de démonstration ne mène vers une campagne inexistante ;
- aucun `href="#"` ;
- préserver le rendu UI-02/UI-03.

4. COMPOSITION RESPONSIVE

Créer une seule vue Blade responsive, par exemple :

`resources/views/pages/campaigns/show.blade.php`

Ordre mobile obligatoire :
1. retour vers les collectes ;
2. titre principal, 30–32 px, graisse 600 ;
3. état de collecte et contexte générique ;
4. média principal ;
5. galerie ;
6. carte montant/objectif/statistiques ;
7. actions principales ;
8. navigation horizontale des sections ;
9. contenu ;
10. organisateur et bénéficiaire ;
11. footer ;
12. barre d'action sticky mobile.

Desktop :
- titre au-dessus de la grille ;
- colonne principale d'environ 65 % ;
- carte contribution/statistiques sticky à droite, environ 35 % ;
- vidéo avant galerie ;
- navigation et récit sous les médias ;
- aucun chevauchement avec le footer.

5. MÉDIA ET GALERIE

Aucun modèle média Campaign n'existe. UI-04 ne crée aucune migration média.

Créer des composants réutilisables préparant la future intégration, par exemple :
- `campaign.media-player` ;
- `campaign.gallery`.

Dans UI-04 réel :
- afficher un poster/placeholder local 16:9 non trompeur ;
- afficher « Vidéo de présentation non disponible » si aucune donnée vidéo réelle n'existe ;
- aucun bouton lecture actif sans source réelle ;
- aucune iframe YouTube, Facebook ou TikTok ;
- aucun autoplay ;
- aucune URL externe Stitch/Google ;
- galerie vide ou placeholder local clairement présenté ;
- ne pas afficher de faux compteur de médias.

Prévoir dans le contrat du composant les futurs formats 16:9 et 9:16, sans implémenter de stockage ni parser arbitrairement des URL.

6. CHIFFRES ET PROGRESSION

Réutiliser `campaign.progress` lorsque possible.

Afficher uniquement les projections serveur :
- `net_collected_nominal` ;
- `goal_amount` ;
- `donation_count` ;
- `distinct_donor_count`.

Règles :
- tous les montants restent des entiers ;
- devise XOF affichée FCFA ;
- chiffres tabulaires ;
- division protégée ;
- valeur réelle du pourcentage conservée ;
- largeur visuelle plafonnée à 100 % ;
- « Objectif atteint » est un état dérivé, jamais un statut stocké ;
- aucun délai ou chiffre fictif.

7. ACTIONS

Créer trois actions visuellement lisibles :
- « Je soutiens » ;
- « Partager » ;
- « Suivre ».

Comportement actuel :
- `Je soutiens` : désactivé avec indication accessible « Parcours de don web à venir », car aucun flux public web final n'est livré ;
- `Partager` : fonctionnel avec Web Share API si disponible, sinon copie de l'URL canonique, avec retour accessible ;
- `Suivre` : désactivé avec indication « Fonctionnalité à venir ».

Ne jamais appeler directement l'API Donation depuis Blade/JavaScript dans UI-04. Ne jamais simuler un paiement.

Respecter `fundraising_status` :
- OPEN : libellé normal, mais CTA toujours désactivé tant que le parcours web est absent ;
- NOT_STARTED : « Collecte pas encore ouverte » ;
- PAUSED : « Collecte temporairement suspendue » ;
- CLOSED : « Collecte terminée ».

8. NAVIGATION DE CONTENU

Présenter les sections suivantes dans une barre horizontalement scrollable :
- À propos ;
- Dons ;
- Commentaires ;
- Mises à jour ;
- Organisateur et bénéficiaire.

Comme tous les modules ne sont pas disponibles :
- « À propos » mène au récit réel ;
- « Organisateur et bénéficiaire » mène aux libellés publics génériques ;
- Dons, Commentaires et Mises à jour portent clairement « À venir » et ne sont pas de faux onglets interactifs ;
- ne pas interroger les donations pour afficher des personnes ;
- ne créer aucun faux commentaire, don ou compte rendu.

Préférer une navigation par ancres réelles vers des sections SSR plutôt qu'un faux système d'onglets JavaScript. Le header sticky ne doit pas masquer la cible d'une ancre.

La description Campaign est échappée. Ne jamais utiliser `{!! !!}`. Préserver les paragraphes de manière sûre, sans autoriser du HTML arbitraire.

9. BARRE STICKY MOBILE

Créer une seule barre sticky/fixed dédiée à la campagne sous `lg` :
- mini-rappel du montant, objectif et progression ;
- « Je soutiens » ;
- « Partager » ;
- « Suivre » ;
- libellés visibles, pas uniquement des icônes ;
- cibles tactiles de 44 px minimum ;
- `env(safe-area-inset-bottom)` ;
- padding bas suffisant dans la page ;
- aucun chevauchement avec le footer.

La navigation mobile générale ne doit pas être ajoutée sur cette page.

Si la barre apparaît uniquement après la sortie du bloc principal, utiliser `IntersectionObserver` dans `resources/js/app.js`, avec fallback sans JavaScript. Elle doit se masquer ou cesser avant le footer. Respecter `prefers-reduced-motion`.

10. SEO ET PARTAGE

Étendre le layout proprement, sans casser les pages existantes, pour accepter :
- title ;
- meta description ;
- canonical ;
- Open Graph minimal ;
- directive robots.

Règles :
- PUBLIC : `index,follow` ;
- UNLISTED : `noindex,nofollow` ;
- titre et description issus de la campagne mais échappés ;
- image Open Graph locale tant qu'aucun média approuvé n'existe ;
- une seule balise H1.

11. COMPOSANTS ATTENDUS

Créer ou adapter uniquement les composants réellement utiles :
- campaign.detail-progress ou réutilisation de campaign.progress ;
- campaign.media-player ;
- campaign.gallery ;
- campaign.action-bar ;
- campaign.public-parties ;
- ui.button si une variante disabled/accessibilité manque.

Éviter les composants minuscules sans réutilisation. Tous les props doivent être typés/validés lorsque cela protège les montants ou états.

12. ACCESSIBILITÉ

- WCAG AA minimum ;
- focus visible ;
- navigation clavier complète ;
- contrôles désactivés réellement non activables ;
- message expliquant chaque action indisponible ;
- progression avec rôle, valeur et libellé accessibles ;
- aucun sens transmis uniquement par couleur ;
- titres hiérarchiques cohérents ;
- images décoratives avec `alt=""` ;
- annonces du résultat de partage via zone `aria-live` ;
- aucune interdiction de zoom mobile.

13. PERFORMANCE ET SÉCURITÉ

- Blade SSR ;
- aucun ajout de dépendance ;
- aucun CDN Tailwind ou Material Icons ;
- aucun script inline ;
- aucun actif distant Stitch/Google ;
- SVG inline ou actifs locaux ;
- images avec dimensions réservées ;
- aucun chargement de relation KYC/document ;
- aucune requête Donation/Payment/Webhook/Ledger ;
- aucun secret ou payload privé ;
- aucune migration.

14. TESTS OBLIGATOIRES

Ajouter des tests Feature couvrant au minimum :
- route nommée et réponse 200 pour `PUBLIC + PUBLISHED` ;
- réponse 200 pour `UNLISTED + PUBLISHED` par URL directe ;
- `noindex,nofollow` pour UNLISTED ;
- réponse 404 pour PRIVATE, TARGETED et chaque statut non PUBLISHED ;
- campagne soft-deleted introuvable ;
- titre, récit, montants et quatre projections rendus depuis la campagne réelle ;
- XOF affiché FCFA ;
- objectif dépassé sans largeur supérieure à 100 % ;
- description hostile échappée ;
- aucun nom/email privé, KYC, document, représentant ou donnée provider exposé ;
- aucune requête sur KYC, documents, donations, payments ou webhook_events ;
- carte homepage réelle liée vers la fiche ;
- carte de démonstration sans lien mort ;
- CTA indisponibles honnêtes ;
- partage présent sans `href="#"` ;
- meta PUBLIC et UNLISTED correctes ;
- composants et barre sticky présents sans navigation basse générale.

Éviter les assertions fragiles sur de longues classes Tailwind.

15. VALIDATIONS

Exécuter dans cet ordre :
1. tests ciblés UI-04 ;
2. tests Homepage/UI-03 ;
3. tests Campaign API ;
4. suite Laravel complète ;
5. Pint ciblé sur les fichiers PHP UI-04 ;
6. `npm.cmd run build` ;
7. `git diff --check -- apps/api` ;
8. inspection locale à 320, 390, 768, 1024 et 1440 px si un navigateur est disponible ;
9. inspection complète du diff.

Rechercher dans les fichiers modifiés :
- `href="#"` ;
- `javascript:void(0)` ;
- `cdn.tailwindcss.com` ;
- `lh3.googleusercontent.com` ;
- `#493BC8` ;
- Epilogue et Plus Jakarta ;
- FLOAT ou DOUBLE ;
- promesses financières interdites de DESIGN.md ;
- accès KYC, documents, representatives, payments, webhook ou provider payloads.

16. HORS PÉRIMÈTRE

- migration ou modèle CampaignMedia ;
- intégration YouTube, Facebook ou TikTok ;
- upload et traitement média ;
- CampaignUpdate, Comment ou Follow ;
- liste publique nominative des dons ;
- formulaire web Donation/Payment ;
- Wave, Orange Money, carte bancaire ou paiement réel ;
- données KYC publiques ;
- dashboard ;
- cache public ;
- modification Finance, Refund ou Payout.

CRITÈRES D'ACCEPTATION
- Fiche réelle accessible uniquement selon ADR-011.
- PUBLIC indexable et UNLISTED non indexable.
- Hiérarchie media-first fidèle à la V3.
- Aucun média ou chiffre fictif mélangé aux campagnes réelles.
- Barre sticky mobile unique et carte sticky desktop.
- Partage réellement fonctionnel ; autres fonctions absentes clairement désactivées.
- Aucune donnée privée ou financière sensible exposée.
- Homepage reliée aux fiches réelles sans régression.
- Tests, Pint, build et diff-check verts.

RAPPORT FINAL
Fournir en français :
- résumé ;
- fichiers créés/modifiés ;
- règles de visibilité et SEO ;
- projection publique retenue ;
- comportement de chaque action ;
- résultats exacts des validations ;
- limites fonctionnelles restantes ;
- changements concurrents observés ;
- état Git ;
- GO/NO-GO UI-04 ;
- message Conventional Commit proposé, sans commit ni push.
```

## Message de commit proposé après validation

```text
feat(ui): ajouter la fiche campagne publique
```
