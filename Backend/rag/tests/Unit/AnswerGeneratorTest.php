<?php

use App\Services\AnswerGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

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
