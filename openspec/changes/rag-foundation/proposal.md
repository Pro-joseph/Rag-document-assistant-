# Proposal: rag-foundation

## Why

No portable foundation exists — need DB + vector storage + models + config before any ingestion/retrieval can run. This unblocks all later RAG features and proves pgvector on RDS/EC2.

## What Changes

- Enable PostgreSQL `pgvector` extension (`CREATE EXTENSION IF NOT EXISTS vector`)
- Migration `documents`: `id`, `filename`, `status` (pending|processing|ready|failed, default pending), `timestamps`
- Migration `chunks`: `id`, `document_id` FK cascade, `chunk_index`, `content` text, `embedding vector(1536)`, `timestamps` + `ivfflat` index `vector_cosine_ops`
- Eloquent models `Document` (hasMany chunks) + `Chunk` (belongsTo, casts) with fillable/guarded and scopes
- Config `config/services.php` entries for `openai.key` + `groq.key/model` sourced from `env()`, never hardcoded
- Storage disk `uploads` for ingestion staging (used by next change)

## Capabilities

### New Capabilities
- `db-schema`: documents/chunks tables, pgvector type, FK constraints, indexes
- `vector-storage`: pgvector extension lifecycle and cosine operator support

### Modified Capabilities
- None (greenfield)

## Impact

- Affects: `database/migrations/*`, `app/Models/*`, `config/services.php`, `.env.example`, Docker Postgres image (needs pgvector), RDS engine version check
- Dependencies: Postgres with pgvector (local Docker `pgvector/pgvector:pg16` or RDS PG15+)
- No API yet — migrations only, no breaking changes
