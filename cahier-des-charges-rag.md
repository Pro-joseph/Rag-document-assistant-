# Cahier des charges — Système RAG (Laravel + Next.js)

## 1. Présentation du projet

**Nom du projet** : RAG Document Assistant *(nom provisoire à définir)*

**Type** : Projet portfolio personnel

**Objectif général** : Développer une application permettant à un
utilisateur de déposer des documents (PDF, DOCX, TXT, CSV), de les
faire analyser et indexer automatiquement, puis de poser des questions
en langage naturel et d'obtenir des réponses fondées uniquement sur le
contenu de ces documents, avec citation des sources.

**Contexte** : Projet destiné à démontrer une double compétence
backend (Laravel, API REST, traitement asynchrone) et intégration IA
(embeddings, recherche vectorielle, génération augmentée), en
complément de FreelanceScope dans le portfolio.

---

## 2. Objectifs

- Permettre l'ingestion de documents hétérogènes (PDF, DOCX, TXT, CSV)
- Indexer le contenu sous forme vectorielle pour permettre une
  recherche sémantique
- Répondre aux questions de l'utilisateur en s'appuyant uniquement sur
  les documents fournis (pas de connaissance générale du modèle)
- Fournir les sources utilisées pour chaque réponse (traçabilité)
- Offrir une interface simple d'upload et de chat

**Hors périmètre (v1)** :
- Authentification multi-utilisateurs / gestion de comptes
- Édition ou modification des documents une fois indexés
- Support de formats supplémentaires (images, audio, vidéo)
- Historique de conversation persistant entre sessions

---

## 3. Utilisateurs cibles

| Profil | Besoin |
|---|---|
| Utilisateur unique (démo portfolio) | Déposer des documents et interroger leur contenu rapidement |
| Recruteur / évaluateur technique | Comprendre l'architecture et voir la démo fonctionner en direct |

---

## 4. Architecture technique

| Composant | Choix | Justification |
|---|---|---|
| API backend | Laravel 13 | Stack principal du développeur |
| Frontend | Next.js (App Router) + Tailwind | Interface d'upload et de chat |
| Stockage vectoriel | PostgreSQL + extension `pgvector` | Pas de service tiers additionnel, compatible RDS/EC2 |
| Embeddings | OpenAI `text-embedding-3-small` | Groq ne propose pas d'endpoint d'embeddings |
| Génération de réponses | Groq (Llama 3.x) | Déjà utilisé sur FreelanceScope, rapide |
| Traitement asynchrone | Laravel Queues | L'ingestion ne doit pas bloquer la requête HTTP |
| Parsing de fichiers | `smalot/pdfparser`, `phpoffice/phpword`, parsing natif PHP | Écosystème Laravel standard |

Schéma de flux : Frontend → API Laravel → (Queue Worker → Embeddings →
Postgres) pour l'ingestion, et API Laravel → Embeddings → Postgres →
Groq → Frontend pour l'interrogation.

---

## 5. Spécifications fonctionnelles

### 5.1 Gestion des documents

| ID | Fonctionnalité | Description |
|---|---|---|
| F1 | Upload de document | L'utilisateur dépose un fichier PDF/DOCX/TXT/CSV (max 20 Mo) |
| F2 | Suivi du statut | Le document affiche un statut : `pending` → `processing` → `ready` / `failed` |
| F3 | Liste des documents | L'utilisateur voit tous les documents indexés avec leur statut |
| F4 | Suppression | L'utilisateur peut supprimer un document et ses chunks associés |

### 5.2 Interrogation (chat)

| ID | Fonctionnalité | Description |
|---|---|---|
| F5 | Poser une question | L'utilisateur saisit une question en langage naturel |
| F6 | Réponse fondée sur les documents | La réponse s'appuie uniquement sur le contenu indexé, jamais sur des connaissances générales du modèle |
| F7 | Citation des sources | Chaque réponse indique le(s) document(s) source(s) utilisé(s) |
| F8 | Gestion de l'absence de contexte | Si aucun document pertinent n'est trouvé, l'assistant l'indique clairement plutôt que d'inventer une réponse |

