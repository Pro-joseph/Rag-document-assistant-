## ADDED Requirements

### Requirement: Upload validates and dispatches ingestion
The system SHALL expose `POST /api/documents` accepting `multipart/form-data` with field `file`. Validation SHALL be in `StoreDocumentRequest` with rules `required|file|mimes:pdf,docx,txt,csv|max:20480`. On success SHALL store file to `uploads` disk, create `Document` with `filename=originalName` and `status=pending`, dispatch `IngestDocument` job, and return `DocumentResource` with HTTP 202 containing `id,filename,status`.

#### Scenario: Valid PDF upload returns 202
- **WHEN** client POSTs a 1MB `sample.pdf` with `mimes pdf`
- **THEN** response is 202 with `status pending` and job is queued

#### Scenario: Invalid mime rejected
- **WHEN** client POSTs `image.png` or `exe` file
- **THEN** response is 422 with validation error for `file`

#### Scenario: Oversized file rejected
- **WHEN** client POSTs 25MB file
- **THEN** response is 422 (exceeds 20480 KB)

### Requirement: List documents with status
The system SHALL expose `GET /api/documents` returning `DocumentResource` collection ordered by `created_at DESC` with `id,filename,status,created_at`.

#### Scenario: List after upload
- **WHEN** GET `/api/documents` after one upload still `processing`
- **THEN** list contains one entry with `status` in `pending|processing|ready|failed`

### Requirement: Delete cascades to chunks and file
The system SHALL expose `DELETE /api/documents/{id}` deleting the document, its chunks via FK cascade, and the stored file from disk, returning 204. Thin controller SHALL delegate to model delete and `Storage::delete`.

#### Scenario: Delete removes chunks
- **WHEN** DELETE `/api/documents/1` for a `ready` document with 5 chunks
- **THEN** response is 204 and `SELECT count(*) FROM chunks WHERE document_id=1` is 0 and stored file is removed

### Requirement: Thin controller and FormRequest separation
Controllers SHALL NOT contain validation logic inline; all validation SHALL reside in `StoreDocumentRequest`. Controllers SHALL only orchestrate: validate, store, create model, dispatch job, return Resource. Services SHALL be injected via DI.

#### Scenario: Controller SRP check
- **WHEN** `DocumentController@store` is inspected
- **THEN** it type-hints `StoreDocumentRequest` and contains no `validate([...])` call

### Requirement: API Resource shapes responses
The system SHALL provide `DocumentResource` transforming `Document` to JSON with `id,filename,status,created_at,updated_at` and use it for store/index/delete flows.

#### Scenario: Resource shape
- **WHEN** POST succeeds
- **THEN** JSON contains `data.id`, `data.filename`, `data.status` (no internal fields leaked)
