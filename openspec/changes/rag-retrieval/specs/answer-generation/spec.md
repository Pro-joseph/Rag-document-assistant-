## ADDED Requirements

### Requirement: AnswerGenerator is grounded and cites sources
The system SHALL provide `App\Services\AnswerGenerator` with `generate(string $question, array $hits): string` that: if `hits` non-empty builds `context = collect(hits)->map(fn($h,$i)=>"[".($i+1)."] (source: {$h->filename})\n{$h->content}")->implode("\n\n")` and prompt `Context:\n$context\n\nQuestion: $question\n\nAnswer using only context above, citing [n] numbers.` else builds `No documents ingested yet.\n\nQuestion: $question`. Then calls `Http::withToken(config('services.groq.key'))->post('https://api.groq.com/openai/v1/chat/completions', ['model'=>config('services.groq.model'), 'messages'=>[['role'=>'system','content'=>'Answer only from given context. If answer is not in it, say you don\'t know.'],['role'=>'user','content'=>$prompt]], 'temperature'=>0.2])->throw()->json()['choices'][0]['message']['content']` and returns it.

#### Scenario: Grounded answer with citations
- **WHEN** `generate("What is X?", [{content:"X is Y",filename:"doc.pdf"}])` with Groq fake returning "X is Y [1]"
- **THEN** returns string containing "X is Y" and prompt sent to Groq contained "[1] (source: doc.pdf)" and "X is Y" context

#### Scenario: Empty context fallback
- **WHEN** `generate("What is X?", [])` is called
- **THEN** prompt contains "No documents ingested yet" and system message states `If answer is not in it, say you don't know`, and returned answer is from Groq (e.g., "I don't know")

#### Scenario: No hallucination on irrelevant hits
- **WHEN** hits are unrelated to question
- **THEN** Groq is instructed via system prompt to say `you don't know` rather than invent

### Requirement: Generation is <5s and config-driven
Groq model SHALL come from `config('services.groq.model')` default `llama-3.3-70b-versatile`, temperature 0.2, and total query SHALL aim for <5s per NFR.

#### Scenario: Config used
- **WHEN** `config('services.groq.model')` is `llama-3.3-70b-versatile`
- **THEN** request body `model` equals that value and temperature is 0.2

### Requirement: Handles Groq and query-embedding failures gracefully
The system SHALL catch Http failures from Groq (429/5xx/timeout after retries) and from query embedding, log the cause, and return HTTP 503 JSON with `message` containing "temporarily unavailable", `code` of `groq_unavailable`, and `sources` preserved from retrieved hits. The system SHALL never leak stack traces and SHALL keep validation 422 behavior unchanged.

#### Scenario: Groq failure returns 503 with sources preserved
- **WHEN** POST `/api/query` runs with retrieved hits and Groq returns 500
- **THEN** response is 503 with `message`, `code` of `groq_unavailable`, and `sources` matching the hits

#### Scenario: Failure does not lose conversation context
- **WHEN** generation fails after prior successful messages
- **THEN** the error response still includes `sources` and prior history remains intact on the client
