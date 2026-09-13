<?php

namespace App\Services;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VectorStore
{
    private const DIMENSION = 1536;

    /**
     * Persist each chunk vector together with its document metadata.
     *
     * @param  array<int, string>  $chunks
     * @param  array<int, array<int, float>>  $vectors
     */
    public function store(Document $document, array $chunks, array $vectors): void
    {
        foreach ($chunks as $i => $content) {
            if (! isset($vectors[$i]) || count($vectors[$i]) !== self::DIMENSION) {
                throw new \RuntimeException('Invalid embedding dimension.');
            }

            $metadata = [
                'document_id' => $document->id,
                'filename' => $document->filename,
                'chunk_index' => $i,
            ];

            if (DB::getDriverName() === 'pgsql') {
                $literal = '['.implode(',', $vectors[$i]).']';
                DB::statement(
                    'INSERT INTO chunks (document_id, chunk_index, content, embedding, metadata, created_at, updated_at) VALUES (?, ?, ?, ?::vector, ?::jsonb, now(), now())',
                    [$document->id, $i, $content, $literal, json_encode($metadata, JSON_THROW_ON_ERROR)]
                );

                continue;
            }

            Chunk::create([
                'document_id' => $document->id,
                'chunk_index' => $i,
                'content' => $content,
                'embedding' => json_encode($vectors[$i], JSON_THROW_ON_ERROR),
                'metadata' => $metadata,
            ]);
        }
    }

    /**
     * Retrieve the top-K most similar stored vectors.
     *
     * @param  array<int, float>  $vector
     * @return Collection<int, object{content: string, filename: string, score: float, metadata: array<string, mixed>}>
     */
    public function nearest(array $vector, int $topK = 4): Collection
    {
        $literal = '['.implode(',', $vector).']';

        if (DB::getDriverName() === 'pgsql') {
            $rows = DB::select(
                'SELECT chunks.content, chunks.metadata, documents.filename,
                        1 - (chunks.embedding <=> ?::vector) AS score
                 FROM chunks
                 JOIN documents ON documents.id = chunks.document_id
                 ORDER BY chunks.embedding <=> ?::vector
                 LIMIT ?',
                [$literal, $literal, $topK]
            );

            return collect($rows)->map(fn ($row) => (object) [
                'content' => $row->content,
                'filename' => $row->filename,
                'score' => (float) $row->score,
                'metadata' => $this->decodeMetadata($row->metadata),
            ]);
        }

        return $this->scan($vector, $topK);
    }

    /**
     * Non-PostgreSQL fallback: compute cosine similarity in PHP.
     *
     * @param  array<int, float>  $vector
     * @return Collection<int, object{content: string, filename: string, score: float, metadata: array<string, mixed>}>
     */
    private function scan(array $vector, int $topK): Collection
    {
        $rows = Chunk::query()
            ->join('documents', 'documents.id', '=', 'chunks.document_id')
            ->select('chunks.content', 'chunks.embedding', 'chunks.metadata', 'documents.filename')
            ->get();

        return $rows
            ->map(fn ($row) => (object) [
                'content' => $row->content,
                'filename' => $row->filename,
                'score' => $this->similarity($vector, $this->decodeEmbedding($row->embedding)),
                'metadata' => $this->decodeMetadata($row->metadata),
            ])
            ->sortByDesc('score')
            ->values()
            ->take($topK);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function similarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = $b[$i] ?? 0.0;
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @return array<int, float>
     */
    private function decodeEmbedding(?string $embedding): array
    {
        if ($embedding === null) {
            return [];
        }

        $decoded = json_decode($embedding, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
