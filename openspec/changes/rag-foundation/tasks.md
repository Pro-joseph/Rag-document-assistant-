## 1. Project bootstrap

- [ ] 1.1 Scaffold Laravel 13 project or verify existing `rag-api` scaffold (`composer create-project laravel/laravel rag-api`), set `DB_CONNECTION=pgsql` in `.env` pointing to pgvector Postgres
- [ ] 1.2 Add `config/services.php` entries for `openai.key` and `groq.key/model` with `env()` defaults, update `.env.example` with `OPENAI_API_KEY=`, `GROQ_API_KEY=`, `GROQ_MODEL=llama-3.3-70b-versatile`

## 2. Database migrations

- [ ] 2.1 Create migration `create_documents_table` with `id`, `filename`, `status` default `pending`, `timestamps` and index on `created_at DESC`
- [ ] 2.2 Create migration `create_chunks_table` with `id`, `document_id` FK cascade, `chunk_index`, `content`, `timestamps` — no embedding column yet
- [ ] 2.3 Add migration/raw statements: `CREATE EXTENSION IF NOT EXISTS vector;`, `ALTER TABLE chunks ADD COLUMN embedding vector(1536)`, `CREATE INDEX chunks_embedding_idx ON chunks USING ivfflat (embedding vector_cosine_ops)` and corresponding `down()` drops

## 3. Eloquent models

- [ ] 3.1 Implement `App\Models\Document` with `HasFactory`, fillable `filename,status`, `hasMany(Chunk::class)`, scopes `scopeReady`/`scopeFailed`
- [ ] 3.2 Implement `App\Models\Chunk` with `belongsTo(Document::class)`, fillable `document_id,chunk_index,content`, note embedding is raw vector (no cast, inserted via `DB::statement` with `?::vector`)

## 4. Verification

- [ ] 4.1 Run `php artisan migrate` fresh and verify `\d documents`, `\d chunks`, `\dx` show vector extension
- [ ] 4.2 Smoke test in `psql`: insert one chunk with literal `'[0.1,0.2,...(1536)]'::vector` and query `SELECT 1 - (embedding <=> '[...]'::vector) FROM chunks LIMIT 1` returns score
- [ ] 4.3 Run `php artisan migrate:rollback && php artisan migrate` round-trip succeeds
