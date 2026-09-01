# RAG System — Laravel API + Next.js Frontend

Plan for rebuilding the RAG project on your main stack: Laravel for
ingestion/retrieval/generation, Next.js for the upload + chat UI.

## Stack

| Piece | Choice | Why |
|---|---|---|
| API | Laravel 13 | Your main stack |
| Vector storage | PostgreSQL + `pgvector` extension | Native SQL, no extra service to run, works great on RDS or a plain EC2 box |
| Embeddings | OpenAI `text-embedding-3-small` (or Voyage AI) | Groq doesn't serve an embeddings endpoint, so this is a separate small API call |
| Generation | Groq (Llama 3.x) | You already use this in FreelanceScope |
| Queue | Laravel Queues (database or Redis driver) | Ingestion (parsing + embedding) shouldn't block the request |
| File parsing | `smalot/pdfparser` (PDF), `phpoffice/phpword` (DOCX), native PHP (TXT/CSV) | Standard Laravel-ecosystem packages |
| Frontend | Next.js (App Router) + Tailwind | Upload UI + chat interface |

---

## Part 1 — Laravel API

### 1. Scaffold the project

```bash
composer create-project laravel/laravel rag-api
cd rag-api
composer require smalot/pdfparser phpoffice/phpword guzzlehttp/guzzle
```

### 2. Database: enable pgvector

Requires Postgres (RDS or self-hosted) with the `pgvector` extension installed.

```bash
# on the DB server, one-time
CREATE EXTENSION IF NOT EXISTS vector;
```

Update `.env` to point `DB_CONNECTION=pgsql` at that database.

### 3. Migrations

**documents table** — one row per uploaded file:

```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('filename');
    $table->string('status')->default('pending'); // pending | processing | ready | failed
    $table->timestamps();
});
```

**chunks table** — one row per text chunk, with its embedding:

```php
Schema::create('chunks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained()->cascadeOnDelete();
    $table->integer('chunk_index');
    $table->text('content');
    $table->timestamps();
});

// then a raw statement (pgvector has no native Laravel column type):
DB::statement('ALTER TABLE chunks ADD COLUMN embedding vector(1536)');
DB::statement('CREATE INDEX chunks_embedding_idx ON chunks USING ivfflat (embedding vector_cosine_ops)');
```

(1536 = dimension of `text-embedding-3-small`; adjust if you pick a different model.)

### 4. Config

Add to `config/services.php`:

```php
'openai' => ['key' => env('OPENAI_API_KEY')],
'groq' => ['key' => env('GROQ_API_KEY'), 'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile')],
```

### 5. File parsing service

`app/Services/DocumentParser.php` — one method per file type, dispatched by extension:

```php
class DocumentParser
{
    public function parse(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => $this->parsePdf($path),
            'docx' => $this->parseDocx($path),
            'txt' => file_get_contents($path),
            'csv' => $this->parseCsv($path),
            default => throw new \InvalidArgumentException("Unsupported file type: $extension"),
        };
    }

    private function parsePdf(string $path): string
    {
        $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
        return $pdf->getText();
    }

    private function parseDocx(string $path): string
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    }

    private function parseCsv(string $path): string
    {
        $rows = array_map('str_getcsv', file($path));
        return implode("\n", array_map(fn ($row) => implode(', ', $row), $rows));
    }
}
```

### 6. Chunking service

`app/Services/TextChunker.php` — split on paragraph/sentence boundaries with overlap (same logic as the Python version, ported to PHP):

