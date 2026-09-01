## 1. Requests and Resources

- [ ] 1.1 Create `StoreDocumentRequest` (`php artisan make:request StoreDocumentRequest`) with `authorize()->true`, rules `file=>required|file|mimes:pdf,docx,txt,csv|max:20480`, custom messages, and no other logic
- [ ] 1.2 Create `DocumentResource` (`php artisan make:resource DocumentResource`) mapping `id,filename,status,created_at,updated_at`
- [ ] 1.3 Verify 422 on invalid mime/size via feature test with `Storage::fake`

## 2. Services

- [ ] 2.1 Implement `App\Services\DocumentParser` per spec with `parse`, `parsePdf`, `parseDocx`, `parseCsv`; add `composer require smalot/pdfparser phpoffice/phpword`; unit test with fixtures (pdf/docx/txt/csv/unsupported)
- [ ] 2.2 Implement `App\Services\TextChunker` preserving two-phase split-then-merge, `mb_strlen`/`mb_substr`, overlap handling; unit test empty, small, boundary, oversized-piece, overlap scenarios
- [ ] 2.3 Implement `App\Services\EmbeddingService` with `Http::withToken` batch call; add `services.openai.key` config; feature test with `Http::fake`

## 3. Job and Controller

- [ ] 3.1 Create `App\Jobs\IngestDocument` (`ShouldQueue`, `Dispatchable`, `InteractsWithQueue`) with status `processing`→`ready`/`failed` handling, `DB::statement` vector insert, try/catch to set `failed`; test with fake queue
- [ ] 3.2 Implement `DocumentController` thin: `store(StoreDocumentRequest)` → `store('uploads')`, `Document::create`, `IngestDocument::dispatch($doc, Storage::path($path))`, return `DocumentResource` 202; `index()` → `DocumentResource::collection(Document::orderByDesc('created_at')->get())`; `destroy(Document $document)` → `Storage::delete` + `$document->delete` → 204
- [ ] 3.3 Register `routes/api.php`: `POST /documents`, `GET /documents`, `DELETE /documents/{document}`

## 4. Integration verification

- [ ] 4.1 Run `php artisan queue:work --once` manually: `curl -F file=@sample.pdf POST /api/documents` → 202, poll `GET /api/documents` until `ready`, assert `chunks` count >0
- [ ] 4.2 Negative: upload corrupt PDF → status `failed` without crashing worker
- [ ] 4.3 Delete flow: DELETE then `GET` shows gone and chunks count 0
