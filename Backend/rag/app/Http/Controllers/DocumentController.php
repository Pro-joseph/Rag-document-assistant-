<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\IngestDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('uploads');
        $document = Document::create([
            'filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'status' => 'pending',
        ]);

        $chunks = [];
        if (in_array(strtolower($file->getClientOriginalExtension()), ['txt', 'csv'], true)) {
            $content = trim((string) file_get_contents(Storage::path($path)));
            if ($content !== '') {
                $chunks = [$content];
            }
        }

        IngestDocument::dispatch($document, $chunks);

        return (new DocumentResource($document))->response()->setStatusCode(202);
    }

    public function index(): AnonymousResourceCollection
    {
        return DocumentResource::collection(Document::orderByDesc('created_at')->get());
    }

    public function destroy(Document $document): Response
    {
        if ($document->storage_path !== null && Storage::exists($document->storage_path)) {
            Storage::delete($document->storage_path);
        }

        $document->delete();

        return response()->noContent();
    }
}
