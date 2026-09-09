<?php

namespace App\Enums;

use App\Services\Parsers\CsvParser;
use App\Services\Parsers\DocxParser;
use App\Services\Parsers\PdfParser;
use App\Services\Parsers\TxtParser;

enum DocumentType: string
{
    case Pdf = 'pdf';

    case Docx = 'docx';

    case Txt = 'txt';

    case Csv = 'csv';

    case Unknown = 'unknown';

    public static function fromExtension(string $extension): self
    {
        return match (strtolower($extension)) {
            'pdf' => self::Pdf,
            'docx' => self::Docx,
            'txt' => self::Txt,
            'csv' => self::Csv,
            default => self::Unknown,
        };
    }

    public static function fromMime(string $mimeType): self
    {
        return match (strtolower($mimeType)) {
            'application/pdf' => self::Pdf,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::Docx,
            'text/plain' => self::Txt,
            'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel' => self::Csv,
            default => self::Unknown,
        };
    }

    public function isSupported(): bool
    {
        return $this !== self::Unknown;
    }

    public function parserClass(): ?string
    {
        return match ($this) {
            self::Pdf => PdfParser::class,
            self::Docx => DocxParser::class,
            self::Txt => TxtParser::class,
            self::Csv => CsvParser::class,
            self::Unknown => null,
        };
    }
}
