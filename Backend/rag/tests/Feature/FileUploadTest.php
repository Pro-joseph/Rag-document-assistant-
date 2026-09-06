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

test('the upload endpoint validates that a file is provided', function () {
    $this->postJson('/upload')->assertStatus(422);
});

test('an oversized file is rejected', function () {
    $this->postJson('/upload', [
        'file' => UploadedFile::fake()->create('big.bin', 11 * 1024),
    ])->assertStatus(422);
});
