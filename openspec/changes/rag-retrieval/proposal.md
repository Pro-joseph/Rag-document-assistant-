# Proposal: rag-retrieval

## Why

Ingested chunks are inert without retrieval + grounded generation. Need to turn a natural-language question into a cited answer (F5-F8) with no hallucination, completing the RAG loop.

## What Changes

- `POST /api/query` via `QueryRequest` → `QueryController@ask` orchestrating `Retriever` + `AnswerGenerator` → `{answer, sources[]}`
- `Retriever` cosine search via `pgvector <=>`, returns topK=4 hits with `content, filename, score`
- `AnswerGenerator` builds grounded prompt with `[n] (source: filename)` context and calls Groq `llama-3.3-70b-versatile` at `temperature 0.2`, fallback `No documents ingested yet` / `I don't know`
- `QueryResource` shapes answer + sources

## Capabilities

### New Capabilities
- `vector-search`: embedding query + pgvector cosine ranking
- `answer-generation`: Groq generation grounded only on context with citations and absence handling
- `query-api`: `POST /api/query` thin controller + FormRequest + Resource

### Modified Capabilities
- None

## Impact

- Affects: `app/Http/Requests/QueryRequest`, `app/Http/Controllers/QueryController`, `app/Services/Retriever`, `app/Services/AnswerGenerator`, `app/Http/Resources/QueryResource`, `routes/api.php`, `config/services.php` groq entry, queue not needed (sync query)
- Dependencies: `rag-foundation` (tables/index), `rag-ingestion` (chunks), `EmbeddingService`, Groq API key, must stay <5s per `cahier-des-charges-rag.md:130`
