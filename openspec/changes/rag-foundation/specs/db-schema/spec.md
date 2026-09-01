## ADDED Requirements

### Requirement: Documents table schema
The system SHALL provide a `documents` table with `id` bigIncrements PK, `filename` string non-null, `status` string default `pending` with allowed values `pending|processing|ready|failed`, `created_at`/`updated_at` timestamps, ordered by `created_at DESC` for listing.

#### Scenario: Migration creates documents table
- **WHEN** `php artisan migrate` runs on a fresh DB
- **THEN** `documents` exists with columns `id`, `filename`, `status` default `pending`, `created_at`, `updated_at`

#### Scenario: Document defaults to pending
- **WHEN** a Document is created with only `filename`
- **THEN** `status` is `pending` and can transition to `processing|ready|failed`

### Requirement: Chunks table schema with FK cascade
The system SHALL provide a `chunks` table with `id` bigIncrements PK, `document_id` FK references `documents.id` with `cascadeOnDelete`, `chunk_index` integer non-null, `content` text non-null, `embedding vector(1536)` via raw statement, `created_at`/`updated_at`, and `document_id` indexed via FK.

#### Scenario: FK cascade deletes chunks
- **WHEN** a Document is deleted
- **THEN** all related Chunks are deleted automatically via FK cascade

#### Scenario: Chunk ordering
- **WHEN** chunks for a document are queried
- **THEN** they are ordered by `chunk_index` ascending

### Requirement: Eloquent models with relations and scopes
The system SHALL provide `App\Models\Document` with `hasMany chunks`, fillable `filename,status`, casts, and `App\Models\Chunk` with `belongsTo document`, fillable `document_id,chunk_index,content`, and scopes `ready()`/`failed()` on Document. Models SHALL use `HasFactory`.

#### Scenario: Relation traversal
- **WHEN** `Document::with('chunks')->find(id)` is called
- **THEN** it returns the document with its chunks ordered by `chunk_index`

### Requirement: Migrations are reversible
The system SHALL provide `down()` methods that drop `chunks` index then `chunks` table then `documents` table so `php artisan migrate:rollback` succeeds.

#### Scenario: Rollback round-trip
- **WHEN** `migrate` then `migrate:rollback` then `migrate` runs
- **THEN** all steps succeed without orphaned types or indexes
