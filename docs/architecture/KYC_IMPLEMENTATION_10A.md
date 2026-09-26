# Barkeelu — Implémentation KYC 10A

État documenté au 2026-09-26, après les lots 10A3, 10A4A, 10A4B et 10A4C1 à 10A4C4.

## 1. Portée

Cette note décrit l'état réellement présent dans le dépôt pour les profils KYC, les documents, leur audit, leur sécurité et le pipeline de dérivés image. Elle ne décrit pas comme réalisés le gating payout, l'antivirus, l'optimisation PDF, l'OCR, l'IA, la biométrie ou la preuve de localisation.

## 2. Modèle

- `KycProfile` : profil stable rattaché à un seul sujet parmi `User`, `Organization` et `Beneficiary`.
- `KycDocument` : MASTER documentaire lié à un profil, avec statut, stockage privé, MIME serveur, taille, métadonnées et SHA-256.
- `KycReviewEvent` : événement d'audit d'une transition de profil ou de document.
- `KycDocumentAsset` : projection technique d'un document, identifiée par rôle, processor et version.

## 3. Workflow profile

Transitions implémentées :

- `DRAFT` vers `SUBMITTED` ;
- `SUBMITTED` vers `UNDER_REVIEW` ;
- `UNDER_REVIEW` vers `VERIFIED` ou `REJECTED` ;
- `VERIFIED` vers `SUSPENDED` ou `EXPIRED` ;
- `REJECTED` ou `EXPIRED` vers `DRAFT` ;
- `SUSPENDED` vers `UNDER_REVIEW` ;
- changement de risque avec acteur compliance.

Les opérations de revue critique interdisent le self-review. Les transitions utilisent un verrou de ligne et sont liées à un événement d'audit.

## 4. Workflow document

Transitions implémentées :

- `UPLOADED` vers `ACCEPTED` ;
- `UPLOADED` ou `ACCEPTED` vers `REJECTED` ;
- `ACCEPTED` vers `EXPIRED` par le système ou la compliance.

L'uploader ne peut pas accepter ou rejeter son propre document dans une revue critique. Les transitions sont transactionnelles et auditées.

## 5. Audit immutable

`kyc_review_events` conserve les événements de revue liés au profil ou au document. Les transitions et leur audit sont écrits dans la même transaction, avec verrouillage de la ligne métier. Des contraintes PostgreSQL vérifient la cohérence de l'entité et de l'acteur. Des triggers PostgreSQL empêchent la mise à jour et la suppression des événements lorsqu'ils sont présents dans le schéma.

## 6. Sécurité fichiers

- disque privé : `kyc_private` ;
- upload validé côté serveur par MIME, taille et signature ;
- nom de stockage aléatoire, sans nom original ni PII ;
- SHA-256 calculé sur les octets reçus ;
- téléchargement contrôlé par la Policy ;
- `Content-Type` fourni depuis le MIME serveur stocké ;
- limite `KYC_UPLOAD_MAX_SIZE_KB`, 5120 par défaut, soit 5 MiB.

Le MASTER WebP est conservé tel que reçu. Il n'est pas décodé ni réencodé à l'upload.

## 7. Formats supportés

| Format | Upload master | Pipeline image |
|---|---:|---:|
| PDF | Oui | Non |
| JPEG | Oui | Oui |
| PNG | Oui | Oui |
| WebP | Oui | Oui |
| HEIC/HEIF | Non | Non |
| AVIF | Non | Non |

Le WebP est validé par sa signature conteneur `RIFF` aux octets 0 à 3 et `WEBP` aux octets 8 à 11. Le WebP animé n'est pas rejeté par une détection dédiée à ce stade.

## 8. Dérivés

La table `kyc_document_assets` utilise les rôles `PREVIEW` et `OPTIMIZED`, ainsi que les statuts `PENDING`, `READY` et `FAILED`.

Le rôle `PREVIEW` existe dans le modèle mais n'est pas généré par 10A4C. Le pipeline actuel produit uniquement un asset `OPTIMIZED` avec `image_webp` et la version `v1`.

