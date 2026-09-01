## ADDED Requirements

### Requirement: UploadForm uploads and polls status
The system SHALL provide `components/UploadForm.tsx` with file input `accept .pdf,.docx,.txt,.csv`, client pre-check `file.size <=20*1024*1024`, on submit calls `uploadDocument(file)` via `fetch POST ${API_URL}/documents` with `FormData`, shows loading, then polls `listDocuments()` every 2s until `document.status` is `ready|failed`, updating `DocumentList`. Errors show inline alert.

#### Scenario: Successful upload shows ready
- **WHEN** user selects `sample.pdf` (1MB) and clicks Upload
- **THEN** POST 202, poll finds `ready` within seconds and list shows badge `ready`

#### Scenario: Oversized file blocked client-side
- **WHEN** user selects 25MB file
- **THEN** UploadForm shows error "File too large (max 20MB)" and does not POST

#### Scenario: Invalid type blocked
- **WHEN** user selects `.png`
- **THEN** shows error or API returns 422 and UI displays it

### Requirement: DocumentList shows status and delete
The system SHALL provide `components/DocumentList.tsx` fetching `listDocuments()` on mount and rendering `filename` + status badge (`pending|processing` spinner, `ready` green, `failed` red) sorted by `created_at DESC`, with Delete button calling `deleteDocument(id)` and refreshing list.

#### Scenario: List updates after upload
- **WHEN** one document is uploaded
- **THEN** DocumentList contains it with correct filename and status transitions visible

#### Scenario: Delete removes entry
- **WHEN** user clicks Delete on a document
- **THEN** DELETE 204 and entry disappears without full reload

### Requirement: API client typed and env-driven
`lib/api.ts` SHALL export `API_URL = process.env.NEXT_PUBLIC_API_URL` and functions `uploadDocument(file: File)`, `listDocuments()`, `deleteDocument(id)`, `askQuestion(question: string)` using `fetch` with error throw on `!res.ok` per `rag-laravel-nextjs-guide.md:399`.

#### Scenario: Client uses env URL
- **WHEN** `NEXT_PUBLIC_API_URL=http://localhost:8000/api` and `uploadDocument` is called
- **THEN** fetch URL is `http://localhost:8000/api/documents` with method POST

### Requirement: Env config documented
`.env.local` SHALL contain `NEXT_PUBLIC_API_URL` and `.env.example` SHALL document it.

#### Scenario: Env missing fallback
- **WHEN** env unset in dev
- **THEN** client defaults to `http://localhost:8000/api` and logs warning (optional)
