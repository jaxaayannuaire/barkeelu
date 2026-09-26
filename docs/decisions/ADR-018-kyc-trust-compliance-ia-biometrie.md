# ADR-018 — KYC, Trust, Compliance, IA et biométrie

- **Projet** : Barkeelu.com
- **Statut** : Accepté
- **Date** : 2026-09-22
- **Branche** : `develop`
- **Portée** : cible KYC/Trust/Compliance, sans implémentation applicative
- **Références** : ADR-001, ADR-005, ADR-006, ADR-008, ADR-009, ADR-010, ADR-011, ADR-012, ADR-014

---

## 1. Contexte et état actuel

Le socle actuel contient `KycProfile`, `KycDocument`, leurs UUID publics, le
stockage privé `kyc_private`, la création de profil, l'upload de document, les
policies et les routes Sanctum de création, consultation et upload.

Les contraintes PostgreSQL garantissent exactement un sujet (`User`,
`Organization` ou `Beneficiary`) par profil et un profil par sujet. Les enums
existent mais, à ce jour, seuls `KycProfile::DRAFT` et
`KycDocument::UPLOADED` sont effectivement produits par le workflow.

Il n'existe pas encore de transition de profil ou document, de revue
compliance, d'historique immutable, de téléchargement privé contrôlé, de
scan antivirus, de gating Campaign/Payout, d'OCR, d'IA ou de biométrie.
`Beneficiary` expose `kycProfile()` ; l'absence équivalente à confirmer sur
`User` et `Organization` est une dette de modèle à traiter lors de la phase
d'implémentation, pas dans cet ADR.

## État d’implémentation — 2026-09-26

La section 1 décrit l’état du projet au moment de l’acceptation de cet ADR. Elle est conservée comme contexte historique. Depuis cette décision, les éléments suivants sont implémentés :

- workflow profil ;
- workflow document ;
- audit immutable ;
- séparation self-review ;
- sécurité des fichiers ;
- téléchargement privé ;
- assets image ;
- pipeline WebP asynchrone.

Restent non implémentés ou futurs :

- gating payout final ;
- Trust Campaign avancé ;
- OCR ;
- IA ;
- biométrie ;
- preuve de localisation ;
- antivirus.

## 2. Problème

Un enum seul ne constitue ni un workflow, ni une décision auditée, ni une
autorisation financière. Barkeelu doit pouvoir vérifier des identités et des
documents sensibles, suivre des décisions humaines, appliquer des règles
métier distinctes aux campagnes et aux sorties de fonds, puis accueillir des
analyses automatisées sans rendre un fournisseur IA autoritaire.

## 3. Décision principale : quatre domaines séparés

Les notions suivantes restent indépendantes :

