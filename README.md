# RAG Document Assistant — Laravel API + Next.js Frontend

> Upload PDF/DOCX/TXT/CSV → async ingestion (parse, chunk, embed, index) → ask in natural language → get grounded answers with citations. Single-user portfolio demo built with Laravel 13 + pgvector + OpenAI embeddings + Groq generation + Next.js 15.

Portfolio companion to **FreelanceScope** — demonstrates backend (REST, async queues, vector search) + AI integration (embeddings, RAG, grounded generation).

---

## Architecture

```
Next.js (App Router + Tailwind)          Laravel 13 API (PHP 8.3)
  UploadForm  ──POST /api/documents──► DocumentController (thin)
  DocumentList ◄──GET /api/documents──  StoreDocumentRequest → 202 + dispatch
  ChatWindow  ──POST /api/query──────► QueryController → Retriever → AnswerGenerator
       │                                  │           │           │
       │          Queue Worker            │   DocumentParser  EmbeddingService
       │◄── poll status ─────────────     │   TextChunker     (OpenAI)
       │                                  │   IngestDocument (ShouldQueue) ──► Postgres + pgvector
       └──────────────────────────────────┘                              │   documents / chunks (vector 1536)
                                                                          └──► Groq Llama 3.3 70b
```

**Flow:**
- **Ingestion:** `Frontend → POST /documents → store + Document(pending) → IngestDocument Job (processing) → parse → chunk (800/150, split \n\n/\n/. /space) → OpenAI `text-embedding-3-small` → INSERT `?::vector` → Document(ready|failed)`
- **Query:** `Frontend → POST /query → embed question → pgvector <=> topK=4 (1 - distance = cosine score) → build context [n] (source: filename) → Groq (temperature 0.2, system: grounded-only) → {answer, sources[]}`

---

## Stack

| Layer | Choice | Why |
|---|---|---|
| API | Laravel 13 | Main stack, thin controllers + DI |
| DB / Vector | PostgreSQL + `pgvector` | No extra service, native SQL, RDS-compatible |
| Embeddings | OpenAI `text-embedding-3-small` (1536 dims) | Groq has no embeddings endpoint |
| Generation | Groq `llama-3.3-70b-versatile` | Fast, already used in FreelanceScope |
| Async | Laravel Queues (database or Redis) | Ingestion must not block HTTP |
| Parsing | `smalot/pdfparser` (PDF), `phpoffice/phpword` (DOCX), native PHP (TXT/CSV) | Standard Laravel ecosystem |
| Frontend | Next.js 15 App Router + Tailwind | Upload UI + chat, `useState` only |
| Ops | Docker, Terraform (EC2 + RDS), GitHub Actions | Matches portfolio infra |

---

## Project Structure

```
Rag_project/
├── openspec/                      # Source of truth (OpenSpec — replaces Docs/)
│   ├── config.yaml                # Stack + conventions + constraints
│   ├── specs/                     # Merged specs after archive (empty until first archive)
│   └── changes/
│       ├── rag-foundation/        # DB + pgvector + models + config
│       │   ├── proposal.md
│       │   ├── design.md
│       │   ├── tasks.md
│       │   └── specs/{db-schema,vector-storage}/spec.md
│       ├── rag-ingestion/         # Upload + parse + chunk + embed + queue
│       │   └── specs/{document-upload,document-parsing,text-chunking,ingestion-pipeline}/spec.md
│       ├── rag-retrieval/         # Search + generation + POST /query
│       │   └── specs/{vector-search,answer-generation,query-api}/spec.md
│       └── rag-frontend/          # Next.js App Router + lib/api + components
│           └── specs/{frontend-upload,frontend-chat}/spec.md
├── rag-laravel-nextjs-guide.md    # Original technical guide (code snippets, reference)
├── cahier-des-charges-rag.md      # Functional spec (French, F1-F8, NFRs)
├── Rag.pdf                        # Original brief
└── README.md                      # This file
```

