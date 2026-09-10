<?php

namespace App\Services;

use InvalidArgumentException;

class DocumentChunker
{
    /**
     * Split extracted document text into overlapping character-based chunks.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, ?int $chunkSize = null, ?int $overlap = null): array
    {
        $chunkSize = $chunkSize ?? (int) config('rag.chunk_size');
        $overlap = $overlap ?? (int) config('rag.chunk_overlap');

        $this->assertValidConfiguration($chunkSize, $overlap);

        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $step = $chunkSize - $overlap;
        $length = mb_strlen($text);
        $chunks = [];

        for ($offset = 0; $offset < $length; $offset += $step) {
            $chunks[] = mb_substr($text, $offset, $chunkSize);
        }

        return $chunks;
    }

    private function assertValidConfiguration(int $chunkSize, int $overlap): void
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException('rag.chunk_size must be greater than 0.');
        }

        if ($overlap < 0) {
            throw new InvalidArgumentException('rag.chunk_overlap must be greater than or equal to 0.');
        }

        if ($overlap >= $chunkSize) {
            throw new InvalidArgumentException('rag.chunk_overlap must be smaller than rag.chunk_size.');
        }
    }
}
