<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Retriever
{
    public function __construct(private EmbeddingService $embedder) {}

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

        $queryVector = $vectors[0];
        $literal = '['.implode(',', $queryVector).']';

        if (DB::getDriverName() === 'pgsql') {
            return DB::select(
                'SELECT chunks.content, documents.filename, 1 - (chunks.embedding <=> ?::vector) AS score
                 FROM chunks
                 JOIN documents ON documents.id = chunks.document_id
                 ORDER BY chunks.embedding <=> ?::vector
                 LIMIT ?',
                [$literal, $literal, $topK]
            );
        }

        $rows = DB::table('chunks')
            ->join('documents', 'documents.id', '=', 'chunks.document_id')
            ->select('chunks.content', 'documents.filename')
            ->limit($topK)
            ->get();

        return $rows->map(fn ($r) => (object) ['content' => $r->content, 'filename' => $r->filename, 'score' => 1.0])->all();
    }
}
