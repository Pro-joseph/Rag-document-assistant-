<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);
});

test('a docx file can be uploaded', function () {
    Storage::fake('public');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);
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
