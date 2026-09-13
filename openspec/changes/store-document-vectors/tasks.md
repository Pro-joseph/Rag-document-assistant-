## 1. Schema

- [ ] 1.1 Add `chunks.metadata` nullable column (jsonb on pgsql, text otherwise) and verify `php artisan migrate` + `migrate:rollback` round-trip
- [ ] 1.2 Add `metadata` fillable + `array` cast on `Chunk` and verify `Chunk::create(['metadata' => [...]])` round-trips

## 2. Persistence service

- [ ] 2.1 Implement `VectorStore::store($document, $chunks, $vectors)` writing one row per chunk with pgvector/json embedding + `{document_id, filename, chunk_index}` metadata
- [ ] 2.2 Reject wrong dimension with RuntimeException before any insert
- [ ] 2.3 Refactor `IngestDocument` to delegate inserts to `VectorStore::store` and verify empty-chunk, success, failure tests still pass

## 3. Retrieval

- [ ] 3.1 Implement `VectorStore::nearest($vector, $topK)` pgsql `<=>` path returning `{content, filename, score, metadata}`
- [ ] 3.2 Implement non-pgsql cosine scan fallback and verify ranking desc + null-embedding rows included
- [ ] 3.3 Refactor `Retriever` to use `nearest()` and verify `QueryApiTest` still passes

## 4. Verification (US-17)

- [ ] 4.1 Feature test: after `IngestDocument` with faked embeddings, stored vectors are retrievable via `nearest()` with score in `[0,1]` and matching `metadata.filename`
- [ ] 4.2 Feature test: nearest ranking orders closer vector first without calling the embedding API again
- [ ] 4.3 Run full suite (`php artisan test --compact`) and Pint; all green