# Proposal: project-setup

## Why

No runnable `backend/` or `frontend/` folders exist yet — every feature (upload, chunk, query, chat) depends on a day-0 skeleton. Need side-by-side folders at the repo root so `composer install && php artisan serve` and `npm install && npm run dev` both work before any business coding starts.

## What Changes

- Create `backend/` at repository root via `composer create-project laravel/laravel` (Laravel 13, PHP 8.3) + base packages (`smalot/pdfparser`, `phpoffice/phpword`, `guzzlehttp/guzzle`) and seed `.env.example` (pgsql, queue, openai/groq keys empty)
- Create `frontend/` at repository root via `npx create-next-app@latest --typescript --tailwind --app` (Next.js 15) + `.env.local` / `.env.example` with `NEXT_PUBLIC_API_URL`
- Document split run commands in `README` (`cd backend && ...` vs `cd frontend && ...`)
- Leave business features out — this only makes `migrate:status` and `npm run build` pass

## Capabilities

### New Capabilities
- `project-setup`: two-folder skeleton (`backend/` + `frontend/`) at repo root, each independently installable and runnable

### Modified Capabilities
- None (greenfield pre-requisite, blocks all other changes)

## Impact

- Affects: root `backend/` and `frontend/` folders, `README.md` split instructions, `.gitignore` entries for each
- Dependencies: Node ≥20.19, PHP 8.3, Composer, Postgres (or Docker) — no runtime dependency on `rag-foundation` migrations yet
- Blocks: `rag-foundation` (migrations), `rag-ingestion` (parser packages), `rag-retrieval`, `rag-frontend` (components) — Epic 0 Highest priority
