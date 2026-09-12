## 1. Service

- [x] 1.1 Implement batch embedding request for chunk arrays and verify `Http::fake` returns 2 vectors of 1536 dims for 2 chunks
- [x] 1.2 Wire OpenAI key via `config('services.openai.key')` plus `.env.example` entry and verify config resolves without hardcoded secret
- [x] 1.3 Skip embedding call on empty chunk list and verify document completes with zero vectors

## 2. Pipeline integration

- [x] 2.1 Catch embed failure in ingestion job, mark document `failed` with log, and verify no partial vectors remain
- [x] 2.2 Verify failed document does not block next queued document from reaching `ready`

## 3. Verification

- [x] 3.1 Run unit tests for batch, empty-skip, and error paths and verify all pass
- [ ] 3.2 Run queue worker once with a sample upload and verify `GET /api/documents` shows `ready` with vectors or `failed` on forced error
