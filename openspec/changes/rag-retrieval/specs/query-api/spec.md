## ADDED Requirements

### Requirement: QueryRequest validates
The system SHALL provide `App\Http\Requests\QueryRequest` with `question=>required|string|max:2000` and `authorize()->true`, returning 422 on failure.

#### Scenario: Valid question passes
- **WHEN** POST `/api/query` with `{"question":"What is X?"}`
- **THEN** passes validation

#### Scenario: Missing question rejected
- **WHEN** POST `/api/query` with `{}`
- **THEN** response 422

#### Scenario: Overlong question rejected
- **WHEN** POST with 3000-char question
- **THEN** 422

### Requirement: QueryController thin orchestrates retrieval+generation
`QueryController@ask` SHALL type-hint `QueryRequest`, inject `Retriever`+`AnswerGenerator` via DI, call `$retriever->search($request->question)` then `$generator->generate($request->question, $hits)`, and return `response()->json(['answer'=>$answer,'sources'=>$hits])` via `QueryResource`. No embedding or SQL SHALL be in controller.

#### Scenario: End-to-end query returns answer+sources
- **WHEN** POST `/api/query` with indexed doc containing "revenue is 100"
- **THEN** response 200 with `answer` string containing grounded content and `sources` array length >=1 with `content,filename,score`

#### Scenario: Query with no docs returns grounded fallback
- **WHEN** POST `/api/query` with empty DB
- **THEN** response 200 with `answer` indicating no documents and `sources` empty array

### Requirement: QueryResource shapes response
`QueryResource` or direct JSON SHALL shape to `{answer: string, sources: [{content,filename,score}]}` with no internal fields.

#### Scenario: Resource shape
- **WHEN** query succeeds
- **THEN** JSON has top-level `answer` and `sources` and each source has `filename` traceability F7
