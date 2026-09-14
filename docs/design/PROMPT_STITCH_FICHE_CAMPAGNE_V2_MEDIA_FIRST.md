# Prompt Google Stitch — Fiche campagne V2 Media-first

## Décision UX

La fiche campagne Barkeelu privilégie les vidéos, les images, les chiffres clés et les actions lisibles avant les textes longs. Cette orientation répond aux usages mobiles du Sénégal et de la diaspora, sans sacrifier accessibilité, transparence ou performance réseau.

## Prompt à copier dans Stitch

```text
Reprends les maquettes desktop et mobile existantes de la fiche campagne Barkeelu et crée une V2 « media-first ». Inspire-toi des principes de hiérarchie de la capture GoFundMe fournie, sans reproduire son identité, ses couleurs, ses textes ou sa mise en page exacte.

Conserve l’identité Barkeelu :
- Kodchasan 500 et 600 exclusivement ;
- primaire #6257E2 ;
- hover #5146CE ;
- accent #FEA500 ;
- texte #17171C et #4E4D58 ;
- surfaces blanches et #F5F4F8 ;
- rayon 10 px pour contrôles, 16 px pour cartes, 24 px pour grands blocs ;
- aucun #493BC8, Epilogue ou Plus Jakarta ;
- aucun vert fonctionnel issu du logo.

OBJECTIF UX
Sur mobile, l’utilisateur doit voir immédiatement :
1. le titre ;
2. la vidéo principale ;
3. les images ;
4. le montant collecté, l’objectif et les statistiques ;
5. les actions « Je soutiens », « Partager » et « Suivre » ;
6. les onglets de contenu.

Les textes longs viennent ensuite. Les contenus sont fictifs et doivent porter la mention : « Maquette — contenus et données fictifs ».

VERSION MOBILE — 390 PX

1. HEADER COMPACT
- Logo carré Barkeelu à gauche.
- Bouton menu accessible à droite.
- Aucun avatar anonyme.
- Aucun bouton « Lancer » tant que la création web n’est pas disponible.

2. TITRE PRIORITAIRE
- Placer le titre immédiatement sous le lien retour.
- Taille recommandée : 30 à 32 px, graisse 600, interligne 36 à 40 px.
- Maximum trois lignes avant troncature contrôlée ; prévoir l’accès au titre complet.
- Sous le titre : statut de collecte et libellé générique « Collecte solidaire ».

3. VIDÉO PRINCIPALE AVANT LES PHOTOS
- Créer un lecteur vidéo prioritaire avant l’image principale.
- Afficher une vignette locale, un grand bouton lecture, la durée et le fournisseur sous forme neutre.
- Prévoir YouTube, Facebook, TikTok ou autre fournisseur via un composant générique.
- Ne pas afficher plusieurs logos de fournisseurs simultanément.
- Ne pas lire automatiquement la vidéo.
- Afficher la vignette avant chargement du lecteur afin d’économiser les données mobiles.
- Prévoir « Lire la vidéo » et une option de sous-titres/transcription.
- Pour une vidéo verticale TikTok, conserver un cadre stable avec vidéo 9:16 centrée, sans étirer l’image.
- Pour YouTube/Facebook horizontal, utiliser 16:9.
- Ajouter un état « vidéo indisponible » avec retour sûr vers la galerie.

4. GALERIE PHOTO
- Après la vidéo, afficher une photo principale 16:9 et une rangée de miniatures.
- Afficher le compteur uniquement si plusieurs médias existent réellement.
- Prévoir plein écran, navigation tactile et texte alternatif.
- Utiliser uniquement des illustrations fictives sans personne identifiable dans la maquette.
- Ne pas utiliser d’URL Google Stitch comme actif final.

5. BLOC MONTANTS ET STATISTIQUES
- Créer une carte très visible immédiatement après les médias.
- Afficher : montant net collecté, objectif, pourcentage, nombre de dons et nombre de donateurs distincts.
- Utiliser une barre ou un anneau de progression accessible.
- FCFA très lisible ; chiffres tabulaires.
- Afficher « Objectif atteint » comme état dérivé, jamais comme statut Campaign.
- Si l’objectif est dépassé, conserver la vraie valeur et borner la barre à 100 %.
- Ne pas afficher de délai, classement ou statistique inventée.

6. ACTIONS PRINCIPALES
- Ligne de trois actions immédiatement sous les statistiques :
  - « Je soutiens » : bouton primaire violet, plus large ;
  - « Partager » : bouton secondaire ;
  - « Suivre » : bouton tertiaire avec icône et état suivi/non suivi.
- Cibles tactiles minimales 44 × 44 px.
- Dans la maquette : « Je soutiens — démonstration » ou mention proche « Maquette non transactionnelle ».
- « Suivre » est une fonctionnalité cible non encore implémentée : la marquer comme prototype dans la planche, sans affirmer sa disponibilité.

7. ONGLETS DE CONTENU
- Créer une barre d’onglets horizontalement scrollable sur mobile et sticky sous le header lorsqu’elle atteint le haut.
- Onglets :
  1. À propos ;
  2. Mises à jour ;
  3. Dons ;
  4. Commentaires ;
  5. Organisateur et bénéficiaire.
- Afficher les compteurs uniquement s’ils proviennent de données réelles.
- Souligner l’onglet actif avec #6257E2 et conserver un focus clavier visible.
- Ne pas masquer le contenu dans une interface inaccessible : prévoir rôles tablist/tab/tabpanel et navigation clavier.

8. CONTENU DES ONGLETS — MAQUETTE CIBLE
- À propos : résumé puis récit complet, avec bouton « Lire la suite » sur mobile.
- Mises à jour : chronologie avec date, titre, extrait et média optionnel.
- Dons : montant, date relative, anonymat respecté ; aucun vrai nom dans la maquette.
- Commentaires : liste modérée et formulaire cible clairement identifié comme prototype.
- Organisateur et bénéficiaire : deux cartes distinctes, informations publiques autorisées seulement, sans KYC, email privé ou téléphone.
- Si une fonction n’existe pas dans le backend actuel, afficher son état vide ou « Fonctionnalité prévue », sans faux contenu opérationnel.

9. BARRE D’ACTIONS STICKY MOBILE
- La barre apparaît après que le bloc d’actions principal sort de l’écran.
- Elle reste fixée en bas pendant le scroll.
- Elle contient une version compacte des statistiques : montant collecté, pourcentage ou progression et objectif.
- Elle contient les actions « Je soutiens » et « Partager » ; « Suivre » peut être une icône accessible si l’espace le permet.
- Respecter env(safe-area-inset-bottom).
- Ajouter un padding-bottom suffisant à la page et au footer.
- Ne jamais chevaucher onglets, contenu, formulaire, footer ou liens légaux.
- Prévoir un bouton de fermeture ou un comportement discret si le clavier mobile est ouvert.

VERSION DESKTOP — 1440 PX

1. Titre large en haut : 40 à 44 px, graisse 600.
2. Grille principale inspirée du principe GoFundMe : contenu média à gauche, carte action/statistiques sticky à droite.
3. Dans la colonne gauche :
   - vidéo principale en premier ;
   - galerie photo ensuite ;
   - organisateur sous les médias ;
   - barre d’onglets ;
   - contenu de l’onglet sélectionné.
4. Dans la colonne droite sticky :
   - montant collecté ;
   - objectif ;
   - progression ;
   - nombre de dons et donateurs ;
   - boutons « Je soutiens », « Partager », « Suivre » ;
   - quelques dons récents anonymisés comme composant cible ;
   - bouton « Afficher tous les dons » en prototype ;
   - mentions : « Frais présentés avant confirmation » et « Paiement confirmé uniquement après vérification serveur ».
5. Conserver la carte sticky tant que la colonne principale continue, sans chevaucher le footer.
6. Ajouter en fin de récit une seconde rangée « Je soutiens », « Partager », « Suivre » pour éviter de remonter.

PERFORMANCE VIDÉO ET RÉSEAUX MOBILES
- Aucun autoplay.
- Thumbnail légère avant consentement de lecture.
- Charger l’iframe seulement après action utilisateur.
- Prévoir poster optimisé, lazy loading et dimensions réservées.
- Indiquer la durée avant lecture.
- Prévoir sous-titres ou transcription.
- Ajouter un état réseau lent : « Charger la vidéo » plutôt qu’un écran vide.
- Les photos restent visibles si la vidéo ne charge pas.

ÉTATS MÉTIER
- OPEN : « Je soutiens » actif seulement comme maquette.
- NOT_STARTED : action désactivée, « Collecte pas encore ouverte ».
- PAUSED : action désactivée, « Collecte temporairement suspendue ».
- CLOSED : action désactivée, « Collecte terminée ».
- Objectif atteint + OPEN : message « Objectif atteint », action potentiellement conservée selon politique future.
- Objectif dépassé : pourcentage réel, barre limitée à 100 %.

LIMITES TECHNIQUES À SIGNALER
Les composants suivants sont des cibles UX à concevoir maintenant mais ne doivent pas être présentés comme déjà livrés :
- vidéo et galerie Campaign ;
- abonnements « Suivre » ;
- mises à jour ;
- liste publique des dons ;
- commentaires ;
- formulaire de don web final ;
- partage avancé.

CONTENUS INTERDITS
- certification, garantie de versement ou promesse de décaissement ;
- validation sous 24 h ;
- paiement instantané ou 100 % sécurisé ;
- gratuité garantie ou absence de frais cachés ;
- KYC, Trust Score ou Transparency Score public inventé ;
- identité réelle dans les exemples ;
- intégration Wave, Orange Money, Visa ou Mastercard présentée comme opérationnelle.

LIVRABLES
1. Fiche campagne mobile 390 px complète avec les états : haut de page, onglets et barre sticky en scroll.
2. Variante mobile 320 px vérifiée.
3. Fiche campagne desktop 1440 px.
4. État d’un onglet « Dons » actif.
5. État d’un onglet « Mises à jour » actif.
6. Planche des statuts OPEN, NOT_STARTED, PAUSED, CLOSED, objectif atteint et dépassé.
7. DESIGN.md, code.html et captures strictement cohérents.

Avant export, vérifier : Kodchasan partout, #6257E2 comme primaire, aucun chevauchement mobile, aucune personne identifiable, aucun href="#", aucun CDN Tailwind, aucune URL lh3.googleusercontent.com et aucune promesse interdite.
```

## Points de validation

- La vidéo précède toujours la galerie.
- Le bloc financier et les actions restent visibles sans chercher.
- Les onglets réduisent la longueur perçue du récit.
- La barre sticky mobile facilite l’action sans masquer le contenu.
- Le desktop conserve une colonne d’action sticky.
- Les fonctions futures sont conçues mais explicitement identifiées comme prototypes.
