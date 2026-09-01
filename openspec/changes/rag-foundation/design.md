## Context

Greenfield Laravel 13 API + Postgres. No tables yet, pgvector not enabled. This change must land first — all ingestion/retrieval depends on documents/chunks schema. Stakeholders: portfolio demo, recruiter review. Constraints: must run on RDS PG15+ or local Docker `pgvector/pgvector`, no extra microservices, embeddings dimension locked to 1536 for `text-embedding-3-small`.

## Goals / Non-Goals

**Goals:**
- Proven pgvector extension + migrations runnable via `php artisan migrate`
- Thin Eloquent models with relations, FK cascade, scopes for status
- Env-driven config for OpenAI/Groq, no secrets in repo
- Docker baseline verified by inserting/querying a vector by hand in `psql`

**Non-Goals:**
- No parsing/chunking/embedding logic (next change)
- No API endpoints, FormRequests, Resources, Jobs
- No Next.js frontend
- No auth/multi-tenancy (v1 single user)

## Decisions

- **Decision: Postgres + pgvector over Pinecone/Qdrant** — Rationale: native SQL, no extra service, fits Terraform EC2+RDS portfolio pattern `rag-laravel-nextjs-guide.md:10`. Alternative: managed vector DB — rejected cost + ops overhead. Tradeoff: `ivfflat` needs data before building optimal lists; acceptable for demo scale.
- **Decision: Raw `DB::statement` for `vector(1536)` column** — pgvector has no native Laravel column type `rag-laravel-nextjs-guide.md:65`. Alternative: third-party blueprint macro — adds dependency for one line.
- **Decision: `documents.status` as string enum (pending|processing|ready|failed) with default pending** — avoids native Postgres enum complexity, easy Eloquent casting. Alternative: native enum — harder migration rollback.
- **Decision: `chunks.embedding vector(1536)` + `ivfflat vector_cosine_ops`** — matches embedding model dimension; cosine via `<=>` for retrieval. Alternative: `hnsw` — faster but requires PG16+; keep `ivfflat` for broader RDS support.
- **Decision: Config in `config/services.php` with `env()`** — Laravel convention, testable via `config()`. Separate `openai.key` + `groq.key/model` (`llama-3.3-70b-versatile` default).

## Risks / Trade-offs

- [RDS pgvector unavailable on older engine] → Mitigation: check `SELECT * FROM pg_available_extensions WHERE name='vector'` before provisioning; fallback to EC2 self-hosted PG16+ Docker.
- [Dimension mismatch if embedding model changes] → Mitigation: document 1536 constant in migration comment + service; changing model requires new migration `ALTER TABLE chunks ALTER COLUMN embedding TYPE vector(N)`.
- [ivfflat index needs ANALYZE and data] → Mitigation: create index after migration, run `ANALYZE chunks` post-seed; acceptable recall for <100k vectors.
- [FK cascade deletes large chunk sets] → Mitigation: `cascadeOnDelete()` is intentional (F4 `cahier-des-charges-rag.md:74`); use queued deletes if needed later.

## Migration Plan

1. `CREATE EXTENSION IF NOT EXISTS vector;` (DB superuser one-time, included in migration guard via `DB::statement`)
2. `php artisan migrate` creates `documents` then `chunks` + raw vector column + index
3. Rollback: `php artisan migrate:rollback` drops chunks index then tables (down methods reverse order)
4. Verify: `psql -c "\d chunks"` shows `vector(1536)`, `\dx` shows `vector`
5. Smoke test: manual insert `'[0,0,...]'::vector` and `SELECT * FROM chunks ORDER BY embedding <=> '[...]' LIMIT 1`

## Open Questions

- Which Postgres image/tag for local Docker Compose? Default `pgvector/pgvector:pg16` vs `ankane/pgvector` — decide in Docker change.
- Need `ulid`/`uuid` for documents? Stay `bigIncrements id` per spec — revisit if needed for public URLs.
