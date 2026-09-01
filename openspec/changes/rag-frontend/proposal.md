# Proposal: rag-frontend

## Why

Backend RAG is headless — recruiters and users need a simple upload + chat UI to demo the grounded answering. No frontend exists yet; need polling upload and cited chat aligned with FreelanceScope patterns.

## What Changes

- Next.js 15 App Router + Tailwind scaffold (`rag-frontend`) with `.env.local NEXT_PUBLIC_API_URL`
- `lib/api.ts` client: `uploadDocument`, `listDocuments`, `deleteDocument`, `askQuestion`
- Pages: `app/page.tsx` (upload + document list) + `app/chat/page.tsx` (chat thread)
- Components: `UploadForm` (file input → `uploadDocument` + poll `listDocuments` every 2s until `ready`), `DocumentList` (status badges, delete), `ChatWindow` (message list via `useState`, calls `askQuestion`, shows answer + sources)
- Loading/error states, source citation rendering

## Capabilities

### New Capabilities
- `frontend-upload`: file upload + document list with status polling
- `frontend-chat`: chat interface querying `/api/query` and rendering cited sources

### Modified Capabilities
- None

## Impact

- Affects: `rag-frontend/` repo (or monorepo package) `lib/api.ts`, `app/`, `components/`, `tailwind.config`, `package.json`, `.env.local`
- Dependencies: `rag-ingestion` (`GET/POST /documents`) + `rag-retrieval` (`POST /query`) must be live; CORS on Laravel; queue worker for status transitions
- No backend changes except CORS config if needed
