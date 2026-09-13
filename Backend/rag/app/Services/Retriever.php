<?php

namespace App\Services;

class Retriever
{
    public function __construct(
        private EmbeddingService $embedder,
        private ?VectorStore $vectorStore = null,
    ) {}

    /**
     * Search for semantically similar chunks (US-21/22/23).
     * 1536 dims = text-embedding-3-small.
     *
     * @return array<int, object{content: string, filename: string, score: float}>
     */
    public function search(string $question, int $topK = 4): array
    {
        $vectors = $this->embedder->embed([$question]);

        if (empty($vectors) || empty($vectors[0])) {
            return [];
        }

        $vectorStore = $this->vectorStore ?? app(VectorStore::class);

        return $vectorStore->nearest($vectors[0], $topK)
            ->map(fn ($hit) => (object) [
                'content' => $hit->content,
                'filename' => $hit->filename,
                'score' => $hit->score,
            ])
            ->all();
    }
}
