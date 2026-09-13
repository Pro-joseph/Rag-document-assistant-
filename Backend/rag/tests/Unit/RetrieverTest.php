<?php

use App\Models\Chunk;
use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\Retriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seedChunk(Document $doc, int $index, string $content, array $vector): void
{
    if (DB::getDriverName() === 'pgsql') {
        $literal = '['.implode(',', $vector).']';
        DB::statement(
            'INSERT INTO chunks (document_id, chunk_index, content, embedding, created_at, updated_at) VALUES (?, ?, ?, ?::vector, now(), now())',
            [$doc->id, $index, $content, $literal]
        );
    } else {
        Chunk::create(['document_id' => $doc->id, 'chunk_index' => $index, 'content' => $content]);
    }
}

test('container resolves retriever with embedding service', function () {
    $retriever = app(Retriever::class);

    expect($retriever)->toBeInstanceOf(Retriever::class);
});

test('empty database returns empty array', function () {
    $embedder = Mockery::mock(EmbeddingService::class);
    $embedder->shouldReceive('embed')->once()->with(['anything'])->andReturn([array_fill(0, 1536, 0.1)]);

    $results = (new Retriever($embedder))->search('anything');

    expect($results)->toBe([]);
});

test('search returns ranked hits with content filename score', function () {
    $doc = Document::create(['filename' => 'doc.pdf']);
    seedChunk($doc, 0, 'revenue is 100', array_fill(0, 1536, 0.1));
    seedChunk($doc, 1, 'unrelated weather', array_fill(0, 1536, 0.9));

    $embedder = Mockery::mock(EmbeddingService::class);
    $embedder->shouldReceive('embed')->once()->andReturn([array_fill(0, 1536, 0.1)]);

    $results = (new Retriever($embedder))->search('what is revenue?');

    expect($results)->not->toBeEmpty()
        ->and($results[0]->content)->not->toBeEmpty()
        ->and($results[0]->filename)->toBe('doc.pdf');
});

test('topK limits returned chunks', function () {
    $doc = Document::create(['filename' => 'doc.pdf']);
    seedChunk($doc, 0, 'one', array_fill(0, 1536, 0.1));
    seedChunk($doc, 1, 'two', array_fill(0, 1536, 0.1));
    seedChunk($doc, 2, 'three', array_fill(0, 1536, 0.1));

    $embedder = Mockery::mock(EmbeddingService::class);
    $embedder->shouldReceive('embed')->once()->andReturn([array_fill(0, 1536, 0.1)]);

    $results = (new Retriever($embedder))->search('q', 2);

    expect($results)->toHaveCount(2);
});
