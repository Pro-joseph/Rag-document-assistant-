<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocumentFixtures
{
    public static function uploadedFile(string $path, string $originalName): UploadedFile
    {
        return new UploadedFile($path, $originalName, null, null, true);
    }

    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/rag_fixture_'.uniqid();

        mkdir($dir, 0755, true);

        return $dir;
    }

    public static function pdfPath(string $text): string
    {
        $dir = self::tempDir();
        $path = $dir.'/document.pdf';

        file_put_contents($path, self::buildPdf($text));

        return $path;
    }

    public static function docxPath(string $text): string
    {
        $dir = self::tempDir();
        $path = $dir.'/document.docx';

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        foreach (explode("\n", $text) as $line) {
            $section->addText($line);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public static function cleanup(string $path): void
    {
        $dir = dirname($path);

        if (is_file($path)) {
            @unlink($path);
        }

        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    public static function buildPdf(string $text): string
    {
        $pages = array_filter(explode("\n", $text), fn (string $line): bool => trim($line) !== '');
        $pages = array_values($pages);

        $content = [];
        foreach ($pages as $index => $pageText) {
            $content[$index] = 'BT /F1 18 Tf 100 700 Td ('.$pageText.') Tj ET';
        }

        $fontObjectId = (count($content) * 2) + 3;
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $index): string => ($index + 3).' 0 R', array_keys($content))).'] /Count '.count($content).' >>',
        ];

        foreach ($content as $index => $streamContent) {
            $pageId = $index + 3;
            $contentsId = $index + 4;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 $fontObjectId 0 R >> >> /Contents $contentsId 0 R >>";
            $objects[$contentsId] = '<< /Length '.strlen($streamContent)." >>\nstream\n".$streamContent."\nendstream";
        }

        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $objectId => $body) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId." 0 obj\n".$body."\nendobj\n";
        }

        $size = $fontObjectId + 1;
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 $size\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size $size /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefOffset\n%%EOF";

        return $pdf;
    }
}
