<?php

use App\Services\EmbeddingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('batch embedding returns one 1536-dim vector per chunk', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
                ['embedding' => array_fill(0, 1536, 0.2)],
            ],
        ], 200),
    ]);

    $vectors = app(EmbeddingService::class)->embed(['chunk one', 'chunk two']);

    expect($vectors)->toHaveCount(2)
        ->and($vectors[0])->toHaveCount(1536)
        ->and($vectors[1])->toHaveCount(1536);

    Http::assertSentCount(1);
});

test('empty chunk list skips HTTP call', function () {
    Http::fake();

    $vectors = app(EmbeddingService::class)->embed([]);

    expect($vectors)->toBe([]);
    Http::assertNothingSent();
});

test('service error is thrown to caller', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'overloaded'], 500),
    ]);

    app(EmbeddingService::class)->embed(['hello']);
})->throws(RequestException::class);
