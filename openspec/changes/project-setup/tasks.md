## 1. Backend folder

- [ ] 1.1 Run `composer create-project laravel/laravel backend --prefer-dist` at repo root (PHP 8.3, Laravel 13); verify `cd backend && php --version && php artisan --version`
- [ ] 1.2 Inside `backend/`, run `composer require smalot/pdfparser phpoffice/phpword guzzlehttp/guzzle` and verify `composer show` lists them
- [ ] 1.3 Create/update `backend/.env.example` with `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=rag`, `QUEUE_CONNECTION=database`, `OPENAI_API_KEY=`, `GROQ_API_KEY=`, `GROQ_MODEL=llama-3.3-70b-versatile`, `APP_URL=http://localhost:8000`
- [ ] 1.4 Ensure `backend/.gitignore` covers `vendor/`, `.env`, and keep `openspec/` at root (not inside `backend/`)

## 2. Frontend folder

- [ ] 2.1 Run `npx create-next-app@latest frontend --typescript --tailwind --app --eslint` at repo root (Node ≥20.19); verify `cd frontend && node --version`
- [ ] 2.2 Create `frontend/.env.local` and `frontend/.env.example` each containing `NEXT_PUBLIC_API_URL=http://localhost:8000/api`
- [ ] 2.3 Verify `cd frontend && npm install && npm run build` succeeds; keep Tailwind config as generated

## 3. Docs and verification

- [ ] 3.1 Update `README.md` with split setup section: backend `cd backend && composer install && php artisan serve` vs frontend `cd frontend && npm install && npm run dev`
- [ ] 3.2 Verify `ls -la` at repo root shows `backend/` + `frontend/` + `openspec/` + `README.md` side-by-side
- [ ] 3.3 Verify cross-folder smoke: `cd backend && php artisan migrate:status` (pending slot) and `cd frontend && npm run build` both pass; run `openspec status --change project-setup` shows 4/4
