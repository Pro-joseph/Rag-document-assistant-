<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Services\DocumentChunker;
use App\Services\DocumentParser;
use App\Services\DocumentTypeDetector;
use App\Services\ParsingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadController extends Controller
{
    public function __construct(
        private readonly DocumentTypeDetector $documentTypeDetector,
        private readonly DocumentParser $documentParser,
        private readonly DocumentChunker $documentChunker,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['file'];
        $documentType = $this->documentTypeDetector->detect($file);

        if ($documentType === DocumentType::Unknown) {
            throw ValidationException::withMessages([
                'file' => 'The file must be a supported document (pdf, docx, txt, csv).',
            ]);
        }

        try {
            $extractedContent = $this->documentParser->parse($file);
        } catch (ParsingException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $path = $file->store('documents', 'local');

        if (! Str::startsWith($path, 'documents/')) {
            abort(422, 'Invalid file path.');
        }

        return response()->json([
            'status' => 'success',
            'path' => $path,
            'type' => $documentType->value,
            'content' => $extractedContent,
            'chunks' => $this->documentChunker->chunk($extractedContent),
        ]);
    }
}
