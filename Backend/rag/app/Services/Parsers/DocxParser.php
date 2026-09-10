<?php

namespace App\Services\Parsers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;

class DocxParser implements DocumentParserInterface
{
    public function parse(UploadedFile $file): string
    {
        $phpWord = IOFactory::load($file->getRealPath());

        if ($phpWord === null) {
            throw new \RuntimeException('Failed to read DOCX file: '.$file->getClientOriginalName());
        }

        $textParts = [];

        foreach ($phpWord->getSections() as $section) {
            $this->extractText($section->getElements(), $textParts);
        }

        $text = implode("\n", array_filter($textParts, fn (string $part): bool => trim($part) !== ''));

        if (empty(trim($text))) {
            throw new \RuntimeException('DOCX file contains no extractable text: '.$file->getClientOriginalName());
        }

        return $text;
    }

    /**
     * @param  array<int, object>  $elements
     * @param  array<int, string>  $textParts
     */
    private function extractText(array $elements, array &$textParts): void
    {
        foreach ($elements as $element) {
            if (method_exists($element, 'getElements')) {
                $this->extractText($element->getElements(), $textParts);

                continue;
            }

            if (method_exists($element, 'getText')) {
                $textParts[] = $element->getText();
            }
        }
    }
}