**Future Laravel layout (to be scaffolded):**
```
rag-api/
├── app/
│   ├── Http/{Requests/{StoreDocumentRequest,QueryRequest}, Controllers/{DocumentController,QueryController}, Resources/{DocumentResource,QueryResource}}
│   ├── Services/{DocumentParser, TextChunker, EmbeddingService, Retriever, AnswerGenerator}
│   ├── Jobs/IngestDocument.php
│   └── Models/{Document, Chunk}.php
├── database/migrations/*_create_documents/chunks_table.php
├── config/services.php  # openai.key, groq.key/model from env()
├── routes/api.php       # POST/GET/DELETE /documents, POST /query
└── storage/app/uploads/
rag-frontend/
├── app/{page.tsx, chat/page.tsx, components/{UploadForm,DocumentList,ChatWindow}.tsx}
├── lib/api.ts
└── .env.local
```

**Laravel best practices enforced:**
- **FormRequests** own validation (`required|file|mimes:pdf,docx,txt,csv|max:20480` for upload; `required|string|max:2000` for query) — controllers contain zero `validate()` calls
- **Services** are stateless, DI-bound, SRP (Parser only parses, Chunker only chunks, etc.)
- **Jobs** (`ShouldQueue`) own async work; `pending→processing→ready/failed` — never in controller
- **Controllers** are thin: `Request → store/dispatch → Resource` or `Request → Retriever → AnswerGenerator → Resource`
- **Resources** shape all JSON (`id,filename,status,created_at` / `answer,sources[]`)

---

## Specs (OpenSpec)