| Domaine | Rôle |
|---|---|
| KYC | État de vérification du sujet d'identité : `User`, `Organization` ou `Beneficiary`. |
| Verification | Résultats techniques : documents, OCR, cohérences, biométrie, IA et règles. |
| Risk | Niveau de risque compliance (`UNKNOWN`, `LOW`, `MEDIUM`, `HIGH` dans l'enum existant). |
| Trust | Décision métier qui autorise ou bloque une action donnée, notamment Campaign ou Payout. |

Ainsi, `KYC VERIFIED` ne publie pas automatiquement une campagne et ne suffit
pas seul à autoriser une sortie de fonds. L'identity trust reste distinct du
campaign trust : une organisation vérifiée peut porter une campagne suspendue
pour contenu, fraude ou modération.

## 4. Sujets et profil stable

Chaque `KycProfile` conserve exactement un sujet, protégé par le XOR existant :

```text
User XOR Organization XOR Beneficiary
```

La décision V1 est de conserver **un profil stable par sujet**. Un rejet, une
expiration ou une suspension ouvre un nouveau cycle sur le même profil ; il ne
crée pas automatiquement un nouveau profil.

Avantages : unicité simple, point de lecture unique, liens stables vers les
documents et les décisions, pas de contournement par recréation. Inconvénient :
le profil cumule plusieurs cycles. Cet inconvénient est résolu par des
événements immutables et des documents versionnés, non par un second profil.

## 5. Workflow `KycProfile`

```text
DRAFT --submit--> SUBMITTED --start review--> UNDER_REVIEW
                                            |--> VERIFIED
                                            `--> REJECTED --reopen--> DRAFT

VERIFIED --suspend--> SUSPENDED --reopen--> UNDER_REVIEW
VERIFIED --expire--> EXPIRED ----renew----> DRAFT
```

| FROM | TO | ACTOR | PRECONDITIONS | REASON REQUIRED | AUDIT EVENT |
|---|---|---|---|---|---|
| — | `DRAFT` | Sujet autorisé ou compliance | XOR sujet et unicité | Non | `PROFILE_CREATED` |
| `DRAFT` | `SUBMITTED` | Submitter autorisé | Dossier minimum conforme, documents requis présents | Non | `SUBMITTED` |
| `SUBMITTED` | `UNDER_REVIEW` | Compliance | Assignation atomique | Non | `REVIEW_STARTED` |
| `UNDER_REVIEW` | `VERIFIED` | Compliance distinct du sujet et du submitter | Documents requis acceptés, revue complète | Non obligatoire | `VERIFIED` |
| `UNDER_REVIEW` | `REJECTED` | Compliance distinct du sujet et du submitter | Revue complète | Oui | `REJECTED` |
| `VERIFIED` | `SUSPENDED` | Compliance distinct du sujet | Signal/raison compliance | Oui | `SUSPENDED` |
| `VERIFIED` | `EXPIRED` | Système ou compliance | Date ou règle d'expiration validée | Oui si humain | `EXPIRED` |
| `REJECTED` | `DRAFT` | Sujet autorisé ou compliance | Correction ou réouverture | Oui si compliance impose la réouverture | `REOPENED` |
| `EXPIRED` | `DRAFT` | Sujet autorisé ou compliance | Renouvellement | Non | `REOPENED` |
| `SUSPENDED` | `UNDER_REVIEW` | Compliance | Levée et nouvelle analyse justifiées | Oui | `REOPENED` |

Une modification matérielle d'identité ou des documents requis est autorisée en
`DRAFT`. Elle n'est pas silencieusement modifiable en `UNDER_REVIEW` ; elle
doit rouvrir le cycle vers `DRAFT` et produire l'audit adéquat. Une modification
matérielle après `VERIFIED` déclenche également une revue plutôt que de
conserver une vérification devenue obsolète.

`expires_at` reste nullable en V1 : aucune durée universelle n'est inventée.
Une vérification qui dépend d'un document arrivant à expiration doit toutefois
devenir expirée selon une politique documentaire explicitement approuvée.
`VERIFIED` n'est jamais garantie permanente. Toute opération sensible consulte
l'état courant ; `VERIFIED -> SUSPENDED` et `VERIFIED -> EXPIRED` bloquent les
nouvelles opérations exigeant KYC valide, sans effacer décision historique.

Le profil KYC reste le même pendant tous ces cycles. Les événements précédents
et documents historiques ne sont jamais écrasés.

V1 interdit sans exception `self VERIFY`, `self REJECT` et `self SUSPEND`, même
avec `compliance.manage`. Le final reviewer est distinct du submitter et du
sujet du profil. Le compliance officer qui a commencé la revue peut décider
seulement s'il respecte ces deux séparations. La séparation financière
requester/approver reste distincte et inchangée.

## 6. Workflow `KycDocument` et pièces requises

```text
UPLOADED --accept--> ACCEPTED --expire--> EXPIRED
     `--reject--> REJECTED
ACCEPTED --invalidate--> REJECTED
```

Un fichier rejeté n'est jamais écrasé. Un remplacement crée une nouvelle ligne
`kyc_documents`; la pièce antérieure et sa décision restent consultables selon
la politique de rétention. En V1, `type` est le type logique et chaque ligne
est une version immuable. Une relation explicite de remplacement
(`supersedes_kyc_document_id`) n'est envisagée que si l'interface ne peut pas
déterminer de manière non ambiguë la version valide la plus récente.

| FROM | TO | ACTOR | PRECONDITIONS | REASON REQUIRED | AUDIT EVENT |
|---|---|---|---|---|---|
| — | `UPLOADED` | Sujet autorisé | Pipeline sécurité minimum | Non | `DOCUMENT_UPLOADED` |
| `UPLOADED` | `ACCEPTED` | Compliance | Contrôles document réussis | Non | `DOCUMENT_ACCEPTED` |
| `UPLOADED` | `REJECTED` | Compliance | Revue | Oui | `DOCUMENT_REJECTED` |
| `ACCEPTED` | `EXPIRED` | Système ou compliance | Date ou règle d'expiration validée | Oui si humain | `DOCUMENT_EXPIRED` |
| `ACCEPTED` | `REJECTED` | Compliance | Fraude, falsification, invalidation émetteur, erreur de revue ou autre signal documenté | Oui | `DOCUMENT_REJECTED` |

L'acceptation et le rejet sont réservés à compliance. Le document binaire
historique reste conservé selon politique de rétention. Un document correctif
crée une nouvelle ligne/version. Aucun état antérieur n'est écrasé. Chaque
transition crée un événement immuable dans la même transaction.

Matrice V1 proposée, fondée uniquement sur les types actuels :

| Sujet | Minimum | Optionnel / conditionnel | Expiration |
|---|---|---|---|
| `User` | `IDENTITY_DOCUMENT` **ou** `PASSPORT` | `PROOF_OF_ADDRESS` selon risque ou destination payout | date du document si fournie ; politique de renouvellement à décider. |
| `Organization` | `REGISTRATION_DOCUMENT` | identité du représentant, `REPRESENTATION_PROOF` si l'autorité n'est pas établie autrement | selon document. |
| `Beneficiary` individuel | `IDENTITY_DOCUMENT` **ou** `PASSPORT` | `PROOF_OF_ADDRESS` selon risque/payout | selon document. |
| `Beneficiary` organisation | `REGISTRATION_DOCUMENT` et identité d'un représentant autorisé | `REPRESENTATION_PROOF` si nécessaire | selon document. |

Les types existants ne distinguent pas encore statuts, bénéficiaires effectifs,
attestations fiscales ou preuve bancaire : ce sont des gaps explicites, à
évaluer avant tout nouvel enum. La période légale de conservation et les règles
de validité ne sont pas décidées ici.

## 7. Audit immutable et état courant

Le profil reste la projection rapide de l'état courant. Les futures colonnes
possibles sont `submitted_at`, `submitted_by_user_id`, `review_started_at`,
`current_reviewer_user_id`, `reviewed_at`, `reviewed_by_user_id`,
`verified_at`, `expires_at`, `suspended_at`, `rejection_reason`,
`suspension_reason` et `risk_level`. Elles ne remplacent jamais l'historique.

La décision V1 est une table centrale `kyc_review_events`, plutôt que deux
tables d'historique dès le départ. `entity_type` distingue explicitement
`PROFILE` et `DOCUMENT`. `kyc_profile_id` reste toujours présent ;
`kyc_document_id` est obligatoire lorsque `entity_type = DOCUMENT` et null
lorsque `entity_type = PROFILE`.

```text
id, public_id, entity_type, kyc_profile_id, kyc_document_id nullable,
event_type, from_status nullable, to_status nullable,
risk_level_before nullable, risk_level_after nullable,
reason_code nullable, reason_text nullable, actor_type, actor_user_id nullable,
metadata jsonb nullable, created_at
```

`metadata` est strictement allowlisté et ne contient ni objet de stockage, ni
document, ni secret, ni payload fournisseur. `actor_type` vaut au minimum
`HUMAN` ou `SYSTEM` ; `actor_user_id` est obligatoire pour `HUMAN` et nullable
seulement pour une action système identifiable, telle qu'une expiration
automatique. La table n'a pas `updated_at`.

`kyc_review_events` est append-only. La logique métier interdit `UPDATE` et
`DELETE`. L'implémentation future doit ajouter des triggers PostgreSQL bloquant
`UPDATE` et `DELETE`, analogues aux garanties ledger, hors maintenance
exceptionnelle explicitement contrôlée. Application et base PostgreSQL portent
cette immutabilité.

Les événements minimaux sont `PROFILE_CREATED`, `SUBMITTED`, `REVIEW_STARTED`,
`VERIFIED`, `REJECTED`, `SUSPENDED`, `EXPIRED`, `REOPENED`, `RISK_CHANGED`,
`DOCUMENT_ACCEPTED`, `DOCUMENT_REJECTED` et `DOCUMENT_EXPIRED`.

La mutation du statut, du reviewer, du risque ou du document et l'insertion de
l'événement sont atomiques dans une transaction PostgreSQL unique. Chaque
changement de risque écrit `RISK_CHANGED` avec avant/après, actor, motif,
timestamp et `ruleset_version` si une analyse automatisée l'a recommandé. Un
risque modifié peut déclencher revue ou suspension seulement par décision métier
explicite. Les
notifications ou intégrations partent ensuite par transactional outbox avec un
payload minimal ; l'outbox n'est pas l'historique compliance.

## 8. Risk et Trust

Le `KycRiskLevel` existant (`UNKNOWN`, `LOW`, `MEDIUM`, `HIGH`) reste l'unique
enum de risque V1. Seul compliance peut le modifier ; un code/motif et
`RISK_CHANGED` sont obligatoires. En V1, l'attribution est manuelle.

Un moteur futur peut recommander un niveau à partir de signaux versionnés, mais
ne le change pas silencieusement et ne rend pas une décision terminale seul.

Pour le Trust Campaign, les options sont :

| Option | Avantage | Limite |
|---|---|---|
| Champ `trust_status` sur `Campaign` | Très simple | Mélange des dimensions et faible auditabilité. |
| Table `trust_decisions` | Historique et règles multiples extensibles | Complexité prématurée V1. |
| Workflow Campaign existant | Réutilise modération/publication et conserve la séparation KYC/Campaign | Doit recevoir un audit explicite si ses décisions deviennent compliance critiques. |

La recommandation V1 est l'option 3 : le workflow Campaign reste l'autorité
de Campaign Trust. Le KYC est un prédicat explicite de politique, pas un
substitut au statut Campaign. Une table `trust_decisions` ne sera introduite
que lorsqu'il faudra porter plusieurs décisions indépendantes et historisées.

## 9. Gating Campaign proposé

Le comportement actuel ne comporte aucun gating KYC ; le tableau suivant est
une cible V1, non une description de l'application actuelle.

| Action | Sujet KYC contrôlé | Statut KYC minimum | Trust requis | Raison |
|---|---|---|---|---|
| Créer / éditer un brouillon | Aucun | Aucun | Aucun | Ne pas bloquer l'amorçage. |
| Soumettre | Owner et, si distinct, bénéficiaire économique déterminés par politique | Profil initié : `SUBMITTED`, `UNDER_REVIEW` ou `VERIFIED`, seulement si la politique de catégorie l'exige | Soumission Campaign normale | Dossier identifiable sans confondre soumission et vérification. |
| Revoir / publier | Sujet(s) applicables selon catégorie | `VERIFIED` lorsque la politique l'exige | Décision Campaign indépendante | KYC vérifie l'identité, la modération vérifie la campagne. |
| Collecter | Aucun statut global automatique | Dépend de la politique publiée | Campaign publiable/active | Ne pas introduire de blocage implicite des dons. |
| Suspendre / fermer | Sujet concerné comme signal | Pas de transition automatique | Décision Campaign explicite | `EXPIRED` ou `SUSPENDED` déclenche une revue, pas une mutation opaque. |

Le sujet applicable dépend du propriétaire et du bénéficiaire réel : une
organisation propriétaire ne substitue pas son KYC à celui d'un bénéficiaire
individuel tiers. Le modèle actuel associe une campagne à un bénéficiaire ; si
un cas futur sans bénéficiaire est introduit, la politique devra désigner
explicitement le sujet économique avant toute décision.

## 10. Gating Payout proposé

Le Payout est plus strict. Un `PayoutRequest` référence explicitement le
**bénéficiaire économique figé** au moment de sa création. Il ne recalcule pas
aveuglément ce bénéficiaire depuis l'état courant de `Campaign` lors de
l'approbation ou de l'exécution.

La future structure porte au minimum `beneficiary_id` ou sujet économique
équivalent et, si l'architecture finale le requiert, une référence contrôlée au
`kyc_profile_id`. Un snapshot métier minimal peut conserver beneficiary public
ID, type de sujet et relation métier pertinente pour expliquer la demande. Il
ne recopie ni nom complet inutile, ni adresse, ni numéro de document, ni
téléphone. Il n'est jamais autorité du statut KYC.

Risque interdit : payout demandé pour Beneficiary A, puis bénéficiaire Campaign
modifié vers B, puis payout exécuté pour B. Le payout reste rattaché à A ; son
KYC courant est revalidé.

`VERIFIED` au moment de `REQUEST` ne garantit ni `APPROVE` ni `EXECUTE`. Avant
chaque étape, le service vérifie profil courant du bénéficiaire figé : profil
existant, `status == VERIFIED`, non expiré, non suspendu, sujet correspondant,
Trust/Risk satisfaits.

| Action payout | Sujet KYC | Statut requis, revalidé à l'étape | Contrôles complémentaires inchangés |
|---|---|---|---|
| `REQUEST` | Bénéficiaire économique figé | Profil existant, `VERIFIED`, non expiré, non suspendu | Solde/réservation, destination approuvée, campagne, devise, limites. |
| `APPROVE` | Même bénéficiaire figé | Même exigence courante, revalidée | `finance_operator != finance_approver`, réservation, risque et permissions. |
| `EXECUTE` | Même bénéficiaire figé | Même exigence courante, revalidée sous contrôle transactionnel juste avant émission | ProviderAccount actif, idempotence, reconciliation, règles finance. |

Cas :

- campagne propriétaire `User`, bénéficiaire individuel : KYC du bénéficiaire ;
  celui du owner ne vaut que s'il est ce bénéficiaire ou si une règle distincte
  l'exige ;
- campagne propriétaire `Organization`, bénéficiaire individuel tiers : KYC
  de l'individu, pas seulement celui de l'organisation ;
- bénéficiaire organisation : KYC de l'organisation/bénéficiaire et autorité
  active du représentant qui opère le payout ;
- organisation propriétaire pour un tiers : KYC du tiers économique.

Scénario obligatoire :

```text
10:00 Payout REQUESTED ; KYC VERIFIED
10:15 Compliance : VERIFIED -> SUSPENDED
10:20 APPROVE demandé : refusé
10:30 EXECUTE demandé : refusé
```

`VERIFIED -> EXPIRED` produit même blocage. `SUSPENDED` ou `EXPIRED` interdit
nouvelle demande, approbation, exécution et nouveau retry provider avant sortie
irréversible. Cela ne modifie pas rétroactivement Payment/don déjà encaissé :
encaissement entrant et Payout sortant restent distincts.

Tout retry provider envisagé avant irréversibilité revalide KYC courant sous
contrôle transactionnel, puis seulement initie retry. Exemple : Payout
`APPROVED`, timeout/`UNKNOWN`, retry envisagé, contrôle KYC, retry éventuel. Si
KYC est entretemps non autorisé, aucun retry ne part. Une opération déjà
irréversiblement exécutée chez provider ne peut pas être annulée
automatiquement ; elle suit reconciliation et procédures financières dédiées.

Le KYC ne remplace ni ledger, ni réservations, ni séparation des rôles, ni
contrôles provider, devise, limites, anti-fraude ou réconciliation.

## 11. Sécurité documentaire et rétention

ADR-008 reste applicable : stockage privé obligatoire, clé générée serveur,
jamais de symlink ni URL publique permanente, ni `object_key` exposé par API.

La future chaîne d'upload doit appliquer : limite de taille et de nombre de
fichiers, MIME, magic bytes, extension cohérente, validation structurelle
PDF/image, hash SHA-256, streaming lorsque nécessaire et protection DoS.

La cible antivirus est :

```text
UPLOAD → QUARANTINE → SCAN → CLEAN ou INFECTED/REJECTED
```

Un futur `scan_status` est à évaluer, sans réutiliser abusivement le statut de
revue humaine. Consultation et téléchargement passent par un contrôleur et une
policy, streaming sécurisé et `Content-Disposition` contrôlé ; aucun chemin
utilisateur n'est accepté. Les accès sensibles doivent être audités.

La durée de conservation, l'archivage, la suppression et les exigences légales
restent des décisions juridiques/compliance à prendre séparément.

## 12. OCR, IA, analyses et confidentialité

L'architecture future dépend d'une abstraction, jamais directement d'OpenAI,
Gemini, Claude, DeepSeek ou d'un modèle local :

```text
KycAiReviewService → KycAiProvider → provider sélectionné
```

Elle peut permettre un provider principal et un secondaire pour second avis ou
fallback. L'IA peut classifier, extraire, comparer, signaler, recommander et
préparer une synthèse. Elle ne peut jamais, seule, vérifier/rejeter/suspendre
un profil, autoriser un payout, supprimer une pièce ou modifier silencieusement
les données déclarées. Le human-in-the-loop est obligatoire.

Les résultats futurs sont conservés dans `kyc_analysis_runs` avec notamment :

```text
id, public_id, kyc_profile_id, kyc_document_id nullable,
analysis_type, provider, model, model_version, prompt_version nullable,
ruleset_version nullable, status, confidence nullable, risk_score nullable,
result_json, started_at, completed_at, created_at
```

`result_json` ne devient jamais duplicata permanent du document KYC. Il interdit
sans nécessité explicite image base64, document ou PDF complet, numéro
CNI/passeport brut, adresse complète, selfie, image faciale, template ou
embedding facial, preuve liveness brute, prompt PII complet et réponse provider
brute contenant toutes les PII.

`kyc_analysis_findings` porte findings structurés, minimisés et versionnés :
code, sévérité, champ, valeurs normalisées/hashées lorsque possible, confidence
et métadonnées allowlistées. Exemple : `NAME_MISMATCH`, `last_name`, `0.94`,
`MEDIUM`. Autres exemples : `DOB_MISMATCH`, `DOCUMENT_EXPIRED`,
`LOW_IMAGE_QUALITY`, `MANUAL_REVIEW_REQUIRED`. Quand valeur est requise pour
audit, préférer valeur normalisée minimale, hash, chiffrement applicatif ou
référence au document source privé.

Les données envoyées au provider sont minimisées au document, type attendu et
champs nécessaires. Ne jamais envoyer par défaut ledger, paiements, campagnes
sans rapport, historique complet ou données d'autres sujets. Prompts et réponses
IA peuvent contenir PII : ne jamais journaliser prompt brut ou réponse brute.
Conserver `prompt_version`, template identifier, provider, modèle/version,
metadata minimale et résultats structurés. Contenu brut ne peut être conservé
que sous justification explicite et politique sécurité/rétention approuvée.
Les appels restent auditables avec provider/version ; secrets restent hors base
et hors logs. Résidence des données et rétention provider restent ouvertes.

## 13. Biométrie future

Face matching et liveness sont deux contrôles différents :

```text
Face matching : photo document ↔ selfie
Liveness      : personne réelle présente au moment du contrôle
```

Selfie, photo visage extraite, face image, face template, face embedding,
données/preuves liveness, anti-spoofing et vidéo biométrique sont hautement
sensibles. Ils restent privés.

Conservation par défaut minimale : provider, modèle/version, analysis/session
public ID, `face_match_score`, résultat, résultat liveness, confidence,
`processed_at` et finding codes. Ne pas conserver durablement par défaut face
embedding, template biométrique, vidéo liveness ou selfie brut dupliqué. Une
session/évidence biométrique porte provider spécialisé, moteur/version, date,
score, statut et audit. Le LLM généraliste n'est pas moteur biométrique
principal. Résultats sont signaux de revue, non décisions autonomes. Si provider
conserve temporairement artefact, politique future définit finalité, durée,
lieu, suppression, base juridique et accès.

## 14. Concurrence, API et erreurs

Les services futurs suivent :

```text
DB::transaction
SELECT KycProfile FOR UPDATE
validation transition et actor/policies
modification état
insert kyc_review_event
COMMIT
```

Une soumission répétée est idempotente ou reçoit `409`. Deux compliance officers
ne peuvent pas assigner ou décider contradictoirement. Exemple Reviewer A
`VERIFIED`, Reviewer B `REJECTED` : une transition gagne ; autre reçoit `409`
ou erreur contrôlée, jamais erreur SQL brute. Acceptation documentaire et
affectation revue restent atomiques.

API cible, sans implémentation :

```text
POST /api/v1/kyc/profiles/{profile}/submit
POST /api/v1/kyc/profiles/{profile}/review/start
POST /api/v1/kyc/profiles/{profile}/verify
POST /api/v1/kyc/profiles/{profile}/reject
POST /api/v1/kyc/profiles/{profile}/suspend
POST /api/v1/kyc/profiles/{profile}/reopen
GET  /api/v1/kyc/profiles/{profile}/documents
GET  /api/v1/kyc/documents/{document}
GET  /api/v1/kyc/documents/{document}/content
POST /api/v1/kyc/documents/{document}/accept
POST /api/v1/kyc/documents/{document}/reject
GET  /api/v1/compliance/kyc/profiles
GET  /api/v1/compliance/kyc/profiles/{profile}/history
```

Routes exposées utilisent UUID/public ID, jamais ID numérique interne. Sujets
autorisés consultent/soumettent selon policy ; seules personnes
`compliance.manage` voient queue, prennent en charge et décident. L'API renvoie
`401`, `403`, `404`, `409`, `422` et éventuellement `503` selon sémantique
normale. Elle n'expose jamais SQL, stack trace, chemin, objet, `object_key`,
secret provider ou contenu PII brut. Document content passe uniquement par
contrôleur autorisé.

## 15. Outbox et UI compliance

Les statuts importants produisent ultérieurement un événement outbox minimal,
écrit avec la mutation : `KYC_SUBMITTED`, `KYC_VERIFIED`, `KYC_REJECTED`,
`KYC_SUSPENDED`, `KYC_EXPIRED` et `KYC_RISK_CHANGED`. Usages : notification,
dashboard, blocage contrôlé de payout et orchestration de traitement ; ils ne
remplacent pas `kyc_review_events`.

La future UI compliance requiert une queue `SUBMITTED`/`UNDER_REVIEW`, filtres
par sujet, risque, statut documentaire, reviewer, date, expiration et flags
d'analyse ; vue autorisée des documents privés ; comparaison déclaré/extrait ;
findings, biométrie, historique, affectation et actions de décision.

## 16. Migrations et tests futurs envisagés

À concevoir avant écriture :

1. enrichissement contrôlé de `kyc_profiles` pour l'état courant ;
2. `kyc_review_events` immutable avec `entity_type`, FK document conditionnelle,
   `actor_type` et protections PostgreSQL ;
3. enrichissement éventuel de `kyc_documents` (review/scan/remplacement), sans
   effacer l'historique ;
4. plus tard seulement, `kyc_analysis_runs`, `kyc_analysis_findings` et une
   structure biométrique dédiée si le besoin est validé ;
5. référence stable du bénéficiaire économique sur Payout, lors de phase finance ;
6. aucune table `trust_decisions` V1 tant que le workflow Campaign suffit.

Les tests devront couvrir XOR/unicité, toutes les transitions autorisées et
interdites, séparation submitter/reviewer, immutabilité audit, concurrence,
policies, téléchargement privé, absence de fuite `object_key`/PII, documents
versionnés, expiration, outbox transactionnelle, gating Campaign et surtout
Payout sur PostgreSQL réel. Tests Payout couvrent changement Campaign A vers B,
`VERIFIED -> SUSPENDED`/`EXPIRED` entre request/approve/execute, retry provider
après `UNKNOWN` et absence de sortie nouvelle lorsque KYC n'est plus valide.

## 17. Phasage recommandé

| Phase | Portée |
|---|---|
| 10A3 | Machine d'état `KycProfile`, `kyc_review_events`, services, policies, API et tests PostgreSQL. |
| 10A4 | Workflow documentaire, versionnement, accès privé contrôlé, revue et interface de quarantaine/scan. |
| 10A5 | Gating Payout par bénéficiaire économique, avec tests finance/KYC. |
| 10A6 | Politique Campaign Trust, queue compliance, affectation, filtres et historique. |
| 10A7 | Abstraction OCR/document intelligence, analyses et findings versionnés. |
| 10A8 | IA multi-provider, règles de risque explicables et revue humaine obligatoire. |
| 10A9 | Sessions biométriques, face matching et liveness via fournisseur spécialisé. |

Chaque phase doit réinspecter les migrations, modèles, policies, finance et
tests réels avant modification. Aucune ne doit présumer que l'enum existant est
déjà un workflow effectif.

## 18. Hors périmètre de cette décision

Cet ADR ne crée ni code, migration, endpoint, provider, appel externe,
antivirus, Object Storage, frontend compliance, Flutter KYC, score automatique,
auto-approval ou gating Payout actif. Il ne modifie aucune logique Wave.

## 19. Décisions ouvertes

Restent à valider par architecture, sécurité, finance, juridique et compliance :

- politique légale de rétention KYC et exigences Sénégal/UEMOA ;
- région de stockage ;
- provider OCR, IA principal et biométrique ;
- durée de validité KYC ;
- seuils face matching et liveness ;
- règles risk scoring ;
- auto-approval futur ;
- règles fines de publication Campaign ;
- stratégie exacte de chiffrement de certaines PII ;
- durée de conservation des artefacts biométriques ;
- documents organisationnels additionnels et règles de bénéficiaires effectifs.

Ne sont plus ouvertes : profil stable par sujet, audit immutable, self-review
interdit, human-in-the-loop, face matching distinct de liveness, bénéficiaire
Payout stable, revalidation KYC `REQUEST`/`APPROVE`/`EXECUTE`, revalidation des
retries provider, `result_json` minimisé, embeddings non permanents par défaut
et `ACCEPTED -> REJECTED` documentaire sous conditions.

## 20. Conséquences

La plateforme obtient une cible auditable et extensible sans confondre identité,
analyse, risque, modération et argent. Le coût est l'ajout progressif de
services transactionnels, policies, protections PostgreSQL et tests de sécurité
avant d'activer chaque capacité. Aucune opération financière ne doit être
autorisée par un navigateur, une IA ou un seul statut Campaign.
