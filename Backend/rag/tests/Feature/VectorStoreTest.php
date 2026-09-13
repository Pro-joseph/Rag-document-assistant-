<?php

use App\Jobs\IngestDocument;
use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function vectorWithPattern(array $pattern): array
{
    $vector = [];

    while (count($vector) < 1536) {
        $vector = array_merge($vector, $pattern);
    }

    return array_slice($vector, 0, 1536);
}

test('store persists each vector with document metadata (US-15 + US-16)', function () {
    $document = Document::create(['filename' => 'manual.pdf']);
    $store = app(VectorStore::class);

    $store->store($document, ['first chunk', 'second chunk'], [
        vectorWithPattern([0.1, 0.1, 0.1]),
        vectorWithPattern([0.9, 0.1, 0.1]),
    ]);

    $chunks = $document->chunks()->get();

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]->embedding)->not->toBeNull()
        ->and($chunks[1]->metadata)->toMatchArray([
            'document_id' => $document->id,
            'filename' => 'manual.pdf',
            'chunk_index' => 1,
        ]);
});

test('store rejects wrong dimension before any insert', function () {
    $document = Document::create(['filename' => 'broken.pdf']);
    $store = app(VectorStore::class);

    expect(fn () => $store->store($document, ['chunk'], [array_fill(0, 10, 0.5)]))
        ->toThrow(RuntimeException::class)
        ->and($document->chunks()->count())->toBe(0);
});

test('stored vectors are retrievable with cosine score (US-17)', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => vectorWithPattern([0.1, 0.1, 0.1])],
                ['embedding' => vectorWithPattern([0.9, 0.1, 0.1])],
                ['embedding' => vectorWithPattern([0.9, 0.9, 0.1])],
            ],
        ], 200),
    ]);

    $document = Document::create(['filename' => 'manual.pdf']);
    (new IngestDocument($document, ['first chunk', 'second chunk', 'third chunk']))
        ->handle(app(EmbeddingService::class));

    $hits = app(VectorStore::class)->nearest(vectorWithPattern([0.9, 0.9, 0.1]), 3);

    expect($hits)->toHaveCount(3)
        ->and($hits[0]->score)->toBeGreaterThan(0.9)
        ->and($hits[0]->filename)->toBe('manual.pdf')
        ->and($hits[0]->metadata['filename'])->toBe('manual.pdf')
        ->and($hits[2]->score)->toBeLessThan($hits[0]->score);
});

test('nearest ranks the closest stored vector first', function () {
    $documentA = Document::create(['filename' => 'a.pdf']);
    $documentB = Document::create(['filename' => 'b.pdf']);
    $store = app(VectorStore::class);

    $store->store($documentA, ['about cats'], [vectorWithPattern([0.1, 0.1, 0.1])]);
    $store->store($documentB, ['about dogs'], [vectorWithPattern([0.9, 0.1, 0.1])]);

    $hits = $store->nearest(vectorWithPattern([0.9, 0.1, 0.1]), 2);

    expect($hits)->toHaveCount(2)
        ->and($hits[0]->filename)->toBe('b.pdf')
        ->and($hits[1]->filename)->toBe('a.pdf');
});

test('rows without an embedding are still listed with zero score', function () {
    $document = Document::create(['filename' => 'legacy.pdf']);
    $document->chunks()->create(['chunk_index' => 0, 'content' => 'legacy chunk']);

    $hits = app(VectorStore::class)->nearest(vectorWithPattern([0.5, 0.5, 0.5]), 1);

    expect($hits)->toHaveCount(1)
        ->and($hits[0]->score)->toBe(0.0)
        ->and($hits[0]->content)->toBe('legacy chunk');
});
