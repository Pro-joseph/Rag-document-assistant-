<?php

use App\Services\DocumentChunker;
use InvalidArgumentException;

test('chunker splits long text into multiple chunks', function () {
    $chunks = (new DocumentChunker)->chunk(str_repeat('a', 2500), 800, 150);

    expect($chunks)->toHaveCount(4)
        ->and(collect($chunks)->every(fn (string $chunk): bool => $chunk !== ''))->toBeTrue();
});

test('chunks never exceed the configured chunk size', function () {
    $chunks = (new DocumentChunker)->chunk(str_repeat('word ', 500), 100, 20);

    expect($chunks)->toHaveCount(32)
        ->and(collect($chunks)->every(fn (string $chunk): bool => mb_strlen($chunk) <= 100))->toBeTrue();
});

test('consecutive chunks contain the expected overlapping portion', function () {
    $chunks = (new DocumentChunker)->chunk(str_repeat('word ', 500), 100, 20);

    expect($chunks)->toHaveCount(32)
        ->and($chunks[1])->toStartWith(mb_substr($chunks[0], -20));
});

test('chunker preserves the original text content', function () {
    $text = str_repeat('word ', 500);

    $chunks = (new DocumentChunker)->chunk($text, 100, 20);

    expect(implode('', $chunks))->toContain($text)
        ->and($chunks[0])->toBe(mb_substr($text, 0, 100))
        ->and(end($chunks))->toEndWith('word');
});

test('chunker returns no chunks for empty or whitespace-only text', function () {
    expect((new DocumentChunker)->chunk('', 800, 150))->toBe([])
        ->and((new DocumentChunker)->chunk('   ', 800, 150))->toBe([])
        ->and((new DocumentChunker)->chunk("\n\t\n", 800, 150))->toBe([]);
});

test('chunker keeps a short document as a single chunk', function () {
    expect((new DocumentChunker)->chunk('Hello world', 800, 150))->toBe(['Hello world']);
});

test('chunker rejects configuration where overlap reaches or exceeds chunk size', function () {
    $chunker = new DocumentChunker;

    expect(fn () => $chunker->chunk('some text', 100, 100))->toThrow(InvalidArgumentException::class, 'overlap')
        ->and(fn () => $chunker->chunk('some text', 100, 150))->toThrow(InvalidArgumentException::class);
});

test('chunker rejects a non-positive chunk size and a negative overlap', function () {
    $chunker = new DocumentChunker;

    expect(fn () => $chunker->chunk('some text', 0, 0))->toThrow(InvalidArgumentException::class, 'chunk_size')
        ->and(fn () => $chunker->chunk('some text', -10, 0))->toThrow(InvalidArgumentException::class, 'chunk_size')
        ->and(fn () => $chunker->chunk('some text', 100, -1))->toThrow(InvalidArgumentException::class, 'overlap');
});
