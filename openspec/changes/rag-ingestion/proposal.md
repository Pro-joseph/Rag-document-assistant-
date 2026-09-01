# Proposal: rag-ingestion

## Why

Users cannot ingest documents today — no upload, parsing, chunking, or embedding pipeline exists. Need async ingestion (F1-F4) so files go `pending→processing→ready/failed` without blocking HTTP.

## What Changes

- Upload endpoint `POST /api/documents` via `StoreDocumentRequest` + `DocumentController@store` → 202 + dispatch queued `IngestDocument` job
- `GET /api/documents` + `DELETE /api/documents/{id}` (cascade) via `Index/Resource` thin controllers
- Services: `DocumentParser` (pdf/docx/txt/csv dispatch), `TextChunker` (800/150 split-then-merge), `EmbeddingService` (OpenAI batch)
- Queued job `IngestDocument` with status transitions and failure → `failed`
- `Storage` disk `uploads` + filesystem handling

## Capabilities

### New Capabilities
- `document-upload`: upload validation, storage, 202 dispatch, listing, deletion
- `document-parsing`: per-extension parsing (pdf via smalot/pdfparser, docx via phpoffice/phpword, txt/csv native)
- `text-chunking`: deterministic chunking with overlap and boundary respect (invariant)
- `ingestion-pipeline`: queued job orchestrating parse→chunk→embed→insert with vector literal `?::vector`

### Modified Capabilities
- None

## Impact

- Affects: `app/Http/Requests/StoreDocumentRequest`, `app/Http/Controllers/DocumentController`, `app/Services/*`, `app/Jobs/IngestDocument`, `app/Http/Resources/DocumentResource`, `routes/api.php`, `storage/app/uploads`, `composer.json` (pdfparser/phpword/guzzle), queue worker (`php artisan queue:work`)
- Dependencies: `rag-foundation` (tables + pgvector), OpenAI API key, queue driver (database or redis)
