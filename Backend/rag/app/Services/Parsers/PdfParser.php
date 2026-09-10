<?php

namespace App\Services\Parsers;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

class PdfParser implements DocumentParserInterface
{
    public function parse(UploadedFile $file): string
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($file->getRealPath());

        $text = $pdf->getText();

        if (empty(trim($text))) {
            throw new \RuntimeException('PDF file contains no extractable text: '.$file->getClientOriginalName());
        }

        return $text;
    }
}
