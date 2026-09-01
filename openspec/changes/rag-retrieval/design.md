## Context

Documents are chunked + embedded. Need query path `cahier-des-charges-rag.md:115` steps 1-5: embed question → similarity search → build prompt → call Groq → return answer+sources. Must enforce grounded-only answers F6-F8 and <5s latency.

## Goals / Non-Goals

**Goals:**
- Thin `QueryController` with `QueryRequest` + `QueryResource`
- `Retriever` via `pgvector <=>`, `AnswerGenerator` via Groq `openai/v1/chat/completions`
- Citations `[source]` numbers, graceful `don't know` when no relevant chunks
- Testable via `Http::fake` for both OpenAI and Groq

**Non-Goals:**
- No frontend yet, no streaming, no persistent chat history, no re-ranking yet

## Decisions

- **Decision: `QueryRequest` with `question required|string|max:2000`** — isolates validation, controller stays thin. Alternative: inline validate — rejected SRP violation.
- **Decision: `Retriever` depends on `EmbeddingService` via DI** `rag-laravel-nextjs-guide.md:256` — embeds question to 1536 vector, builds literal `'['.implode(',',$vec).']'`, queries `SELECT content, filename, 1 - (embedding <=> ?::vector) AS score FROM chunks JOIN documents ... ORDER BY embedding <=> ?::vector LIMIT ?` `rag-laravel-nextjs-guide.md:264`. Alternative: precompute query vector client-side — rejected security.
- **Decision: `AnswerGenerator::generate(question, hits)` builds context as `collect(hits)->map(fn($h,$i)=>"[".($i+1)."] (source: {$h->filename})\n{$h->content}")->implode("\n\n")` `rag-laravel-nextjs-guide.md:286`** — numbered citations, includes filename for traceability F7. Prompt: `Context:\n$context\n\nQuestion: $question\n\nAnswer using only context, citing [n]` or `No documents ingested yet.\n\nQuestion:...` fallback `rag-laravel-nextjs-guide.md:290`. Alternative: raw concatenation — less citation fidelity.
- **Decision: Groq via `Http::withToken(config('services.groq.key'))->post('https://api.groq.com/openai/v1/chat/completions', [model, messages, temperature 0.2])`** `rag-laravel-nextjs-guide.md:294` — `temperature 0.2` for determinism, system role `Answer only from given context. If not in it, say you don't know.` Alternative: higher temperature — risks hallucination.
- **Decision: `QueryController@ask(QueryRequest, Retriever, AnswerGenerator)` → `return response()->json(['answer'=>..., 'sources'=>...])`** `rag-laravel-nextjs-guide.md:351` — no business logic, just `validate → search → generate → QueryResource`. `QueryResource` shapes `answer` + `sources{content,filename,score}`.
- **Decision: Sync query (no queue)** — retrieval+generation must be <5s, queue would add latency. Ingestion remains async.

## Risks / Trade-offs

- [TopK too small → missing context] → Mitigation: default 4, tunable via request param `topK` clamp 1-10 later.
- [Groq hallucination despite prompt] → Mitigation: system prompt strict, `temperature 0.2`, fallback answer when `hits empty` or score below threshold not yet; spec requires `don't know` path F8.
- [Embedding latency spikes OpenAI] → Mitigation: `Http::timeout(5)` + `retry`; total budget <5s NFR, log slow queries.
- [Sources leak large content] → Mitigation: return only needed `content` slice; consider truncation if chunk >800.
- [Cost on public demo] → Mitigation: rate limiting middleware per `cahier-des-charges-rag.md:174` future, limit question length.

## Migration Plan

1. Add services/controllers/requests/resources/routes — no DB migration
2. Verify: `curl -X POST /api/query -H 'Content-Type: application/json' -d '{"question":"What is X?"}'` → `{answer,sources}` with citations
3. Test no-context: query with empty DB → `No documents...` path and answer indicates lack of sources
4. Rollback: remove routes/services — safe, stateless

## Open Questions

- Threshold for “no relevant context” — currently always generate with whatever hits returned; add `score <0.7` filter later?
- Streaming response needed? No for v1.
