<?php

namespace App\Services\Parsers;

use Illuminate\Http\UploadedFile;

class CsvParser implements DocumentParserInterface
{
    public function parse(UploadedFile $file): string
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open CSV file: '.$file->getClientOriginalName());
        }

        $rows = [];
        while (($row = fgetcsv($handle, separator: ',', enclosure: '"', escape: '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        if (empty($rows)) {
            return '';
        }

        $output = [];
        $headers = array_shift($rows);
        $output[] = 'Headers: '.implode(', ', $headers);

        foreach ($rows as $index => $row) {
            $pairs = [];
            foreach ($headers as $colIndex => $header) {
                $value = $row[$colIndex] ?? '';
                $pairs[] = $header.': '.$value;
            }
            $output[] = 'Row '.($index + 1).': '.implode(' | ', $pairs);
        }

        return implode("\n", $output);
    }
}
