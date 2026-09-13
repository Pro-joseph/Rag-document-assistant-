<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestDocument implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $chunks
     */
    public function __construct(
        public Document $document,
        public array $chunks = [],
    ) {}

    public function handle(EmbeddingService $embedder, ?VectorStore $vectorStore = null): void
    {
        $vectorStore ??= app(VectorStore::class);

        $this->document->update(['status' => 'processing']);

        try {
            if (empty($this->chunks)) {
                $this->document->update(['status' => 'ready']);

                return;
            }

            $vectors = $embedder->embed($this->chunks);

            $vectorStore->store($this->document, $this->chunks, $vectors);

            $this->document->update(['status' => 'ready']);
        } catch (Throwable $e) {
            $this->document->update(['status' => 'failed']);
            Log::error('Embedding failed', ['document_id' => $this->document->id, 'error' => $e->getMessage()]);
        }
    }
}
