<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

    public function handle(EmbeddingService $embedder): void
    {
        $this->document->update(['status' => 'processing']);

        try {
            if (empty($this->chunks)) {
                $this->document->update(['status' => 'ready']);

                return;
            }

            $vectors = $embedder->embed($this->chunks);

            foreach ($this->chunks as $i => $content) {
                if (! isset($vectors[$i]) || count($vectors[$i]) !== 1536) {
                    throw new \RuntimeException('Invalid embedding dimension.');
                }

                if (DB::getDriverName() === 'pgsql') {
                    $literal = '['.implode(',', $vectors[$i]).']';
                    DB::statement(
                        'INSERT INTO chunks (document_id, chunk_index, content, embedding, created_at, updated_at) VALUES (?, ?, ?, ?::vector, now(), now())',
                        [$this->document->id, $i, $content, $literal]
                    );
                } else {
                    DB::table('chunks')->insert([
                        'document_id' => $this->document->id,
                        'chunk_index' => $i,
                        'content' => $content,
                        'embedding' => json_encode($vectors[$i]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->document->update(['status' => 'ready']);
        } catch (Throwable $e) {
            $this->document->update(['status' => 'failed']);
            Log::error('Embedding failed', ['document_id' => $this->document->id, 'error' => $e->getMessage()]);
        }
    }
}
