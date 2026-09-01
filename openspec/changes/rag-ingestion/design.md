## Context

Foundation provides tables. Now need upload → async pipeline per `cahier-des-charges-rag.md:107` steps 1-4 and `rag-laravel-nextjs-guide.md:85-247`. Current controller prototype does validation inline `rag-laravel-nextjs-guide.md:324` — violates SRP, must split.

## Goals / Non-Goals

**Goals:**
- Thin controllers delegating to services/job; validation isolated in FormRequest
- Deterministic, testable chunking preserving two-phase invariant
- Queued embeddings via OpenAI batch, pgvector insert with `?::vector`
- Status tracking observable via `GET /documents` polling

**Non-Goals:**
- No retrieval/generation (next change)
- No frontend (later change)
- No auth, no file editing, no image/audio

## Decisions

- **Decision: `StoreDocumentRequest` + `DocumentController` thin** — Rationale: Laravel best practice, reusable validation `required|file|mimes:pdf,docx,txt,csv|max:20480` `rag-laravel-nextjs-guide.md:326`. Controller only: validate, store to disk, `Document::create`, dispatch job, return `DocumentResource` 202. Alternative: inline validation — rejected (fat controller, untestable).
- **Decision: Service layer `DocumentParser`, `TextChunker`, `EmbeddingService`** — SRP, DI via container, mockable. `DocumentParser::parse(path, ext)` match dispatch `rag-laravel-nextjs-guide.md:88`. Alternative: static helpers — rejected (no DI).
- **Decision: Keep two-phase `splitIntoPieces` → `mergePieces` in `TextChunker`** — Fixes oversized block bug `rag-laravel-nextjs-guide.md:193`. Merge uses `mb_strlen`, `mb_substr`, overlap tail handling `rag-laravel-nextjs-guide.md:163-189`. Alternative: naive `str_split` — rejected (breaks boundaries, ignores overlap).
- **Decision: `EmbeddingService::embed(array $texts): array` batch POST to `https://api.openai.com/v1/embeddings` with `Http::withToken`** `rag-laravel-nextjs-guide.md:203` — batch is cheaper than per-chunk. Guzzle via Laravel Http. Alternative: per-chunk calls — rejected cost.
- **Decision: `IngestDocument implements ShouldQueue` with injected services** — `handle(DocumentParser, TextChunker, EmbeddingService)` via container. Updates `processing` on start, `ready` on success, `failed` on exception. Uses `DB::statement('INSERT ... ?::vector')` with `implode` literal `rag-laravel-nextjs-guide.md:238`. Alternative: sync ingestion — rejected (blocks HTTP).
- **Decision: `Api Resources` for responses** — `DocumentResource` wraps `id,filename,status,created_at`; ensures consistent JSON. Controllers never return raw model arrays.
- **Decision: `Storage::disk('local')` `uploads/` path via `store('uploads')` + `Storage::path()` for job** — Keeps files outside public, easy to clean on `Document::delete` cascade.

## Risks / Trade-offs

- [PDF malformed / encrypted] → Mitigation: catch `InvalidArgumentException`, set `failed`, log; chunker validates empty text returns `[]`.
- [OpenAI rate limit / timeout] → Mitigation: job retry with backoff, small batch size (chunk count typically <50 for 20MB); use `Http::retry(3,100)`.
- [Queue worker not running] → Mitigation: docs require `php artisan queue:work` as separate process/container; `pending` stays visible and pollable.
- [Embedding cost spike] → Mitigation: limit `max:20480`, limit chunkSize 800, optional rate limiting middleware later.
- [CSV with binary] → Mitigation: parser uses `array_map('str_getcsv', file($path))` fallback; validation ensures `mimes` but not content sniffing.

## Migration Plan

1. `composer require smalot/pdfparser phpoffice/phpword guzzlehttp/guzzle`
2. Deploy services/jobs/controllers/requests/resources; keep migration idempotent (no DB change)
3. Restart queue worker; ensure `QUEUE_CONNECTION=database` + `php artisan queue:table` if needed
4. Verify: `curl -F file=@sample.pdf http://localhost:8000/api/documents` → 202, poll `GET /api/documents` until `ready`, check `chunks` count >0

## Open Questions

- Batch embedding size limit? OpenAI max tokens per request ~8191 per input, batch of many 800-char chunks is safe — monitor.
- Should `DELETE` also delete stored file from disk? Yes — add `Storage::delete` in `destroy` or model observer.
