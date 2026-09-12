<?php

use App\Exceptions\GroqUnavailableException;
use App\Services\AnswerGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('services.groq.key', 'test-groq-key');
});

function groqResponse(string $content): array
{
    return ['choices' => [['message' => ['content' => $content]]]];
}

test('grounded answer cites sources in prompt', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(groqResponse('X is Y [1]'), 200),
    ]);

    $answer = app(AnswerGenerator::class)->generate('What is X?', [
        (object) ['content' => 'X is Y', 'filename' => 'doc.pdf', 'score' => 0.9],
    ]);

    expect($answer)->toContain('X is Y [1]');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['model'] === config('services.groq.model')
            && $body['temperature'] === 0.2
            && str_contains($body['messages'][1]['content'], '[1] (source: doc.pdf)')
            && str_contains($body['messages'][1]['content'], 'X is Y')
            && str_contains($body['messages'][0]['content'], "don't know");
    });
});

test('empty context uses fallback prompt', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(groqResponse("I don't know"), 200),
    ]);

    $answer = app(AnswerGenerator::class)->generate('What is X?', []);

    expect($answer)->toContain("don't know");

    Http::assertSent(function ($request) {
        return str_contains($request->data()['messages'][1]['content'], 'No documents ingested yet');
    });
});

test('uses configured groq model and temperature', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(groqResponse('ok'), 200),
    ]);

    app(AnswerGenerator::class)->generate('Q?', []);

    Http::assertSent(function ($request) {
        return $request->data()['model'] === 'llama-3.3-70b-versatile'
            && $request->data()['temperature'] === 0.2;
    });
});

test('groq failure throws with sources preserved', function () {
    Http::fake([
        'api.groq.com/*' => Http::response(['error' => 'overloaded'], 500),
    ]);

    $hits = [(object) ['content' => 'X is Y', 'filename' => 'doc.pdf', 'score' => 0.9]];

    try {
        app(AnswerGenerator::class)->generate('What is X?', $hits);
        expect(false)->toBeTrue('Expected GroqUnavailableException');
    } catch (GroqUnavailableException $e) {
        expect($e->sources)->toHaveCount(1);
    }
});

test('missing api key throws without http call', function () {
    config()->set('services.groq.key', '');
    Http::fake();

    try {
        app(AnswerGenerator::class)->generate('Q?', []);
        expect(false)->toBeTrue('Expected GroqUnavailableException');
    } catch (GroqUnavailableException $e) {
        expect(true)->toBeTrue();
    }

    Http::assertNothingSent();
});
