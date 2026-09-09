<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DocumentFixtures;

test('a file can be uploaded to the documents directory', function () {
    Storage::fake('public');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('document.txt', 100),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonStructure(['path']);

    Storage::disk('public')->assertExists($response->json('path'));
});

test('a pdf file can be uploaded', function () {
    Storage::fake('public');

    $path = DocumentFixtures::pdfPath('Hello World from PDF');

    $response = $this->postJson('/upload', [
        'file' => new UploadedFile($path, 'document.pdf', 'application/pdf', null, true),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    DocumentFixtures::cleanup($path);
});

test('a docx file can be uploaded', function () {
    Storage::fake('public');

    $path = DocumentFixtures::docxPath('Hello from DOCX test document');

    $response = $this->postJson('/upload', [
        'file' => new UploadedFile($path, 'document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    DocumentFixtures::cleanup($path);
});

test('the upload endpoint validates that a file is provided', function () {
    $this->postJson('/upload')->assertStatus(422);
});

test('an oversized file is rejected', function () {
    $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('big.bin', 11 * 1024),
    ])->assertStatus(422);
});

test('an unsupported file type is rejected', function () {
    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('image.png', 100, 'image/png'),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});
