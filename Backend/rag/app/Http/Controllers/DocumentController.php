<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\IngestDocument;
use App\Models\Document;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $file = $request->file('file');
        $path = $file->store('uploads');
        $document = Document::create(['filename' => $file->getClientOriginalName()]);

        $chunks = [];
        if (in_array(strtolower($file->getClientOriginalExtension()), ['txt', 'csv'], true)) {
            $content = trim((string) file_get_contents(Storage::path($path)));
            if ($content !== '') {
                $chunks = [$content];
            }
        }

        IngestDocument::dispatch($document, $chunks);

        return (new DocumentResource($document))->additional(['meta' => ['storage_path' => $path]]);
    }

    public function index(): AnonymousResourceCollection
    {
        return DocumentResource::collection(Document::orderByDesc('created_at')->get());
    }

    public function destroy(Document $document): Response
    {
        $document->delete();

        return response()->noContent();
    }
}
