## Context

See proposal.md - Why. `IngestDocument` currently embeds and inserts chunks inline, and `Retriever` runs its own `<=>` query. US-15 needs embeddings stored in the vector column; US-16 needs document metadata stored with each vector; US-17 needs a retrieval path that proves stored vectors come back. Metadata is added as a `chunks.metadata` jsonb/text column so retrieved hits are self-describing without a JOIN for display, while `document_id` FK remains for relational integrity.

## Goals / Non-Goals

**Goals:**
- Persist each generated vector into `chunks.embedding` (pgvector / JSON fallback) with matching `metadata` in the same row
- Store `{document_id, filename, chunk_index}` metadata with every vector
- Provide `nearest()` retrieving top-K stored vectors by cosine similarity with `content, filename, score, metadata`
- Centralize persistence so `IngestDocument` and `Retriever` share one path; validate 1536-dim contract before insert

**Non-Goals:**
- No query API changes (`QueryController`/`QueryResource`/routes untouched)
- No re-embed/backfill CLI, no vector index tuning, no chunking changes
- Postgres-only features stay guarded: the in-PHP cosine scan keeps the default SQLite dev DB functional

## Decisions

- Persistence in `VectorStore` not spread across job + retriever. `store()` mirrors current insert semantics (per-row `?::vector` cast on `pgsql`, `json_encode` fallback), preserving row shapes and FK ordering so existing `IngestDocumentTest` assertions hold.
- Metadata jsonb on Postgres, JSON text elsewhere — no DB-specific model code needed beyond the migration guard.
- `nearest()` uses `chunks.embedding <=> ?::vector` on Postgres (same operator already indexed with `ivfflat`); non-Postgres falls back to loading rows and computing normalized dot-product cosine in PHP, ranking descending. Rows with a null embedding are included with score `0.0` so pre-index chunks (e.g. created directly in old tests) keep being returned.
- Content hash locked to `text-embedding-3-small` (1536) via `const DIMENSION`, matching `generate-embeddings` spec; invalid dimension throws before any insert.
- `Retriever` keeps orchestrating question embedding then delegates ranking to `nearest()`.

## Risks / Trade-offs

- [Duplicate metadata vs JOIN] → Mitigation: metadata mirrors `documents.filename` at insert time; relational truth stays in `documents` table
- [Null legacy embeddings in dev DB] → Mitigation: `nearest()` scores them 0.0 and still includes them (previous behavior returned all rows)
- [Non-Postgres scan is O(n) in-PHP] → Mitigation: fine for local dev; production runs Postgres ivfflat
- [Model dimension drift] → Mitigation: 1536 assert in `store()` throws before write (existing behavior preserved)

## Migration Plan

1. Add `chunks.metadata` nullable column via new migration (jsonb on Postgres, text elsewhere); `down()` drops it
2. Add `VectorStore`, wire into `IngestDocument`/`Retriever`, add model fillable + metadata cast on `Chunk`
3. Rollback: revert service/job/retriever/model changes and drop migration — stateless, no data loss

## Open Questions

- None blocking; batch insert optimizations deferred.