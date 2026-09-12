<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    /**
     * Generate embeddings for the given texts in a single batch.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::withToken((string) config('services.openai.key'))
            ->retry(3, 100)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => array_values($texts),
            ])
            ->throw()
            ->json();

        return array_map(fn ($item) => $item['embedding'], $response['data']);
    }
}
