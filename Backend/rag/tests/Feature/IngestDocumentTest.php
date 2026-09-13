<?php

use App\Jobs\IngestDocument;
use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeVectors(int $count): array
{
    return [
        'data' => array_map(
            fn ($i) => ['embedding' => array_fill(0, 1536, 0.1 + $i * 0.01)],
            range(0, $count - 1)
        ),
    ];
}

test('empty chunks complete as ready with zero vectors', function () {
    Http::fake();

    $document = Document::create(['filename' => 'empty.txt']);

    (new IngestDocument($document, []))->handle(app(EmbeddingService::class));

    expect($document->fresh()->status)->toBe('ready')
        ->and($document->fresh()->chunks()->count())->toBe(0);

    Http::assertNothingSent();
});

test('chunks generate one vector each and mark ready', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(fakeVectors(2), 200),
    ]);

    $document = Document::create(['filename' => 'doc.pdf']);

    (new IngestDocument($document, ['chunk one', 'chunk two']))->handle(app(EmbeddingService::class));

    expect($document->fresh()->status)->toBe('ready')
        ->and($document->fresh()->chunks()->count())->toBe(2);
});

test('embedding failure marks failed with no partial vectors', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'overloaded'], 500),
    ]);

    $document = Document::create(['filename' => 'broken.pdf']);

    (new IngestDocument($document, ['chunk one']))->handle(app(EmbeddingService::class));

    expect($document->fresh()->status)->toBe('failed')
        ->and($document->fresh()->chunks()->count())->toBe(0);
});

test('failed document does not block next queued document', function () {
    Http::fakeSequence()
        ->push(['error' => 'boom'], 500)
        ->push(['error' => 'boom'], 500)
        ->push(['error' => 'boom'], 500)
        ->push(['error' => 'boom'], 500)
        ->push(fakeVectors(1), 200);

    $failed = Document::create(['filename' => 'a.pdf']);
    $ready = Document::create(['filename' => 'b.pdf']);

    (new IngestDocument($failed, ['chunk a']))->handle(app(EmbeddingService::class));
    (new IngestDocument($ready, ['chunk b']))->handle(app(EmbeddingService::class));

    expect($failed->fresh()->status)->toBe('failed')
        ->and($ready->fresh()->status)->toBe('ready')
        ->and($ready->fresh()->chunks()->count())->toBe(1);
});
