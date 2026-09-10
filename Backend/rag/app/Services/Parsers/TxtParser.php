<?php

namespace App\Services\Parsers;

use Illuminate\Http\UploadedFile;

class TxtParser implements DocumentParserInterface
{
    public function parse(UploadedFile $file): string
    {
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new \RuntimeException('Failed to read TXT file: '.$file->getClientOriginalName());
        }

        return $content;
    }
}
