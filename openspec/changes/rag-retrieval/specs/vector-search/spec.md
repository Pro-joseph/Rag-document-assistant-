## ADDED Requirements

### Requirement: Retriever embeds and cosine searches
The system SHALL provide `App\Services\Retriever` with `search(string $question, int $topK=4): array` that `embed([$question])[0]` to get 1536-dim vector, builds literal `'['.implode(',', $vec).']'`, and executes `DB::select('SELECT chunks.content, documents.filename, 1 - (chunks.embedding <=> ?::vector) AS score FROM chunks JOIN documents ON documents.id=chunks.document_id ORDER BY chunks.embedding <=> ?::vector LIMIT ?', [$literal,$literal,$topK])`, returning array of `{content,filename,score}` ordered by score DESC (distance ASC).

#### Scenario: Search returns ranked hits
- **WHEN** `search("what is revenue?")` with 2 relevant chunks and 1 irrelevant
- **THEN** returns up to 4 rows ordered by `embedding <=> query` ASC, each with `content` non-empty, `filename` present, `score` float 0-1

#### Scenario: Empty DB returns empty array
- **WHEN** `search("anything")` with zero chunks in DB
- **THEN** returns `[]`

#### Scenario: TopK respected
- **WHEN** `search("q", 2)` is called
- **THEN** at most 2 rows are returned

### Requirement: Retriever is DI injectable
`Retriever` SHALL be constructed with `EmbeddingService` via container and have no controller logic.

#### Scenario: Container resolves Retriever
- **WHEN** `app(Retriever::class)` is resolved
- **THEN** instance has `EmbeddingService` injected and `search` is callable
