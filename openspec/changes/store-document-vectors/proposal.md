# Proposal: store-document-vectors

## Why

Embeddings are generated (US-13/14) but persisted as raw SQL inside `IngestDocument`, and search only works by re-deriving document info through a JOIN. US-15/16/17 require a first-class persistence layer: embeddings stored in the vector column, metadata stored alongside each vector so retrieved chunks link back to their documents, and a verification path proving stored vectors are retrievable.

## What Changes

- Add `chunks.metadata` column (jsonb on Postgres, JSON text elsewhere) storing `{document_id, filename, chunk_index}` per stored vector (US-16)
- Extract persistence into `App\Services\VectorStore`:
  - `store()` writes each chunk vector + metadata into the `chunks` table (pgvector `::vector` on Postgres, JSON array otherwise) and validates the 1536-dim contract (US-15)
  - `nearest()` retrieves top-K stored vectors by cosine similarity, returning `content, filename, score, metadata` on Postgres via `<=>` and via in-PHP cosine scan on non-Postgres DBs (US-17)
- Refactor `IngestDocument` and `Retriever` to delegate storage/search to `VectorStore` (single persistence path)
- Tests proving vectors + metadata are stored and retrievable (US-15/16/17 acceptance)

## Capabilities

### New Capabilities
- `vector-persistence`: store embeddings with document metadata and retrieve stored vectors by cosine similarity

### Modified Capabilities
- None

## Impact

- Affects: `Backend/rag/database/migrations/*` (new `metadata` column), `Backend/rag/app/Services/VectorStore.php`, `Backend/rag/app/Jobs/IngestDocument.php`, `Backend/rag/app/Services/Retriever.php`, `Backend/rag/app/Models/Chunk.php`, tests
- Dependencies: `rag-foundation` (chunks table, pgvector), `generate-embeddings` (1536-dim vectors). Blocks `rag-retrieval` at runtime only via shared `Retriever`, no API contract change.