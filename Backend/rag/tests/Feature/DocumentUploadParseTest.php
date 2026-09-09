<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DocumentFixtures;

test('upload returns document type and parsed content for txt', function () {
    Storage::fake('local');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('sample.txt', 'Hello world from txt'),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'type' => 'txt',
            'content' => 'Hello world from txt',
        ])
        ->assertJsonStructure(['path']);
});

test('upload stores the file and returns the correct path', function () {
    Storage::fake('local');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('report.txt', 'report content'),
    ]);

    $response->assertOk();

    Storage::disk('local')->assertExists($response->json('path'));
});

test('upload returns document type and parsed content for csv', function () {
    Storage::fake('local');

    $csvContent = "Name,Age\nAlice,20\nBob,22";

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('data.csv', $csvContent),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'type' => 'csv',
        ]);

    expect($response->json('content'))->toContain('Headers: Name, Age')
        ->and($response->json('content'))->toContain('Alice')
        ->and($response->json('content'))->toContain('Bob');
});

test('upload returns parsed content for a real pdf', function () {
    Storage::fake('local');

    $path = DocumentFixtures::pdfPath('Hello World from PDF');
    $file = new UploadedFile($path, 'document.pdf', 'application/pdf', null, true);

    $response = $this->postJson('/upload', [
        'file' => $file,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'type' => 'pdf',
        ]);

    expect($response->json('content'))->toContain('Hello World from PDF');

    DocumentFixtures::cleanup($path);
});

test('upload returns parsed content for a real docx', function () {
    Storage::fake('local');

    $path = DocumentFixtures::docxPath('Hello from DOCX test document');
    $file = new UploadedFile($path, 'document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $response = $this->postJson('/upload', [
        'file' => $file,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'type' => 'docx',
        ]);

    expect($response->json('content'))->toContain('Hello from DOCX test document');

    DocumentFixtures::cleanup($path);
});

test('upload handles parsing failure gracefully and logs details', function () {
    Storage::fake('local');
    Log::spy();

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('corrupt.pdf', 'not a real pdf'),
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ]);

    expect($response->json('message'))->toBe('The document could not be parsed. Please check the file and try again.');

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message): bool {
            return str_contains($message, 'Document parsing failed');
        });
});

test('failed parses do not leave orphaned files in storage', function () {
    Storage::fake('local');

    $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('corrupt.pdf', 'not a real pdf'),
    ])->assertStatus(422);

    Storage::disk('local')->assertDirectoryEmpty('documents');
});

test('upload does not expose exception details to the user', function () {
    Storage::fake('local');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('corrupt.pdf', 'not a real pdf'),
    ]);

    expect($response->json('message'))->not->toContain('Missing')
        ->and($response->json('message'))->not->toContain('.php')
        ->and($response->json('message'))->not->toContain('storage');
});

test('upload rejects files with unsupported extensions at validation', function () {
    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('image.png', 100, 'image/png'),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('existing upload validation still rejects oversized files', function () {
    $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('big.bin', 11 * 1024),
    ])->assertStatus(422);
});

test('uploads whose extracted text exceeds the limit are rejected', function () {
    Storage::fake('local');
    config()->set('rag.max_extracted_characters', 10);

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 11)),
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ])
        ->assertJsonPath('message', 'The document contains too much text to process at once. Please upload a smaller document.');

    Storage::disk('local')->assertDirectoryEmpty('documents');
});

test('upload endpoint is rate limited', function () {
    Storage::fake('local');

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/upload', [
            'file' => UploadedFile::fake()->createWithContent('tiny.txt', 'content '.$i),
        ])->assertOk();
    }

    $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('tiny.txt', 'too many'),
    ])->assertStatus(429);
});