```php
class TextChunker
{
    public function chunk(string $text, int $chunkSize = 800, int $overlap = 150): array
    {
        $text = trim($text);
        if ($text === '') return [];

        $pieces = $this->splitIntoPieces($text, $chunkSize, ["\n\n", "\n", '. ', ' ']);
        return $this->mergePieces($pieces, $chunkSize, $overlap);
    }

    private function splitIntoPieces(string $text, int $chunkSize, array $separators): array
    {
        $text = trim($text);
        if ($text === '') return [];
        if (mb_strlen($text) <= $chunkSize) return [$text];

        if (empty($separators)) {
            return mb_str_split($text, $chunkSize);
        }

        $sep = array_shift($separators);
        $parts = explode($sep, $text);

        $pieces = [];
        foreach ($parts as $part) {
            $pieces = array_merge($pieces, $this->splitIntoPieces($part, $chunkSize, $separators));
        }
        return $pieces;
    }

    private function mergePieces(array $pieces, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            $candidate = $current ? trim("$current $piece") : $piece;
            if (mb_strlen($candidate) <= $chunkSize) {
                $current = $candidate;
                continue;
            }

            if ($current) $chunks[] = $current;
            $tail = $overlap ? mb_substr($current, -$overlap) : '';
            $current = $tail ? trim("$tail $piece") : $piece;

            while (mb_strlen($current) > $chunkSize) {
                $chunks[] = mb_substr($current, 0, $chunkSize);
                $current = $overlap
                    ? mb_substr($current, $chunkSize - $overlap)
                    : mb_substr($current, $chunkSize);
            }
        }

        if ($current) $chunks[] = $current;
        return array_values(array_filter(array_map('trim', $chunks)));
    }
}
```

> This keeps the same two-phase split-then-merge design as the Python
> version — that design fixed a real bug (an oversized block bypassing
> the size limit), so keep it intact rather than simplifying it.

### 7. Embedding service

`app/Services/EmbeddingService.php`:

```php
class EmbeddingService
{
    public function embed(array $texts): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $texts,
            ])
            ->throw()
            ->json();

        return array_map(fn ($item) => $item['embedding'], $response['data']);
    }
}
```

### 8. Ingestion job (queued)

`app/Jobs/IngestDocument.php`:

```php
class IngestDocument implements ShouldQueue
{
    public function __construct(public Document $document, public string $filePath) {}

    public function handle(DocumentParser $parser, TextChunker $chunker, EmbeddingService $embedder): void
    {
        $this->document->update(['status' => 'processing']);

        $text = $parser->parse($this->filePath, pathinfo($this->filePath, PATHINFO_EXTENSION));
        $chunks = $chunker->chunk($text);
        $vectors = $embedder->embed($chunks);

        foreach ($chunks as $i => $content) {
            $embeddingLiteral = '[' . implode(',', $vectors[$i]) . ']';
            DB::statement(
                'INSERT INTO chunks (document_id, chunk_index, content, embedding, created_at, updated_at)
                 VALUES (?, ?, ?, ?::vector, now(), now())',
                [$this->document->id, $i, $content, $embeddingLiteral]
            );
        }

        $this->document->update(['status' => 'ready']);
    }
}
```

### 9. Retrieval

`app/Services/Retriever.php` — cosine similarity search via pgvector's `<=>` operator:

```php
class Retriever
{
    public function __construct(private EmbeddingService $embedder) {}

    public function search(string $question, int $topK = 4): array
    {
        $queryVector = $this->embedder->embed([$question])[0];
        $literal = '[' . implode(',', $queryVector) . ']';

        return DB::select(
            'SELECT chunks.content, documents.filename,
                    1 - (chunks.embedding <=> ?::vector) AS score
             FROM chunks
             JOIN documents ON documents.id = chunks.document_id
             ORDER BY chunks.embedding <=> ?::vector
             LIMIT ?',
            [$literal, $literal, $topK]
        );
    }
}
```

### 10. Generation (Groq)

`app/Services/AnswerGenerator.php`:

```php
class AnswerGenerator
{
    public function generate(string $question, array $hits): string
    {
        $context = collect($hits)
            ->map(fn ($h, $i) => "[" . ($i + 1) . "] (source: {$h->filename})\n{$h->content}")
            ->implode("\n\n");

        $prompt = $context
            ? "Context:\n$context\n\nQuestion: $question\n\nAnswer using only the context above, citing [source] numbers."
            : "No documents ingested yet.\n\nQuestion: $question";

        $response = Http::withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Answer only from the given context. If the answer is not in it, say you don\'t know.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ])
            ->throw()
            ->json();

        return $response['choices'][0]['message']['content'];
    }
}
```

### 11. Controllers + routes

