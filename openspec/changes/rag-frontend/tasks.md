## 1. Scaffolding

- [ ] 1.1 Run `npx create-next-app@latest rag-frontend --typescript --tailwind --app` (or in monorepo `frontend/`), verify `npm run dev` on 3000
- [ ] 1.2 Create `.env.local` with `NEXT_PUBLIC_API_URL=http://localhost:8000/api` and `.env.example`, configure `next.config.js` if needed
- [ ] 1.3 Configure Laravel `config/cors.php` `allowed_origins` to include `http://localhost:3000` and test `curl` from frontend origin

## 2. API client

- [ ] 2.1 Implement `lib/api.ts` per `rag-laravel-nextjs-guide.md:399` with `API_URL`, 4 exported functions, `fetch` + `!res.ok` throw, typed returns
- [ ] 2.2 Add unit smoke test: mock `fetch` global, call `askQuestion` returns `{answer, sources}`

## 3. Components

- [ ] 3.1 Implement `components/UploadForm.tsx` with file input, `max 20MB` pre-check, `FormData` POST, polling `setInterval(listDocuments,2000)` until `ready|failed`, loading/error states
- [ ] 3.2 Implement `components/DocumentList.tsx` with `useEffect` initial fetch, status badges (Tailwind), Delete button → `deleteDocument` + refetch
- [ ] 3.3 Implement `components/ChatWindow.tsx` with `useState` message array, input + submit → `askQuestion`, append Q/A+sources, spinner, error alert, truncated sources with Expand

## 4. Pages and verification

- [ ] 4.1 Wire `app/page.tsx` (UploadForm + DocumentList) and `app/chat/page.tsx` (ChatWindow) with `Link` navigation
- [ ] 4.2 E2E: `npm run dev` + `php artisan serve` + `php artisan queue:work` → upload pdf → see ready badge → go to /chat → ask question → see answer + [1] citation
- [ ] 4.3 Polish: Tailwind loading spinners, empty states, error boundaries, responsive layout
