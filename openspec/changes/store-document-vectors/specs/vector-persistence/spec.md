# store-document-vectors

## ADDED Requirements

### Requirement: Store embeddings in the chunks vector column
The system SHALL persist each generated embedding into the `chunks` table via a dedicated `VectorStore` service. On PostgreSQL the embedding SHALL be written as a `vector` literal (`?::vector`); on other drivers the embedding SHALL be stored as a JSON array in the `embedding` column. The service SHALL validate that every vector has exactly 1536 dimensions and throw a `RuntimeException` before inserting anything when the dimensions do not match.

#### Scenario: Vectors persisted per chunk (US-15)
- **WHEN** `VectorStore::store` runs with 2 chunks and 2 matching vectors
- **THEN** 2 chunk rows exist, each with an `embedding` matching its input vector and the same `document_id`

#### Scenario: Invalid dimension rejected (US-15)
- **WHEN** a vector with a wrong dimension is passed to `VectorStore::store`
- **THEN** a `RuntimeException` is thrown and no chunk rows are inserted

### Requirement: Store document metadata with each vector
The system SHALL store with every vector a `metadata` value containing the associated document information: `document_id`, `filename`, and `chunk_index`. Metadata SHALL be written in the same row as the embedding, as JSON in the `chunks.metadata` column (jsonb on PostgreSQL, text elsewhere), and SHALL be readable back from the stored chunk.

#### Scenario: Metadata stored with vector (US-16)
- **WHEN** a chunk is stored for a document titled `manual.pdf`
- **THEN** the stored chunk's `metadata` contains `{"document_id": <id>, "filename": "manual.pdf", "chunk_index": 0}`

#### Scenario: Metadata survives storage round-trip (US-16)
- **WHEN** the stored chunk is queried back via `Chunk::find`
- **THEN** its `metadata` cast returns the same `{document_id, filename, chunk_index}` array

### Requirement: Retrieve stored vectors by cosine similarity
The system SHALL provide `VectorStore::nearest($vector, $topK)` returning the most similar stored chunks with their content, filename, score, and metadata. On PostgreSQL this SHALL use the `<=>` cosine operator with the existing `ivfflat` index; on non-PostgreSQL drivers the system SHALL compute cosine similarity in PHP over the stored JSON embeddings and rank descending.

#### Scenario: Stored vectors are retrievable (US-17)
- **WHEN** vectors have been stored and `nearest` is called with a query vector
- **THEN** the stored chunks are returned with a cosine similarity score in `[0, 1]` and their `metadata.filename` matches the originating document

#### Scenario: Closest vector ranks first (US-17)
- **WHEN** two stored vectors differ and a query vector is closer to the first
- **THEN** the first chunk appears before the second in the result ordering

#### Scenario: Rows without an embedding still listed (US-17)
- **WHEN** a chunk has a null `embedding`
- **THEN** it is still returned with score `0.0` rather than dropped