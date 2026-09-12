<?php

namespace App\Services;

use App\Exceptions\GroqUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnswerGenerator
{
    /**
     * Generate a grounded answer with citations (Groq).
     *
     * @param  array<int, object|array>  $hits
     */
    public function generate(string $question, array $hits): string
    {
        $context = collect($hits)
            ->map(function ($hit, $index) {
                $filename = is_array($hit) ? ($hit['filename'] ?? 'unknown') : ($hit->filename ?? 'unknown');
                $content = is_array($hit) ? ($hit['content'] ?? '') : ($hit->content ?? '');

                return '['.($index + 1)."] (source: {$filename})\n{$content}";
            })
            ->implode("\n\n");

        $prompt = $context !== ''
            ? "Context:\n{$context}\n\nQuestion: {$question}\n\nAnswer using only the context above, citing [n] numbers."
            : "No documents ingested yet.\n\nQuestion: {$question}";

        try {
            if ((string) config('services.groq.key') === '') {
                throw new GroqUnavailableException(sources: $hits);
            }

            $response = Http::withToken((string) config('services.groq.key'))
                ->timeout(5)
                ->retry(3, 100)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => "Answer only from the given context. If the answer is not in it, say you don't know."],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                ])
                ->throw()
                ->json();

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (! is_string($content) || $content === '') {
                throw new GroqUnavailableException(sources: $hits);
            }

            return $content;
        } catch (GroqUnavailableException $e) {
            Log::error('Groq generation failed', ['hits' => count($hits), 'error' => $e->getMessage()]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('Groq generation failed', ['hits' => count($hits), 'error' => $e->getMessage()]);

            throw new GroqUnavailableException(sources: $hits, previous: $e);
        }
    }
}
