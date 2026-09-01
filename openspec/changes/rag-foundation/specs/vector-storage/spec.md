## ADDED Requirements

### Requirement: pgvector extension enabled
The system SHALL ensure PostgreSQL has `CREATE EXTENSION IF NOT EXISTS vector` enabled before any vector column is created. The check SHALL be part of the migration guard and documented for RDS manual enablement.

#### Scenario: Extension exists after migration
- **WHEN** migrations have run
- **THEN** `\dx` or `SELECT extname FROM pg_extension WHERE extname='vector'` returns a row

#### Scenario: RDS compatibility check documented
- **WHEN** provisioning RDS
- **THEN** docs note to verify `SHOW rds.extensions` includes `vector` for the chosen engine version

### Requirement: Embedding column as vector(1536)
The system SHALL add `chunks.embedding vector(1536)` via `DB::statement('ALTER TABLE chunks ADD COLUMN embedding vector(1536)')` (1536 = `text-embedding-3-small` dimension). Column SHALL be nullable to allow insertion before embedding in later job step during foundation verification.

#### Scenario: Vector column type correct
- **WHEN** `\d chunks` is inspected
- **THEN** `embedding` shows type `vector(1536)`

#### Scenario: Vector literal insert and cosine search
- **WHEN** a row is inserted with `'[0.1,0.2,...]'::vector` and queried with `SELECT content, 1 - (embedding <=> '[...]'::vector) AS score ORDER BY embedding <=> '[...]'::vector LIMIT 1`
- **THEN** a row is returned with cosine similarity score in `[0,1]` range

### Requirement: IVFFlat index for cosine similarity
The system SHALL create `CREATE INDEX chunks_embedding_idx ON chunks USING ivfflat (embedding vector_cosine_ops)` after column creation to accelerate `<=>` queries.

#### Scenario: Index exists
- **WHEN** `\di` or `SELECT indexname FROM pg_indexes WHERE tablename='chunks'` is queried
- **THEN** `chunks_embedding_idx` exists with method `ivfflat`

#### Scenario: Config for embeddings stored in env
- **WHEN** `config/services.php` is loaded
- **THEN** `config('services.openai.key')` equals `env('OPENAI_API_KEY')` and `config('services.groq.key')`/`groq.model` equal env values with default `llama-3.3-70b-versatile`, and no key is hardcoded in repo
