<?php

namespace App\Services;

use App\Services\Parsers\DocumentParserInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class DocumentParser
{
    public const DEFAULT_MAX_EXTRACTED_CHARACTERS = 5_000_000;

    public function __construct(
        private readonly DocumentTypeDetector $detector,
        private readonly int $maxExtractedCharacters = self::DEFAULT_MAX_EXTRACTED_CHARACTERS,
    ) {}

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
            $text = $parser->parse($file);
        } catch (\Throwable $e) {
            Log::error('Document parsing failed', [
                'file' => $this->normalizeForLog($file->getClientOriginalName()),
                'type' => $type->value,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ParsingException('The document could not be parsed. Please check the file and try again.', previous: $e);
        }

        if (mb_strlen($text) > $this->maxExtractedCharacters) {
            throw new ParsingException('The document contains too much text to process at once. Please upload a smaller document.');
        }

        return $text;
    }

    private function normalizeForLog(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]+/', ' ', trim($value)) ?? $value;
    }
}
