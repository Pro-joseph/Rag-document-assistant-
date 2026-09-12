<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sources = collect($this->resource['sources'] ?? [])
            ->map(fn ($hit) => [
                'content' => is_array($hit) ? ($hit['content'] ?? '') : ($hit->content ?? ''),
                'filename' => is_array($hit) ? ($hit['filename'] ?? '') : ($hit->filename ?? ''),
                'score' => is_array($hit) ? ($hit['score'] ?? null) : ($hit->score ?? null),
            ])
            ->all();

        return [
            'answer' => $this->resource['answer'] ?? '',
            'sources' => $sources,
        ];
    }
}
