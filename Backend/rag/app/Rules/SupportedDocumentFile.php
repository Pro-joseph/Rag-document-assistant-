<?php

namespace App\Rules;

use App\Services\DocumentTypeDetector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class SupportedDocumentFile implements ValidationRule
{
    private const SUPPORTED_EXTENSIONS = ['pdf', 'docx', 'txt', 'csv'];

    /**
     * Accept pdf/docx/txt/csv even when the server's fileinfo database
     * cannot sniff the content type (e.g. valid docx reported as
     * application/octet-stream). Falls back to extension plus the
     * DocumentTypeDetector (mime, extension, zip signature) instead of
     * rejecting legit files. Spoofed files still fail later at parsing.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute field must be a file of type: pdf, docx, txt, csv.');

            return;
        }

        if (in_array($value->guessExtension(), self::SUPPORTED_EXTENSIONS, true)) {
            return;
        }

        $detected = app(DocumentTypeDetector::class)->detect($value);

        if (! $detected->isSupported()) {
            $fail('The :attribute field must be a file of type: pdf, docx, txt, csv.');
        }
    }
}
