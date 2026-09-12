## Why

Document chunks need numeric vectors before cosine search can work. Pipeline must also survive embedding API failures without stalling the queue (F2 reliability).

## What Changes

- Add batch embedding of `TextChunker` output via OpenAI `text-embedding-3-small` (1536 dims), skipping empty input
- Add explicit error handling in `IngestDocument`: catch embed failure, mark document `failed`, log, continue queue
- No upload, parsing, chunking, retrieval, or frontend changes

## Capabilities

### New Capabilities
- `embedding-generation`: per-chunk vector generation (US-13) and error handling (US-14)

### Modified Capabilities
- None

## Impact

- Affects: `Backend/rag/app/Services/EmbeddingService.php`, `Backend/rag/app/Jobs/IngestDocument.php`, `Backend/rag/config/services.php` (`openai.key`)
- Dependencies: `rag-foundation` vector column `vector(1536)` + `rag-ingestion` chunker output; blocks `rag-retrieval` query-time embed reuse
