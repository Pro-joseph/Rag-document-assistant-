## 1. Requests and Resources

- [ ] 1.1 Create `QueryRequest` (`php artisan make:request QueryRequest`) with `question=>required|string|max:2000`
- [ ] 1.2 Create `QueryResource` (`php artisan make:resource QueryResource`) mapping `answer,sources` (sources via `Retriever` rows)
- [ ] 1.3 Feature test: validation 422 cases vs valid pass

## 2. Services

- [ ] 2.1 Implement `App\Services\Retriever` per spec with `EmbeddingService` DI, vector literal, `DB::select` with `<=>` and `LIMIT`, order by distance ASC
- [ ] 2.2 Implement `App\Services\AnswerGenerator` per spec with context building, system/user messages, `temperature 0.2`, fallback `No documents...`; mock Groq via `Http::fake`
- [ ] 2.3 Unit tests: Retriever empty vs ranked hits (mock EmbeddingService), AnswerGenerator prompt contains citations / fallback, temperature/config checks

## 3. Controller and Routes

- [ ] 3.1 Implement `QueryController@ask(QueryRequest $request, Retriever $retriever, AnswerGenerator $generator)` thin, returning `response()->json(['answer'=>..., 'sources'=>...])` (or QueryResource)
- [ ] 3.2 Register `POST /api/query` in `routes/api.php` alongside document routes
- [ ] 3.3 Add config `services.groq.model` default `llama-3.3-70b-versatile`, ensure `GROQ_API_KEY` in `.env.example`

## 4. Verification

- [ ] 4.1 Seed one document with known content, POST `/api/query` with related question → assert answer contains expected phrase and `sources[0].filename` correct
- [ ] 4.2 POST unrelated question → assert answer contains `don't know` or indicates lack of context (F8), sources returned but not hallucinated
- [ ] 4.3 Latency check: query round-trip <5s with 4 hits (measure)
