<?php

namespace App\Services\Parsers;

use Illuminate\Http\UploadedFile;

interface DocumentParserInterface
{
    public function parse(UploadedFile $file): string;
}
