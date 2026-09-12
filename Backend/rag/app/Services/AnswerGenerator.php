<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
            ? "Context:\n{$context}\n\nQuestion: {$question}\n\nAnswer using only the context above, citing [source] numbers."
            : "No documents ingested yet.\n\nQuestion: {$question}";

        $response = Http::withToken((string) config('services.groq.key'))
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

        return $response['choices'][0]['message']['content'];
    }
}
