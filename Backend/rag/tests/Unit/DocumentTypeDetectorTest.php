<?php

use App\Enums\DocumentType;
use App\Services\DocumentTypeDetector;
use Illuminate\Http\UploadedFile;
use Tests\Support\DocumentFixtures;

test('detects pdf type', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent(
        'document.pdf',
        DocumentFixtures::buildPdf('Hello from PDF')
    );

    expect($detector->detect($file))->toBe(DocumentType::Pdf);
});

test('detects docx type from mime type', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent(
        'document.docx',
        'irrelevant content'
    )->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($detector->detect($file))->toBe(DocumentType::Docx);
});

test('detects docx type from zip container signature', function () {
    $detector = new DocumentTypeDetector;

    $path = DocumentFixtures::docxPath('Hello document');
    $file = new UploadedFile($path, 'unknown.bin', 'application/zip', null, true);

    expect($detector->detect($file))->toBe(DocumentType::Docx);

    DocumentFixtures::cleanup($path);
});

test('detects txt type', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent('document.txt', 'hello world');

    expect($detector->detect($file))->toBe(DocumentType::Txt);
});

test('detects csv type', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent('document.csv', 'a,b,c');

    expect($detector->detect($file))->toBe(DocumentType::Csv);
});

test('returns unknown for unsupported type', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent('document.exe', 'binary content');

    expect($detector->detect($file))->toBe(DocumentType::Unknown);
});

test('detection never throws on unreadable or malformed files', function () {
    $detector = new DocumentTypeDetector;

    $file = UploadedFile::fake()->createWithContent('document.bin', 'not a zip at all');

    expect($detector->detect($file))->toBe(DocumentType::Unknown);
});
