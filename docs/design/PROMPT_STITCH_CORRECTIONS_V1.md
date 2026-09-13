# Prompt correctif Google Stitch — Barkeelu V1

## Objectif

Corriger les trois livrables existants sans réinventer leur direction visuelle :

1. Design System ;
2. Homepage desktop 1440 px ;
3. Homepage mobile 390 px.

## Prompt à copier dans Stitch

```text
Corrige les trois écrans Barkeelu existants en conservant leur structure générale, leur sobriété, leur hiérarchie et leur direction visuelle. Ne crée pas une nouvelle identité et n’ajoute aucune fonctionnalité.

RÈGLES IMPÉRATIVES

1. TYPOGRAPHIE
- Remplacer intégralement Plus Jakarta Sans par Kodchasan.
- Charger Kodchasan depuis Google Fonts.
- Utiliser Kodchasan Medium 500 pour le texte courant et SemiBold 600 pour les titres et boutons.
- Ne plus afficher « Kodchasan & Plus Jakarta » ni utiliser Plus Jakarta dans les tokens, le HTML ou le CSS.
- Fallback : Kodchasan, system-ui, sans-serif.

2. LOGOS
- Sur desktop, utiliser uniquement le logo horizontal fourni, sans ajouter le mot « Barkeelu » à côté : ce texte est déjà dans le logo.
- Sur mobile, utiliser le logo carré fourni ; le nom Barkeelu peut être affiché séparément seulement si l’espace et la lisibilité le justifient.
- Conserver les proportions, sans recadrage, recoloration ou déformation.

3. COULEURS
- Couleur d’action principale exacte : #6257E2.
- Hover principal : #5146CE.
- Accent secondaire exact : #FEA500.
- Le vert #39C43A appartient au symbole du logo. Ne pas l’utiliser comme couleur générique de certification, de badge ou de CTA.
- Conserver des couleurs d’état distinctes et accessibles.
- Ne pas remplacer la couleur principale par #493BC8 dans les composants standards.

4. VÉRACITÉ DU CONTENU
Supprimer ou reformuler toutes les affirmations non validées, notamment :
- « plateforme certifiée » ;
- « garantie de versement » ou « garantie de versement certifié » ;
- « validation sous 24 h » ;
- « paiement instantané » ;
- « paiements 100 % sécurisés » ;
- « retraits directs » ;
- « création 100 % gratuite » ;
- « sans frais cachés » ;
- « transparence totale » ;
- toute promesse de délai, de certification ou de résultat financier.

Employer à la place des formulations prudentes :
- « Campagnes examinées avant publication » ;
- « Identité du bénéficiaire vérifiée selon le niveau applicable » ;
- « Frais présentés avant confirmation » ;
- « Paiement confirmé après vérification par le serveur » ;
- « Décaissement soumis aux contrôles applicables ».

5. MOYENS DE PAIEMENT
- Wave est prioritaire mais son intégration reste en préparation.
- Orange Money et les cartes bancaires sont prévus ultérieurement.
- Ne pas présenter Wave, Orange Money, Visa ou Mastercard comme déjà opérationnels, partenaires ou intégrés.
- Remplacer les listes de paiement par : « Wave prioritaire — autres moyens prévus ultérieurement » lorsque ce contenu est nécessaire.

6. DONNÉES DE DÉMONSTRATION
- Ajouter en haut des pages de démonstration, de manière visible mais discrète : « Maquette — contenus et données fictifs ».
- Toutes les campagnes, organisations, personnes, montants, compteurs, témoignages et photos sont fictifs.
- Retirer toute formulation laissant croire qu’une campagne a réellement été financée sur Barkeelu.
- Transformer le témoignage en composant intitulé « Exemple de récit d’impact » avec une mention « Démonstration ».
- Ne pas utiliser de coordonnées inventées. Remplacer téléphone et email par des placeholders explicites ou supprimer le bloc.

7. DATES ET FRANÇAIS
- Footer : © 2026 Barkeelu.com.
- Corriger toutes les accents et formulations, notamment « Découvrir ».
- Employer « FCFA » dans l’interface et conserver XOF dans les spécifications techniques.

8. NAVIGATION MOBILE
- Conserver une navigation basse simple.
- Ne pas afficher « Mes dons » comme fonctionnalité disponible.
- Utiliser provisoirement : Accueil, Découvrir, Rechercher, Compte.
- Les boutons et liens d’une maquette restent clairement non transactionnels.

9. DESIGN SYSTEM
- Maintenir les composants boutons, champs, badges, progressions et cartes.
- Remplacer le badge vert « Certifié » par des exemples de statuts neutres et explicites : « Identité vérifiée », « Examen en cours », « Informations à compléter », « Non vérifié ».
- Ne jamais confondre vérification, Trust Score, Transparency Score et avis utilisateur.
- Conserver rayon 10 px pour contrôles, 16 px pour cartes et 24 px pour blocs éditoriaux.
- Préserver les cibles tactiles de 44 × 44 px et le contraste WCAG AA.

10. HOMEPAGE DESKTOP ET MOBILE
- Conserver le hero, les cartes de campagnes, « Comment ça marche », le bloc confiance, l’exemple d’impact, le CTA final et le footer.
- Remplacer les promesses commerciales non validées par des explications factuelles.
- Les catégories doivent porter la mention « Taxonomie à confirmer » uniquement sur la planche de spécification, pas comme texte commercial public.
- Sur mobile, supprimer toute redondance de recherche/navigation et maintenir une lecture fluide sans écran surchargé.

LIVRABLES ATTENDUS
- Design System corrigé.
- Homepage desktop corrigée en 1440 px.
- Homepage mobile corrigée en 390 px.
- HTML/CSS mis à jour pour chaque écran.
- DESIGN.md exporté et strictement cohérent avec les règles ci-dessus.

Ne pas ajouter d’autres textes marketing, partenaires, garanties, statistiques, labels de sécurité ou fonctionnalités non explicitement demandés.
```

## Critères de validation

- Aucun usage de Plus Jakarta Sans.
- Aucun logo horizontal doublonné par un texte.
- Aucune promesse, certification ou intégration non validée.
- Toutes les données de démonstration sont identifiées comme fictives.
- Les trois écrans utilisent les mêmes tokens et composants.
- Le rendu reste cohérent sur 1440 px et 390 px.
