# Prompt maître Google Stitch — Barkeelu Homepage V1

## Utilisation

Joindre à Google Stitch :

- le logo horizontal Barkeelu ;
- le logo carré Barkeelu ;
- `docs/design/DESIGN.md`.

Demander une première génération **desktop 1440 px et mobile 390 px**, puis itérer section par section.

## Prompt à copier dans Stitch

```text
Conçois le design system initial et la page d’accueil responsive de Barkeelu.com, une plateforme sénégalaise de fundraising, de dons, de solidarité et d’impact social.

OBJECTIF
Créer une interface originale, moderne, minimaliste, humaine et rassurante. S’inspirer principalement de la simplicité et de l’efficacité de conversion de GoFundMe, avec une influence secondaire d’Ulule pour la découverte éditoriale et la mise en valeur des projets. Ne copier aucune mise en page, illustration, icône, formulation ou identité de ces plateformes.

IDENTITÉ IMPOSÉE
- Couleur primaire : #6257E2.
- Couleur secondaire : #FEA500.
- Le vert présent dans le symbole du logo est patrimonial et doit rester discret dans l’interface.
- Police : Kodchasan Medium depuis Google Fonts ; utiliser Kodchasan SemiBold pour les titres si disponible.
- Utiliser le logo horizontal dans l’en-tête desktop et le logo carré dans les contextes mobiles compacts.
- Respecter les proportions et couleurs des logos fournis.
- Ton visuel : confiance, solidarité, proximité, dignité, transparence, Sénégal et diaspora.

PUBLICS
Citoyens, familles, dahiras, associations, ONG, fondations, entreprises/RSE et diaspora. L’expérience principale doit fonctionner parfaitement sur un smartphone Android et une connexion mobile moyenne.

LIVRABLES
1. Une page de fondations/design system montrant couleurs, typographie, espacements, boutons, champs, badges de statut, cartes campagne, barre de progression, navigation et états focus/disabled/loading.
2. Une homepage desktop de 1440 px.
3. Une homepage mobile de 390 px.
4. Des composants réutilisables cohérents entre les deux formats.

STRUCTURE DE LA HOMEPAGE
1. En-tête : logo, Découvrir, Comment ça marche, À propos, recherche, Se connecter et CTA « Lancer une collecte ».
2. Hero sans carrousel : promesse claire, court texte explicatif, recherche de campagnes, CTA principal « Lancer une collecte » et CTA secondaire « Découvrir les collectes ».
3. Section « Collectes à soutenir » avec cartes de campagnes.
4. Section de découverte par causes, présentée comme une taxonomie à confirmer.
5. Section « Comment ça marche » en trois étapes simples : créer, partager, collecter.
6. Bloc de confiance : vérification des campagnes et bénéficiaires, transparence des frais, paiements adaptés au Sénégal.
7. Section éditoriale « Leur mobilisation a changé les choses » avec récits d’impact.
8. CTA final puis pied de page institutionnel, aide, sécurité, légal et réseaux sociaux.

COMPOSANT CARTE CAMPAGNE
Inclure une image 16:9, un titre sur deux lignes maximum, le nom de l’organisateur, une localisation optionnelle, le montant collecté en FCFA, l’objectif, une barre de progression et un badge de vérification explicite. Utiliser des données de démonstration clairement fictives et réalistes pour le Sénégal. Ne pas afficher de note, Trust Score ou compteur non nécessaire.

CONTENU D’EXEMPLE
Utiliser des campagnes fictives et dignes couvrant par exemple santé, éducation, solidarité familiale, projet communautaire et environnement. Ne pas utiliser de vraies personnes, organisations ou affirmations sensibles.

STYLE
- Beaucoup d’espace blanc et hiérarchie visuelle forte.
- Cartes sobres, rayon de 16 px, ombres très légères.
- CTA principal violet avec texte blanc.
- Orange réservé aux accents et mises en avant secondaires.
- Texte principal presque noir, surfaces secondaires gris très clair.
- Photographies humaines authentiques et respectueuses, sans misérabilisme.
- Icônes simples et cohérentes.
- Aucun dégradé excessif, glassmorphism, effet 3D ou décoration folklorique générique.

ACCESSIBILITÉ ET RESPONSIVE
- Contraste WCAG AA.
- Corps de texte de 16 px minimum.
- Zones tactiles de 44 px minimum.
- Focus clavier visible.
- Ne jamais transmettre une information uniquement par couleur.
- Sur mobile, navigation compacte, sections verticales, cartes facilement balayables sans masquer de contenu, CTA lisibles et non superposés.

CONTRAINTES MÉTIER
- Devise : FCFA / XOF.
- Différencier clairement organisateur et bénéficiaire.
- Une campagne peut être vérifiée, mais vérification, note, Trust Score et Transparency Score sont des notions distinctes.
- Ne jamais afficher un paiement comme confirmé sur la seule base d’un retour du navigateur.
- Les catégories, paiements, dons, commentaires, preuves et scores avancés sont des interfaces cibles : éviter de suggérer qu’ils sont déjà disponibles techniquement.
- L’interface finale sera intégrée dans Laravel SSR et connectée à une API REST /api/v1.

MICROCOPY PROPOSÉE
- Titre hero : « Ensemble, donnons vie aux projets qui comptent. »
- Sous-titre : « Créez ou soutenez une collecte solidaire, simplement et en toute confiance. »
- Recherche : « Rechercher une collecte, une cause ou une organisation ».
- CTA : « Lancer une collecte » et « Faire un don ».

SORTIE ATTENDUE
Produire une proposition cohérente et prête à être commentée, avec noms de composants et variantes. Fournir aussi les règles de design dans un DESIGN.md exportable, en conservant exactement les couleurs, la typographie et les principes indiqués ici.
```

## Critères de validation

- L’identité Barkeelu est reconnaissable sans ressembler à une copie.
- Le CTA principal est immédiatement identifiable.
- Le parcours visuel fonctionne d’abord sur mobile.
- Les montants et frais sont compréhensibles en FCFA.
- La confiance repose sur des libellés précis, pas sur des badges décoratifs.
- Le logo conserve ses proportions et reste lisible.
- Aucun élément ne présente une fonctionnalité future comme déjà opérationnelle.
