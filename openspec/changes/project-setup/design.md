## Context

Repo at `Rag_project/` currently contains only `openspec/`, `README.md`, `cahier-des-charges-rag.md`, `rag-laravel-nextjs-guide.md`. No executable Laravel or Next.js code exists. All 4 later changes (`rag-foundation` migrations, `rag-ingestion` upload/parse, `rag-retrieval` query, `rag-frontend` components) assume a runnable project. Need day-0 folders before any `php artisan migrate` or `npm run dev` can be verified.

## Goals / Non-Goals

**Goals:**
- Two root folders `backend/` (Laravel 13) + `frontend/` (Next.js 15 + Tailwind) side-by-side alongside `openspec/`
- Each folder independently installable (`composer install` vs `npm install`) and runnable (`php artisan serve` vs `npm run dev`)
- Base packages pre-installed so later changes don't need to re-add them
- Env examples committed, no secrets, split README commands

**Non-Goals:**
- No business logic, migrations, controllers, services, jobs, resources, or UI components — just skeleton that builds
- No Docker Compose, Terraform, or CI yet (deferred to `rag-ops`)
- No pgvector enablement beyond documenting `pgvector/pgvector:pg16` choice (done in `rag-foundation`)
- No monorepo `rag-api` vs `backend` rename debate beyond this change

## Decisions

- **Decision: `backend/` + `frontend/` at repo root, not nested nor named `rag-api`/`rag-frontend`** — Rationale: clear `docker-compose.yml` `build: ./backend` / `build: ./frontend`, matches user confirmed "yes folder for backend and folder for frontend". Alternative `rag-api`/`rag-frontend` — rejected renaming churn, keeps generic names. Easy to rename later via git mv if needed.
- **Decision: `composer create-project laravel/laravel backend --prefer-dist`** — standard Laravel scaffold, PHP 8.3, keeps `openspec/` outside Laravel root. Alternative `laravel new` installer — adds extra dependency.
- **Decision: Pre-install `smalot/pdfparser`, `phpoffice/phpword`, `guzzlehttp/guzzle` in backend skeleton** — these are required by `DocumentParser`/`EmbeddingService` (`rag-laravel-nextjs-guide.md:27`) and would otherwise be added later. Installing now makes Ticket 0 verification `composer install` already correct. Alternative defer to `rag-ingestion` — rejected would leave setup incomplete.
- **Decision: `npx create-next-app@latest frontend --typescript --tailwind --app --eslint`** — Next.js 15 App Router baseline (`rag-laravel-nextjs-guide.md:384`). Alternative `frontend/` via manual `package.json` — rejected more steps.
- **Decision: `.env.example` committed, `.env` gitignored, keys empty** — Laravel `OPENAI_API_KEY`, `GROQ_API_KEY`, `GROQ_MODEL`, `DB_CONNECTION=pgsql`; Next.js `NEXT_PUBLIC_API_URL=http://localhost:8000/api`. No hardcoding, follows `cahier-des-charges-rag.md:130` security.

## Risks / Trade-offs

- [PHP version mismatch  <8.3] → Mitigation: check `php --version` before `composer create-project`; document in README.
- [Node <20.19] → Mitigation: check `node --version` before `npx`; `openspec` already requires 20.19 so same prerequisite.
- [Composer memory/time on Windows] → Mitigation: allow `COMPOSER_MEMORY_LIMIT=-1` note; scaffold once, commit.
- [`backend/` vs `frontend/` naming vs existing docs that say `rag-api`/`rag-frontend`] → Mitigation: add note in design that rename is just folder name, internal Laravel `APP_NAME` unchanged; later `rag-foundation` tasks will reference `backend/` explicitly.
- [Two `node_modules`/`vendor` at root confusing] → Mitigation: each `.gitignore` isolates (`backend/vendor/`, `frontend/node_modules/`), root `.gitignore` covers both.

## Migration Plan

1. Run `composer create-project laravel/laravel backend --prefer-dist` at repo root (creates `backend/`). Then `cd backend && composer require smalot/pdfparser phpoffice/phpword guzzlehttp/guzzle`.
2. Run `npx create-next-app@latest frontend --typescript --tailwind --app --eslint` at repo root.
3. Copy `.env.example` templates into each folder (backend includes DB/queue/Groq/OpenAI keys; frontend includes `NEXT_PUBLIC_API_URL`).
4. Update `README` with split commands (`cd backend && composer install && php artisan serve` / `cd frontend && npm install && npm run dev`).
5. Verify `ls backend frontend openspec` all at root; `php artisan --version` and `npm run build` pass.
6. Rollback: `rm -rf backend frontend` and remove README section — safe, no DB changes.

## Open Questions

- Keep generic `backend`/`frontend` vs rename to `rag-api`/`rag-frontend` for portfolio naming? Decision: `backend`/`frontend` now, rename later is cheap `git mv`.
- Should `docker-compose.yml` be scaffolded now or deferred to `rag-ops`? Deferred — keep Ticket 0 minimal.
