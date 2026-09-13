## Context

See proposal.md - Why. Chunks come from `TextChunker` (800 chars, 150 overlap). Vectors must be 1536 dims (`vector-storage` requires `vector(1536)` + `ivfflat`). `IngestDocument` already orchestrates parse → chunk → embed → insert; this change makes the embed step and its error branch explicit per US-13/US-14.

## Goals / Non-Goals

**Goals:**
- Batch embed of chunk array in one OpenAI call, skip when empty
- Explicit failure contract: embed error → document `failed`, logged, queue continues

**Non-Goals:**
- No parsing, chunking, retrieval, query API, or frontend changes
- No model change (stays `text-embedding-3-small`), no re-embed/backfill CLI

## Decisions

- Batch `embed(array $texts): array` via `Http::withToken` to `.../v1/embeddings` — cheaper than per-chunk calls; alternative per-chunk rejected for cost. Follows existing `ingestion-pipeline` pattern.
- Stateless service resolved via container, key from `config('services.openai.key')` (env, never hardcoded).
- Empty chunk array skips the HTTP call entirely — avoids wasteful request and matches existing empty-parse behavior.
- `IngestDocument::handle` wraps embed + insert in try/catch → `status failed` + log, no partial `?::vector` inserts committed on failure. Upload already returned 202, so no 500 to caller.
- Retry transient 429/5xx with `Http::retry(3,100)` before failing; assert `count(vec) == 1536` before insert to catch dimension drift.

## Risks / Trade-offs

- [OpenAI 429/5xx] → Mitigation: retry with backoff, then `failed` + log
- [Dimension mismatch if model changes] → Mitigation: assert 1536 before `?::vector` insert
- [Large batch cost] → Mitigation: cap batch size (~50 chunks per call, chunk from 20MB file stays below)
- [Partial inserts on mid-batch failure] → Mitigation: insert only after successful embed of full batch

## Migration Plan

1. Add `services.openai.key` config + `.env.example` entry if missing
2. Implement service + job catch branch; no DB migration (column already `vector(1536)`)
3. Rollback: revert service/job files — safe, stateless

## Open Questions

- None blocking; batch size cap tunable later without spec change.
