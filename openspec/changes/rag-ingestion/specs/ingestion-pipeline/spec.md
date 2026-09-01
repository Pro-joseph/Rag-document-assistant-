## ADDED Requirements

### Requirement: Queued IngestDocument job orchestrates pipeline
The system SHALL provide `App\Jobs\IngestDocument implements ShouldQueue` with `__construct(Document $document, string $filePath)` and `handle(DocumentParser $parser, TextChunker $chunker, EmbeddingService $embedder)` doing: `update status processing`, `parse` via parser, `chunk` via chunker, `embed` via embedder batch, then for each `content` at index `i` insert with `DB::statement('INSERT INTO chunks (document_id, chunk_index, content, embedding, created_at, updated_at) VALUES (?,?,?,?::vector,now(),now())', [id, i, content, '['.implode(',', vectors[i]).']'])`, finally `update status ready`. On any exception SHALL set `status failed` and rethrow/log without blocking other jobs.

#### Scenario: Full pipeline pending→ready
- **WHEN** job runs for a 2-page PDF producing 3 chunks
- **THEN** document ends `ready` and `SELECT count(*) FROM chunks WHERE document_id=?` is 3 with embeddings non-null

#### Scenario: Failed parse sets failed
- **WHEN** parser throws (corrupt PDF)
- **THEN** document status becomes `failed` and no partial chunks remain or are rolled back, and exception is logged

#### Scenario: Empty parse produces no chunks but ready
- **WHEN** file contains only whitespace
- **THEN** chunker returns `[]`, embed is not called, document becomes `ready` with 0 chunks

### Requirement: EmbeddingService batch via OpenAI
The system SHALL provide `App\Services\EmbeddingService` with `embed(array $texts): array` calling `Http::withToken(config('services.openai.key'))->post('https://api.openai.com/v1/embeddings', ['model'=>'text-embedding-3-small','input'=>$texts])->throw()->json()` and returning `array_map(fn($item)=>$item['embedding'], data)`.

#### Scenario: Batch embeddings
- **WHEN** `embed(['hello','world'])` is called with mocked Http fake returning 2 vectors of 1536 dims
- **THEN** returns 2 arrays each length 1536

### Requirement: Queue worker separate process
The system SHALL document and support running `php artisan queue:work` as separate container/systemd process; ingestion SHALL NOT run synchronously in the HTTP request.

#### Scenario: Polling shows progress
- **WHEN** upload returns 202 and client polls `GET /api/documents` every 2s
- **THEN** status transitions `pending→processing→ready` without request timeout

### Requirement: File storage lifecycle
Uploaded files SHALL be stored via `$file->store('uploads')` on default disk and path passed to job via `Storage::path($path)`. `DELETE` SHALL remove stored file. Keys SHALL be env-driven, file size/mime never bypass validation.

#### Scenario: Stored file exists for job
- **WHEN** upload succeeds
- **THEN** file exists at `storage/app/uploads/<hash>` and `Storage::exists` is true until document deletion
