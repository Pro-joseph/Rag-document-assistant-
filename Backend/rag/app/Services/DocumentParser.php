<?php

namespace App\Services;

use App\Services\Parsers\DocumentParserInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class DocumentParser
{
    public function __construct(private readonly DocumentTypeDetector $detector) {}

    public function parse(UploadedFile $file): string
    {
        $type = $this->detector->detect($file);

        if (! $type->isSupported()) {
            throw new ParsingException('Unsupported document type. Supported formats: PDF, DOCX, TXT, CSV.');
        }

        $parserClass = $type->parserClass();

        if ($parserClass === null) {
            throw new ParsingException('No parser available for document type: '.$type->value);
        }

        /** @var DocumentParserInterface $parser */
        $parser = new $parserClass;

        try {
            return $parser->parse($file);
        } catch (\Throwable $e) {
            Log::error('Document parsing failed', [
                'file' => $this->normalizeForLog($file->getClientOriginalName()),
                'type' => $type->value,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ParsingException('The document could not be parsed. Please check the file and try again.', previous: $e);
        }
    }

    private function normalizeForLog(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]+/', ' ', trim($value)) ?? $value;
    }
}
