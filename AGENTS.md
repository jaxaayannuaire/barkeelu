# AGENTS.md — Barkeelu.com

## 1. Projet

Barkeelu.com est une plateforme de fundraising, crowdfunding, dons, solidarité et impact social.

Le projet doit rester :
- sécurisé ;
- testable ;
- modulaire ;
- auditable ;
- évolutif ;
- API-first ;
- adapté au Sénégal et à la diaspora.

## 2. Dépôt et environnement

Dépôt GitHub :

```text
https://github.com/jaxaayannuaire/barkeelu
```

Racine locale Windows :

```text
D:\projets_web\barkeelu\v1
```

Environnement principal :

```text
Windows
PowerShell
Codex CLI
```

## 3. Langue

Tous les commentaires, rapports, descriptions, documents et messages de commit doivent être rédigés en français.

Les identifiants techniques restent en anglais :
- classes ;
- méthodes ;
- variables ;
- tables ;
- colonnes ;
- routes ;
- enums techniques.

## 4. Stack cible

Backend :
- Laravel 13 ;
- PHP 8.3 ;
- PostgreSQL ;
- Redis ;
- Laravel Sanctum ;
- Laravel Reverb ;
- queues/events ;
- API REST `/api/v1`.

Clients :
- Laravel SSR ;
- Flutter ;
- intégrations externes ;
- bots/services.

## 5. Sources de vérité

Ordre de confiance :

1. état réel du dépôt ;
2. tests ;
3. migrations/schéma ;
4. code ;
5. ADR acceptés ;
6. documentation architecture ;
7. README ;
8. ROADMAP ;
9. CHANGELOG ;
10. rapports historiques.

Ne jamais déclarer une fonctionnalité réalisée uniquement parce qu'elle figure dans une roadmap.

## 6. Architecture de référence

Lire avant tout travail important :

```text
docs/architecture/BARKEELU_ERD_V1_2_CONSOLIDE.md
docs/decisions/
```

Les anciennes versions ERD sont historiques et ne doivent pas servir seules à générer les migrations.

## 7. Domaines critiques

Augmenter la profondeur d'analyse pour :
- payments ;
- webhooks ;
- ledger ;
- refunds ;
- payouts ;
- KYC ;
- audit ;
- permissions ;
- secrets ;
- données privées ;
- transactional outbox.

## 8. Argent

Règles obligatoires :
- `BIGINT` pour les montants ;
- aucun FLOAT/DOUBLE ;
- devise explicite ;
- taux en basis points ;
- calcul serveur ;
- historique immuable ;
- frais appliqués figés à la transaction.

## 9. Donations et paiements

Relation :

```text
Donation 1 ── N Payment
```

Ne pas remettre `payment_id` dans `donations`.

Ne jamais considérer le navigateur ou Flutter comme source de vérité d'un paiement.

Un second paiement réellement encaissé doit rester traçable et être traité via `UNAPPLIED_FUNDS`.

## 10. Webhooks

Règles :
- vérifier signature ;
- préserver le corps brut si nécessaire ;
- persister avant traitement métier ;
- idempotence ;
- tolérer doublons/ordre différent/retries ;
- vérifier montant/devise/compte provider côté serveur ;
- traiter par queue.

## 11. Ledger

Le ledger est en double entrée et append-only.

Une transaction postée :
- ne se modifie pas ;
- ne se supprime pas ;
- se corrige par reversal.

Ne pas implémenter l'équilibre multi-lignes par simple `CHECK`.

Suivre ADR-002.

## 12. Transactional Outbox

Les événements critiques sont écrits dans la même transaction SQL que l'état métier.

Redis/Reverb ne sont pas source de vérité.

Suivre ADR-006.

## 13. Payout

Toujours distinguer :
- Payment entrant ;
- Refund ;
- Payout sortant.

Avant payout :
- KYC valide ;
- bénéficiaire valide ;
- destination approuvée ;
- solde réservé atomiquement ;
- approbation ;
- idempotence.

Un timeout provider ne justifie jamais automatiquement une seconde émission.

## 14. Séparation des rôles

Au minimum :

```text
finance_operator != finance_approver
```

Un utilisateur ne peut pas approuver son propre payout.

## 15. KYC

Les documents KYC :
- stockage privé ;
- accès contrôlé ;
- pas de secret dans Git ;
- pas de contenu sensible recopié dans les logs.

Suivre ADR-008.

## 16. Barkeelu Live

Principe :

```text
Barkeelu ne transporte pas normalement la vidéo.
```

Barkeelu fournit données, overlay, tracking et temps réel.

Suivre ADR-007.

## 17. API

La sécurité est appliquée côté backend.

Avant chaque route :
1. authentification ;
2. autorisation ;
3. ownership/organisation ;
4. validation ;
5. idempotence si nécessaire ;
6. tests correspondants.

## 18. Base de données

Avant toute migration :
1. lire l'ERD consolidé ;
2. lire les ADR concernés ;
3. inspecter les migrations existantes ;
4. inspecter les modèles ;
5. vérifier FK ;
6. vérifier indexes ;
7. vérifier contraintes d'unicité ;
8. vérifier nullabilité ;
9. vérifier concurrence ;
10. ajouter des tests.

Ne pas modifier une migration historique partagée sans justification.

## 19. Tests

Ordre :
1. tests ciblés ;
2. tests du domaine ;
3. suite complète avant checkpoint important.

Pour les domaines financiers, tester aussi sur PostgreSQL réel :
- concurrence ;
- verrouillage ;
- idempotence ;
- reprise ;
- contraintes.

Ne jamais supprimer un test de sécurité pour faire passer la suite.

## 20. Audit lecture seule

Si une mission est indiquée comme audit/revue/lecture seule :
- aucun fichier modifié ;
- aucune dépendance ajoutée ;
- aucune migration ;
- aucune commande destructive ;
- aucun commit ;
- aucun push.

Rapport :
- constat ;
- preuves ;
- risques ;
- fichiers concernés ;
- recommandations ;
- priorité ;
- GO/NO-GO.

## 21. Git

Avant toute mission :

```powershell
git status
git branch --show-current
git log -1 --oneline
```

Sans autorisation explicite, ne jamais exécuter :
- `git commit`
- `git push`
- `git merge`
- `git rebase`
- `git reset --hard`
- `git clean`

Le push est toujours effectué manuellement par l'utilisateur.

Ne jamais écraser les modifications utilisateur non commitées.

## 22. Messages de commit

Conventional Commits en français :

```text
type(scope): description en français
```

Exemples :

```text
docs(architecture): ajouter l'ERD consolidé
docs(decisions): documenter le ledger en double entrée
feat(payment): ajouter la persistance des webhooks
test(payout): couvrir les réservations concurrentes
```

Codex peut proposer un message mais ne commit pas sans autorisation.

## 23. Dépendances

Ne jamais ajouter automatiquement une dépendance Composer/npm/infrastructure sans :
- justification ;
- analyse ;
- autorisation explicite.

## 24. Cycle de développement

```text
Spécification
→ Codex
→ tests
→ rapport
→ validation Tech Lead
→ documentation
→ CHANGELOG
→ ROADMAP
→ git diff
→ commit autorisé
→ push manuel utilisateur
```

## 25. Règle Codex

Codex doit privilégier :

```text
comprendre
→ modifier peu
→ tester
→ inspecter
→ expliquer
```

Pour toute mission sensible, commencer par inspecter l'existant avant d'écrire.
