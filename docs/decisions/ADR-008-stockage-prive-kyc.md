# ADR-008 — Stockage privé KYC

## Statut
Accepté pour conception V1.

## Décision
Les documents KYC ne sont jamais stockés dans un espace public.

Le modèle sépare :
- `kyc_profiles`
- `kyc_documents`

Les documents utilisent :
- clé objet privée ;
- hash ;
- statut ;
- dates d'émission/expiration ;
- metadata contrôlée.

## Accès
Prévoir :
- autorisations fines ;
- liens temporaires ;
- journalisation des consultations sensibles ;
- rotation/gestion des clés ;
- politique de rétention ;
- sauvegarde/restauration.

## Logs
Ne jamais recopier :
- pièces KYC ;
- secrets ;
- tokens ;
- documents complets

dans `audit_logs`, logs applicatifs ou traces d'erreur.
