<?php

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.groq.key', 'test-groq-key');
});

test('missing question is rejected with 422', function () {
    $this->postJson('/api/query', [])->assertStatus(422);
});

test('overlong question is rejected with 422', function () {
    $this->postJson('/api/query', ['question' => str_repeat('a', 3000)])->assertStatus(422);
});

test('query with no documents returns fallback answer', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]], 200),
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => "I don't know"]]]], 200),
    ]);

    $response = $this->postJson('/api/query', ['question' => 'What is X?']);

    $response->assertOk()
        ->assertJsonPath('answer', "I don't know")
        ->assertJsonStructure(['answer', 'sources']);
});

test('query returns grounded answer with sources', function () {
    $doc = Document::create(['filename' => 'doc.pdf', 'status' => 'ready']);
    $doc->chunks()->create(['chunk_index' => 0, 'content' => 'revenue is 100']);

    Http::fake([
        'api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]], 200),
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'revenue is 100 [1]']]]], 200),
    ]);

    $response = $this->postJson('/api/query', ['question' => 'What is revenue?']);

    $response->assertOk()
        ->assertJsonPath('answer', 'revenue is 100 [1]')
        ->assertJsonStructure(['answer', 'sources' => [['content', 'filename']]])
        ->assertJsonPath('sources.0.filename', 'doc.pdf');
});

test('groq failure returns 503 with sources preserved', function () {
    $doc = Document::create(['filename' => 'doc.pdf', 'status' => 'ready']);
    $doc->chunks()->create(['chunk_index' => 0, 'content' => 'revenue is 100']);

    Http::fake([
        'api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]], 200),
        'api.groq.com/*' => Http::response(['error' => 'overloaded'], 500),
    ]);

    $response = $this->postJson('/api/query', ['question' => 'What is revenue?']);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'groq_unavailable')
        ->assertJsonStructure(['message', 'code', 'sources'])
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('sources.0.filename', 'doc.pdf');
});

test('embedding failure during search returns 503', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'boom'], 500),
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'unused']]]], 200),
    ]);

    $response = $this->postJson('/api/query', ['question' => 'What is X?']);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'groq_unavailable');
});
