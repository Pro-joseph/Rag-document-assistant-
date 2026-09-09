<?php

namespace App\Services;

use App\Enums\DocumentType;
use Illuminate\Http\UploadedFile;

class DocumentTypeDetector
{
    private const ZIP_MIME_TYPES = [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
    ];

    private const ZIP_SIGNATURE = "PK\x03\x04";

    public function detect(UploadedFile $file): DocumentType
    {
        $mimeType = $file->getMimeType() ?? '';
        $extension = strtolower($file->getClientOriginalExtension());

        $typeFromMime = DocumentType::fromMime($mimeType);

        if ($typeFromMime->isSupported()) {
            return $typeFromMime;
        }

        if (in_array(strtolower($mimeType), self::ZIP_MIME_TYPES, true) && $this->isDocxArchive($file)) {
            return DocumentType::Docx;
        }

        $typeFromExtension = DocumentType::fromExtension($extension);

        if ($typeFromExtension->isSupported()) {
            return $typeFromExtension;
        }

        if ($this->isDocxArchive($file)) {
            return DocumentType::Docx;
        }

        return DocumentType::Unknown;
    }

    private function isDocxArchive(UploadedFile $file): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return false;
        }

        $realPath = $file->getRealPath();

        if ($realPath === false || ! is_file($realPath)) {
            return false;
        }

        if ($this->readLeadingBytes($realPath) !== self::ZIP_SIGNATURE) {
            return false;
        }

        try {
            $zip = new \ZipArchive;

            if ($zip->open($realPath) !== true) {
                return false;
            }

            $hasDocumentXml = $zip->locateName('word/document.xml') !== false;

            $zip->close();

            return $hasDocumentXml;
        } catch (\Throwable) {
            return false;
        }
    }

    private function readLeadingBytes(string $path): string|false
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, 4);

        fclose($handle);

        return $bytes;
    }
}
