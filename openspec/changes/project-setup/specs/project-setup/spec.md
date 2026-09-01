## ADDED Requirements

### Requirement: Backend folder scaffolded
The system SHALL provide `backend/` at repository root containing a Laravel 13 skeleton (PHP 8.3) with `composer.json`, `config/services.php`, `routes/api.php` stub, `database/migrations/` directory, `backend/.env.example` (containing `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `QUEUE_CONNECTION=database`, `OPENAI_API_KEY=`, `GROQ_API_KEY=`, `GROQ_MODEL=llama-3.3-70b-versatile`, `APP_URL=http://localhost:8000`), and base packages `smalot/pdfparser`, `phpoffice/phpword`, `guzzlehttp/guzzle` listed in `composer.json`. No business routes/controllers are required at this stage.

#### Scenario: Backend runnable after install
- **WHEN** `cd backend && composer install && php artisan --version` is run
- **THEN** it prints a Laravel 13 version without "missing package" or "class not found" error

#### Scenario: Backend migrate slot ready
- **WHEN** `cd backend && php artisan migrate:status` is run with a configured pgvector Postgres
- **THEN** it shows pending migrations slot (or "No migrations found") and does not error about DB connection or missing extension yet

#### Scenario: Backend env example contains required keys
- **WHEN** `backend/.env.example` is inspected
- **THEN** it contains lines for `DB_CONNECTION`, `QUEUE_CONNECTION`, `OPENAI_API_KEY`, `GROQ_API_KEY`, `GROQ_MODEL=llama-3.3-70b-versatile` with empty values (no hardcoded secrets)

### Requirement: Frontend folder scaffolded
The system SHALL provide `frontend/` at repository root containing a Next.js 15 App Router + Tailwind + TypeScript skeleton created via `create-next-app` with `package.json`, `app/page.tsx` stub, `frontend/.env.example` and `frontend/.env.local` containing `NEXT_PUBLIC_API_URL=http://localhost:8000/api`, and Tailwind config.

#### Scenario: Frontend runnable after install
- **WHEN** `cd frontend && npm install && npm run build` is run
- **THEN** build succeeds without "module not found" and `npm run dev` starts on http://localhost:3000

#### Scenario: Frontend env contains API URL
- **WHEN** `frontend/.env.example` and `frontend/.env.local` are inspected
- **THEN** they contain `NEXT_PUBLIC_API_URL=http://localhost:8000/api`

### Requirement: Two folders coexist at root with split docs
The system SHALL have both `backend/` and `frontend/` existing side-by-side at repository root alongside `openspec/` and `README.md`, each with its own `.gitignore` isolating `backend/vendor/` and `frontend/node_modules/`. `README.md` SHALL list separate commands `cd backend && composer install && php artisan serve` and `cd frontend && npm install && npm run dev`.

#### Scenario: Two folders at root
- **WHEN** `ls -la` at repository root is inspected
- **THEN** entries `backend/` and `frontend/` both exist as directories alongside `openspec/`

#### Scenario: Docs describe split commands
- **WHEN** `README.md` is read
- **THEN** it contains a section with separate backend and frontend setup commands as above
