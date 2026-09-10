<?php

use App\Services\DocumentChunker;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

test('rag config defines the default chunk size and overlap', function () {
    expect(config('rag.chunk_size'))->toBe(800)
        ->and(config('rag.chunk_overlap'))->toBe(150);
});

test('chunker applies the configured chunk size and overlap', function () {
    config()->set([
        'rag.chunk_size' => 100,
        'rag.chunk_overlap' => 20,
    ]);

    $chunks = (new DocumentChunker)->chunk(str_repeat('word ', 500));

    expect(collect($chunks)->every(fn (string $chunk): bool => mb_strlen($chunk) <= 100))->toBeTrue()
        ->and($chunks)->toHaveCount(32)
        ->and($chunks[1])->toStartWith(mb_substr($chunks[0], -20));
});

test('chunker rejects an invalid chunking configuration from config', function () {
    config()->set([
        'rag.chunk_size' => 100,
        'rag.chunk_overlap' => 100,
    ]);

    expect(fn () => (new DocumentChunker)->chunk('some text'))->toThrow(
        InvalidArgumentException::class,
        'chunk_overlap must be smaller than rag.chunk_size'
    );
});

test('upload response includes chunks from the chunker', function () {
    Storage::fake('local');

    $response = $this->postJson('/upload', [
        'file' => UploadedFile::fake()->createWithContent('report.txt', str_repeat('Hello world. ', 200)),
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'type' => 'txt',
        ])
        ->assertJsonStructure(['chunks']);
});