This project is **spec-driven** via [OpenSpec](https://github.com/Fission-AI/OpenSpec).

| Change | Capabilities | Key Requirements |
|---|---|---|
| `rag-foundation` | `db-schema`, `vector-storage` | `documents(status pending|processing|ready|failed)`, `chunks(FK cascade, vector(1536), ivfflat vector_cosine_ops)`, `CREATE EXTENSION vector`, reversible migrations |
| `rag-ingestion` | `document-upload`, `document-parsing`, `text-chunking`, `ingestion-pipeline` | `POST 202 + GET + DELETE 204`, `DocumentParser(match ext)`, `TextChunker(800/150, split-then-merge invariant)`, `EmbeddingService(batch)`, `IngestDocument` queue |
| `rag-retrieval` | `vector-search`, `answer-generation`, `query-api` | `Retriever(<=> topK=4, score 0..1)`, `AnswerGenerator(context [n] (source: filename), fallback "No documents…", temp 0.2)`, `POST /query → {answer, sources}` |
| `rag-frontend` | `frontend-upload`, `frontend-chat` | `lib/api.ts (FETCH + env NEXT_PUBLIC_API_URL)`, `UploadForm(poll 2s)`, `DocumentList(badges+delete)`, `ChatWindow(useState, citations)` |

**OpenSpec workflow:**
```bash
npm install -g @fission-ai/openspec@latest
openspec init --tools opencode   # already done in this repo

# Propose → Apply → Archive per change (in dependency order):
# rag-foundation → rag-ingestion → rag-retrieval → rag-frontend

# In OpenCode chat:
/opsx-propose rag-foundation   # already created; review proposal/design/specs/tasks
/opsx-apply                    # implements tasks.md checklist
/opsx-archive                  # merges delta specs into openspec/specs/ and archives change

# Check status:
openspec status --change rag-foundation
```

Detailed requirements with `SHALL` + `Given/When/Then` scenarios live in `openspec/changes/*/specs/*/spec.md`. After archive they merge into `openspec/specs/*/spec.md`.

---

## API Contract

| Method | Path | Request | Response |
|---|---|---|---|
| `POST` | `/api/documents` | `multipart/form-data: file (pdf,docx,txt,csv, ≤20MB)` | `202 {data:{id,filename,status:"pending",created_at}}` |
| `GET` | `/api/documents` | — | `200 {data:[{id,filename,status,created_at}]}` DESC |
| `DELETE` | `/api/documents/{id}` | — | `204` + cascade delete chunks + stored file |
| `POST` | `/api/query` | `{"question":"string ≤2000"}` | `200 {answer:string, sources:[{content,filename,score}]}` |

**Errors:** `422` validation (invalid mime/oversize/missing question), `500` on job failure sets document `failed` without blocking others (F2 reliability).

---

## Getting Started (Local)

### Prerequisites
- PHP 8.3 + Composer, Node 20.19+, Postgres 15+ with `pgvector` (or Docker `pgvector/pgvector:pg16`), Redis optional (or use `database` queue)

### 1. Backend (Laravel)
```bash
composer create-project laravel/laravel rag-api
cd rag-api
composer require smalot/pdfparser phpoffice/phpword guzzlehttp/guzzle

# .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rag
OPENAI_API_KEY=sk-...
GROQ_API_KEY=gsk_...
GROQ_MODEL=llama-3.3-70b-versatile
QUEUE_CONNECTION=database # or redis
NEXT_PUBLIC_API_URL=http://localhost:8000/api  # CORS allow http://localhost:3000

# DB
psql -c "CREATE EXTENSION IF NOT EXISTS vector;"
php artisan queue:table && php artisan migrate
php artisan queue:work   # separate terminal/container
php artisan serve        # http://localhost:8000
```

### 2. Frontend (Next.js)
```bash
npx create-next-app@latest rag-frontend --typescript --tailwind --app
cd rag-frontend
echo "NEXT_PUBLIC_API_URL=http://localhost:8000/api" > .env.local
npm run dev              # http://localhost:3000
```

### 3. Verify
```bash
# Upload
curl -F file=@sample.pdf http://localhost:8000/api/documents  # → 202 {status:pending}
# Poll until ready
curl http://localhost:8000/api/documents
# Query
curl -X POST http://localhost:8000/api/query -H "Content-Type: application/json" -d '{"question":"What is X?"}'
# → {answer:"... [1]", sources:[{filename:"sample.pdf", score:0.87}]}

# Manual vector check (psql)
psql -c "SELECT 1 - (embedding <=> '[...]'::vector) AS score FROM chunks LIMIT 1;"
```

---

## Non-Functional & Constraints

- **Performance:** Query <5s in normal conditions
- **Reliability:** Failed ingestion → `failed` without blocking other documents/jobs
- **Security:** Strict mime/size validation, keys only via `env()`/`config()`, never hardcoded, rate limiting planned for public demo
- **Portability:** Docker Compose (api + worker + postgres+pgvector + frontend), Terraform EC2+RDS, queue worker as own container
- **Out of scope v1:** Multi-user auth, editing indexed docs, image/audio/video, persistent chat history

---

## Implementation Order (Suggested)

1. `rag-foundation`: migrations + pgvector smoke test (manual vector insert/query)
2. `rag-ingestion`: Parser + Chunker unit tests isolated
3. `rag-ingestion`: EmbeddingService + `IngestDocument` → `pending→ready` with real file
4. `rag-retrieval`: `Retriever` + `AnswerGenerator` via `Http::fake` → `curl POST /query`
5. `rag-frontend`: UploadForm → `POST /documents`
6. `rag-frontend`: ChatWindow → `POST /query` + citation rendering
7. Polish: loading spinners, error alerts, source truncation/expand, CORS, rate limit

---

## Validation Checklist

- [ ] PDF/DOCX/TXT/CSV uploads → `ready`
- [ ] Question on indexed content → correct grounded answer + cited `filename [n]`
- [ ] Off-topic question → `don't know` / `No documents…`, no hallucination
- [ ] DELETE document → chunks + file removed (`SELECT count(*) FROM chunks WHERE document_id=?` is 0)
- [ ] `docker compose up` runs end-to-end

---

## References

- Technical guide with code snippets: `rag-laravel-nextjs-guide.md`
- Functional spec (French, F1-F8, NFRs, planning, risks): `cahier-des-charges-rag.md`
- Specs source of truth: `openspec/changes/*` (pre-archive) → `openspec/specs/*` (post-archive)

Feedback on OpenSpec setup: https://github.com/Fission-AI/OpenSpec/issues
