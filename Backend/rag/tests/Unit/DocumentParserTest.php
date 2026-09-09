<?php

use App\Services\DocumentParser;
use App\Services\DocumentTypeDetector;
use App\Services\Parsers\CsvParser;
use App\Services\Parsers\DocxParser;
use App\Services\Parsers\PdfParser;
use App\Services\Parsers\TxtParser;
use App\Services\ParsingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\Support\DocumentFixtures;

test('txt parser extracts text content', function () {
    $fixture = __DIR__.'/../Fixtures/sample.txt';
    $file = DocumentFixtures::uploadedFile($fixture, 'sample.txt');

    $content = (new TxtParser)->parse($file);

    expect($content)->toContain('sample text file')
        ->and($content)->toContain('Hello from the test document');
});

test('csv parser extracts content with headers and rows', function () {
    $fixture = __DIR__.'/../Fixtures/sample.csv';
    $file = DocumentFixtures::uploadedFile($fixture, 'sample.csv');

    $content = (new CsvParser)->parse($file);

    expect($content)->toContain('Headers: Name, Age, City')
        ->and($content)->toContain('Row 1: Name: Alice')
        ->and($content)->toContain('Row 2: Name: Bob')
        ->and($content)->toContain('City: Rabat');
});

test('pdf parser extracts text from pdf', function () {
    $path = DocumentFixtures::pdfPath('Hello World from PDF');
    $file = DocumentFixtures::uploadedFile($path, 'document.pdf');

    $content = (new PdfParser)->parse($file);

    expect($content)->toContain('Hello World from PDF');

    DocumentFixtures::cleanup($path);
});

test('docx parser extracts text from docx', function () {
    $path = DocumentFixtures::docxPath("Hello from DOCX test document\nSecond line of content");
    $file = DocumentFixtures::uploadedFile($path, 'document.docx');

    $content = (new DocxParser)->parse($file);

    expect($content)->toContain('Hello from DOCX test document')
        ->and($content)->toContain('Second line of content');

    DocumentFixtures::cleanup($path);
});

test('document parser throws parsing exception for unsupported type', function () {
    $file = UploadedFile::fake()->createWithContent('test.exe', 'binary content');

    $parser = new DocumentParser(new DocumentTypeDetector);

    try {
        $parser->parse($file);
        $this->fail('Expected ParsingException was not thrown');
    } catch (ParsingException $e) {
        expect($e->getMessage())->toContain('Unsupported document type');
    }
});

test('document parser throws parsing exception for corrupted file and logs the error', function () {
    Log::spy();

    $file = UploadedFile::fake()->createWithContent('corrupt.pdf', 'not a real pdf');

    $parser = new DocumentParser(new DocumentTypeDetector);

    try {
        $parser->parse($file);
        $this->fail('Expected ParsingException was not thrown');
    } catch (ParsingException $e) {
        expect($e->getMessage())->toContain('could not be parsed');
    }

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Document parsing failed'));
});

test('document parser sanitizes filenames written to the logs', function () {
    Log::spy();

    $file = UploadedFile::fake()
        ->createWithContent("corrupt\nfilename.pdf", 'not a real pdf');

    $parser = new DocumentParser(new DocumentTypeDetector);

    try {
        $parser->parse($file);
        $this->fail('Expected ParsingException was not thrown');
    } catch (ParsingException $e) {
        expect($e->getMessage())->toBe('The document could not be parsed. Please check the file and try again.');
    }

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'Document parsing failed')
                && str_contains($context['file'], 'filename.pdf')
                && ! str_contains($context['file'], "\n");
        });
});

test('document parser parses valid txt through full pipeline', function () {
    $fixture = __DIR__.'/../Fixtures/sample.txt';
    $file = DocumentFixtures::uploadedFile($fixture, 'sample.txt');

    $content = (new DocumentParser(new DocumentTypeDetector))->parse($file);

    expect($content)->toContain('sample text file');
});

test('document parser parses valid csv through full pipeline', function () {
    $fixture = __DIR__.'/../Fixtures/sample.csv';
    $file = DocumentFixtures::uploadedFile($fixture, 'sample.csv');

    $content = (new DocumentParser(new DocumentTypeDetector))->parse($file);

    expect($content)->toContain('Headers: Name, Age, City');
});
