## ADDED Requirements

### Requirement: ChatWindow queries and renders cited sources
The system SHALL provide `components/ChatWindow.tsx` with text input + message list `useState<{role:'user'|'assistant', content:string, sources?:{filename,content,score}[] }[]>`. On submit calls `askQuestion(question)` via `POST ${API_URL}/query` with JSON `{question}`, appends user message then assistant message with `answer` and renders `sources` as numbered list `[n] filename — truncated content` with score.

#### Scenario: Question returns answer with sources
- **WHEN** user types "What is revenue?" and submits with indexed doc containing revenue
- **THEN** chat shows user bubble, then assistant bubble with answer containing grounded phrase and sources list with filename

#### Scenario: No relevant context shows don't know
- **WHEN** user asks unrelated question with no relevant chunks
- **THEN** assistant message states lack of context (e.g., "I don't know" or "No documents...") and sources empty or shown as not relevant

#### Scenario: Loading and error states
- **WHEN** question is submitted
- **THEN** input disabled, spinner shown until response; on fetch error shows "Query failed" alert without losing history

### Requirement: Simple state without external library
Chat state SHALL be local `useState` only, no Redux/Zustand, not persisted between sessions per v1 scope.

#### Scenario: No persistence on refresh
- **WHEN** page is refreshed
- **THEN** chat history is cleared (expected v1, no localStorage)

### Requirement: Pages wired
`app/page.tsx` SHALL render `UploadForm` + `DocumentList`, `app/chat/page.tsx` SHALL render `ChatWindow`; navigation between them via Next Link.

#### Scenario: Navigation
- **WHEN** user visits `/` then navigates to `/chat`
- **THEN** upload list is visible on `/` and chat input on `/chat`

### Requirement: Styling and run
Frontend SHALL use Tailwind, `npm run dev` starts on `http://localhost:3000`, and CORS SHALL be configured on Laravel to allow that origin.

#### Scenario: Dev run
- **WHEN** `npm run dev` and `php artisan serve` both run
- **THEN** upload and query flows work end-to-end without CORS error