---

## 6. Spécifications techniques

### 6.1 Endpoints API

```
POST   /api/documents        Upload d'un fichier, déclenche l'ingestion asynchrone
GET    /api/documents         Liste des documents et de leur statut
DELETE /api/documents/{id}    Suppression d'un document et de ses chunks
POST   /api/query             Question -> réponse + sources
```

### 6.2 Modèle de données

**documents** : `id`, `filename`, `status`, `created_at`, `updated_at`

**chunks** : `id`, `document_id` (FK), `chunk_index`, `content`,
`embedding` (vector 1536), `created_at`, `updated_at`

### 6.3 Traitement de l'ingestion

1. Parsing du fichier selon son extension
2. Découpage du texte en chunks (~800 caractères, chevauchement ~150
   caractères, découpage sur les frontières paragraphe/phrase)
3. Génération des embeddings pour chaque chunk
4. Insertion en base avec l'embedding au format `vector`

### 6.4 Traitement de la requête

1. Génération de l'embedding de la question
2. Recherche des chunks les plus proches par similarité cosinus
   (opérateur `<=>` de pgvector)
3. Construction du prompt avec le contexte récupéré
4. Appel à l'API Groq pour générer la réponse
5. Retour de la réponse et des sources au frontend

---

## 7. Exigences non fonctionnelles

| Catégorie | Exigence |
|---|---|
| Performance | Réponse à une question en moins de 5 secondes en conditions normales |
| Fiabilité | Une ingestion échouée doit passer le document en statut `failed` sans bloquer les autres |
| Sécurité | Validation stricte du type et de la taille des fichiers uploadés ; clés API stockées en variables d'environnement, jamais en dur |
| Scalabilité | Le worker de queue doit pouvoir tourner en processus séparé (conteneur dédié) |
| Portabilité | Déploiement via Docker, provisioning via Terraform (cohérent avec le reste du portfolio) |

---

## 8. Livrables attendus

- Dépôt Git du backend Laravel (API, migrations, services, jobs)
- Dépôt Git du frontend Next.js
- Documentation technique (README avec instructions d'installation)
- Docker Compose pour lancement local
- Démo fonctionnelle déployée (EC2, aligné avec l'infrastructure existante)

---

## 9. Planning indicatif (par étapes)

1. Backend : migrations + extension pgvector opérationnelle
2. Backend : services de parsing et de chunking (testés isolément)
3. Backend : embeddings + job d'ingestion asynchrone
4. Backend : recherche vectorielle + génération de réponse
5. Frontend : formulaire d'upload + liste des documents
6. Frontend : interface de chat
7. Finitions : gestion des erreurs, états de chargement, affichage des sources
8. Déploiement (Docker, EC2, CI/CD)

---

## 10. Critères de validation

- [ ] Un document PDF/DOCX/TXT/CSV peut être uploadé et passe bien au statut `ready`
- [ ] Une question sur le contenu d'un document renvoie une réponse correcte avec la bonne source citée
- [ ] Une question sans rapport avec les documents indexés ne produit pas de réponse inventée
- [ ] La suppression d'un document supprime bien tous ses chunks associés
- [ ] L'application tourne de bout en bout via `docker compose up`

---

## 11. Risques identifiés

| Risque | Impact | Mitigation |
|---|---|---|
| Coût des appels API (OpenAI/Groq) en cas de démo publique | Facturation imprévue | Limiter la taille des fichiers, prévoir un rate limiting côté API |
| Qualité du chunking pour des PDF mal structurés | Réponses imprécises | Tester sur des documents réels variés avant la démo |
| Disponibilité de l'extension pgvector selon l'hébergeur | Blocage du déploiement | Vérifier la compatibilité RDS/version Postgres avant provisioning |