## 9. Pipeline image

Le processor `image_webp/v1` utilise Intervention Image v3 avec le driver GD explicite.

- sortie : WebP ;
- qualité par défaut : 88 ;
- dimension maximale : 2400 px ;
- limite d'entrée : 50 MP ;
- aucun upscale ;
- orientation JPEG traitée ;
- métadonnées client non recopiées volontairement ;
- MASTER inchangé.

Les entrées image sont JPEG, PNG et WebP. Un PDF reste un MASTER valide mais n'entre pas dans ce pipeline.

## 10. Queue

La queue dédiée est `kyc-media`, sur la connexion de queue existante.

Le job est `ShouldBeUnique` avec :

- `tries` : 3 ;
- `backoff` : `[30, 120, 300]` secondes ;
- `timeout` : 180 secondes ;
- `uniqueFor` : 3600 secondes.

## 11. Idempotence

L'unicité logique est portée par :

`document + role + processor + processor_version`.

Le scheduler réutilise l'asset existant. Un asset `READY` est terminal pour cette version du pipeline. Un asset `FAILED` est réarmé vers `PENDING` sur un nouvel appel du scheduler, avec ses métadonnées dérivées nettoyées. Aucun nouvel asset logique n'est créé pour ce retry.

## 12. Post-commit

Le scheduler est appelé après le retour réussi de la transaction qui crée le document et son audit. Ainsi, une panne Redis, queue ou dérivé ne peut pas faire supprimer le MASTER ni annuler un document déjà committé.

Si le dispatch échoue, l'asset reste `PENDING`, l'erreur est journalisée avec un contexte technique minimal et l'upload reste valide. Une réconciliation ou un nouvel appel peut récupérer le travail.

## 13. Storage

Les objets MASTER et dérivés sont séparés :

- MASTER : `profiles/{profile_public_id}/{random}.{extension}` ;
- dérivé : `profiles/{profile_public_id}/derivatives/{document_public_id}/{asset_public_id}.webp`.

Les clés utilisent des identifiants techniques/publics et ne contiennent ni email, ni téléphone, ni nom, ni numéro de pièce.

## 14. Exploitation

Commande indicative :

```bash
php artisan queue:work redis --queue=kyc-media
```

Un worker média dédié est nécessaire en production afin de séparer le traitement CPU image des queues critiques de paiement et webhook. Aucun worker ni service CloudPanel n'est configuré par ce lot.

## 15. Configuration

Les paramètres concernés sont :

- `KYC_UPLOAD_MAX_SIZE_KB` ;
- `KYC_IMAGE_OPTIMIZATION_ENABLED` ;
- `KYC_IMAGE_QUEUE` ;
- `KYC_IMAGE_WEBP_QUALITY` ;
- `KYC_IMAGE_MAX_DIMENSION` ;
- `KYC_IMAGE_MAX_INPUT_PIXELS`.

Aucun secret n'est documenté ici.

## 16. Limites connues

- optimisation PDF absente ;
- antivirus et quarantaine absents ;
- WebP animé non rejeté spécifiquement ;
- HEIC/HEIF absent côté serveur ;
- AVIF absent à l'upload ;
- OCR absent ;
- IA compliance absente ;
- biométrie absente ;
- preuve de géolocalisation absente ;
- gating payout KYC non implémenté.

## 17. Tests

Les validations ciblées documentées pour 10A4C4 sont :

- Security : 8 tests, 29 assertions ;
- Processor : 10 tests, 26 assertions ;
- Queue/upload/job : 10 tests, 57 assertions ;
- suite KYC : 89 tests, 440 assertions ;
- Organization/RBAC : 8 tests, 56 assertions.

La full suite n'a pas été exécutée pour 10A4C4. Les tests couvrent notamment les formats, signatures, upload WebP, scheduler, queue dédiée, idempotence, job READY et réarmement FAILED.
