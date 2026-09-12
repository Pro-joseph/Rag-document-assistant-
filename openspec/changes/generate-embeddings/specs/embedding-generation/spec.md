## Purpose

Numerically represent chunk meaning and survive embedding failures without stalling ingestion. Covers US-13 per-chunk generation and US-14 error handling.

## ADDED Requirements

### Requirement: Generate embeddings for document chunks
The system SHALL generate an embedding for each available document chunk. When chunks exist, the system SHALL request vectors in a single batch and store one 1536-dimension vector per chunk. When no chunks exist, the system SHALL skip the request and finish with zero vectors.

#### Scenario: Chunks available generate embeddings
- **WHEN** document chunks are available and the embedding process runs
- **THEN** an embedding is generated for each chunk

#### Scenario: Empty chunks skip embedding
- **WHEN** chunking produces an empty list
- **THEN** no embedding request is made and processing completes with zero vectors

#### Scenario: Stored vectors match chunk count and dimension
- **WHEN** two chunks are embedded
- **THEN** two vectors of length 1536 are stored, one per chunk index

### Requirement: Handle embedding errors
The system SHALL handle embedding failures so the pipeline does not fail unexpectedly. On service error the system SHALL mark the document `failed`, log the cause, leave no partial vectors, and allow other queued documents to continue.

#### Scenario: Service error is handled
- **WHEN** an embedding cannot be generated and the service returns an error
- **THEN** the system handles the error appropriately and the document becomes `failed`

#### Scenario: Failed document does not block queue
- **WHEN** embedding fails for one document while another is queued
- **THEN** the failed document stays `failed` and the next document still reaches `ready`
