## Context

Laravel API provides `POST/GET/DELETE /api/documents` and `POST /api/query`. Need Next.js App Router frontend `rag-laravel-nextjs-guide.md:379` per portfolio pattern (Laravel API + Next.js UI like FreelanceScope). Keep simple — `useState` only, no state library `rag-laravel-nextjs-guide.md:450`.

## Goals / Non-Goals

**Goals:**
- Scaffold `npx create-next-app@latest rag-frontend --typescript --tailwind --app`
- Typed `lib/api.ts` client with `fetch`, error handling `!res.ok` throw
- `UploadForm` polls `listDocuments` until `ready`, shows pending/processing/ready/failed badges
- `ChatWindow` appends Q/A + sources to local `useState` array, scrollable thread

**Non-Goals:**
- No auth, no persistent history, no streaming, no Laravel Echo real-time (polling only v1)
- No complex state (Redux/Zustand), no SSR for chat, no file preview

## Decisions

- **Decision: Next.js App Router with `app/page.tsx` upload+list and `app/chat/page.tsx` chat** `rag-laravel-nextjs-guide.md:433` — file-based routing, keeps upload and chat separate. Alternative: single page — rejected clutter.
- **Decision: `lib/api.ts` with `API_URL = process.env.NEXT_PUBLIC_API_URL` and 4 functions** `rag-laravel-nextjs-guide.md:399` — `uploadDocument` uses `FormData`, `askQuestion` uses `JSON` body. Typed responses `{answer, sources}`. Alternative: axios — rejected extra dep, `fetch` suffices.
- **Decision: `UploadForm` polls every 2s until status != pending/processing** — replaces need for websockets v1 `rag-laravel-nextjs-guide.md:443`. Simple `setInterval` + `clearInterval` on `ready|failed`. Alternative: Laravel Echo — overkill v1, defer.
- **Decision: `ChatWindow` via `useState` message array `{role, content, sources?}`** `rag-laravel-nextjs-guide.md:447` — append on submit, no persistence per F8 scope `cahier-des-charges-rag.md:36`. Shows sources as `[n] filename — excerpt`. Alternative: localStorage history — rejected scope.
- **Decision: Tailwind for styling, no component library** — matches stack `rag-laravel-nextjs-guide.md:10`, keeps bundle small. Loading states via disabled button + spinner, errors via inline alert.
- **Decision: CORS: Laravel `cors.php` allow `NEXT_PUBLIC_API_URL` origin** — needed for `localhost:3000 → 8000` dev.
- **Decision: Keep `DocumentList` + `UploadForm` separate components** — SRP, testable, but co-located under `app/components/`.

## Risks / Trade-offs

- [Polling misses fast transition if interval too slow] → Mitigation: 2s interval, also refetch on focus/upload success.
- [CORS misconfig blocks fetch] → Mitigation: document `config/cors.php` allowed origins, test with `curl` + browser.
- [Large file upload timeout] → Mitigation: `max:20480` backend + frontend `file.size` pre-check with error before POST.
- [Chat sources overflow UI] → Mitigation: truncate `content` to 500 chars with Expand toggle.
- [Env var missing at build] → Mitigation: `.env.local` example, fallback `http://localhost:8000/api` in dev.

## Migration Plan

1. `npx create-next-app@latest rag-frontend --typescript --tailwind --app`
2. Add `.env.local` `NEXT_PUBLIC_API_URL=http://localhost:8000/api`, create `lib/api.ts`
3. Implement `components/UploadForm.tsx`, `DocumentList.tsx`, `ChatWindow.tsx` + pages
4. `npm run dev` verify upload → poll → chat → sources render
5. Dockerfile for frontend next change `rag-ops` if needed (defer)

## Open Questions

- Deploy Next.js as standalone container or Vercel? Portfolio pattern uses EC2 Docker — keep container, document both.
- Need pagination for document list? Not v1 (<100 docs).