```php
// routes/api.php
Route::post('/documents', [DocumentController::class, 'store']);
Route::get('/documents', [DocumentController::class, 'index']);
Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
Route::post('/query', [QueryController::class, 'ask']);
```

```php
class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:pdf,docx,txt,csv|max:20480']);

        $file = $request->file('file');
        $path = $file->store('uploads');
        $document = Document::create(['filename' => $file->getClientOriginalName()]);

        IngestDocument::dispatch($document, Storage::path($path));

        return response()->json($document, 202);
    }

    public function index()
    {
        return Document::orderByDesc('created_at')->get();
    }

    public function destroy(Document $document)
    {
        $document->delete(); // cascades to chunks via FK
        return response()->noContent();
    }
}
```

```php
class QueryController extends Controller
{
    public function ask(Request $request, Retriever $retriever, AnswerGenerator $generator)
    {
        $request->validate(['question' => 'required|string']);

        $hits = $retriever->search($request->question);
        $answer = $generator->generate($request->question, $hits);

        return response()->json(['answer' => $answer, 'sources' => $hits]);
    }
}
```

Since ingestion is queued, either poll `GET /documents` until `status`
is `ready`, or broadcast a Laravel Echo event on completion if you want
real-time status in the frontend.

### 12. Run it

```bash
php artisan migrate
php artisan queue:work   # separate process/container for ingestion jobs
php artisan serve
```

---

## Part 2 — Next.js Frontend

### 1. Scaffold

```bash
npx create-next-app@latest rag-frontend --typescript --tailwind --app
cd rag-frontend
```

### 2. Env config

`.env.local`:
```
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### 3. API client

`lib/api.ts`:

```ts
const API_URL = process.env.NEXT_PUBLIC_API_URL;

export async function uploadDocument(file: File) {
  const formData = new FormData();
  formData.append('file', file);
  const res = await fetch(`${API_URL}/documents`, { method: 'POST', body: formData });
  if (!res.ok) throw new Error('Upload failed');
  return res.json();
}

export async function listDocuments() {
  const res = await fetch(`${API_URL}/documents`);
  return res.json();
}

export async function deleteDocument(id: number) {
  await fetch(`${API_URL}/documents/${id}`, { method: 'DELETE' });
}

export async function askQuestion(question: string) {
  const res = await fetch(`${API_URL}/query`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ question }),
  });
  if (!res.ok) throw new Error('Query failed');
  return res.json();
}
```

### 4. Pages (App Router)

```
app/
  page.tsx              -> upload + document list
  chat/page.tsx          -> chat interface
  components/
    UploadForm.tsx
    DocumentList.tsx
    ChatWindow.tsx
```

`components/UploadForm.tsx` — file input, calls `uploadDocument`,
polls `listDocuments` every couple seconds until status is `ready`.

`components/ChatWindow.tsx` — text input + message list; on submit,
calls `askQuestion`, appends the question and the returned answer +
sources to local state (`useState` array of messages).

Keep both intentionally simple at first — a form, a list, a scrollable
message thread. No need for a state library at this scale; `useState`
is enough.

### 5. Run it

```bash
npm run dev
```

---

## Suggested build order

1. Laravel: migrations + pgvector extension working, confirm you can
   insert and query a vector by hand in `psql`
2. Laravel: parsing + chunking services, unit test both in isolation
3. Laravel: embedding + ingestion job, confirm a file goes from
   `pending` → `ready` with chunks in the DB
4. Laravel: retrieval + generation, confirm `/query` returns a
   grounded answer via `curl` or Postman
5. Next.js: upload form talking to `/documents`
6. Next.js: chat window talking to `/query`
7. Polish: loading states, error handling, source citations in the UI

## Deployment notes

- Matches your existing pattern: Docker containers for API, queue
  worker, and frontend; Terraform for the EC2 + RDS Postgres
  provisioning; GitHub Actions for CI/CD across the repos
- pgvector needs to be enabled on whichever Postgres instance you use
  (RDS supports it natively on newer engine versions — check before
  provisioning)
- Queue worker needs to run as its own long-lived process (systemd
  service or separate container), not just `php artisan serve`
