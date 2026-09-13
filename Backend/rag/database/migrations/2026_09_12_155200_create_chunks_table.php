<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->text('embedding')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql' && self::pgvectorAvailable()) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE chunks ALTER COLUMN embedding TYPE vector(1536) USING embedding::vector(1536)');
            DB::statement('CREATE INDEX IF NOT EXISTS chunks_embedding_idx ON chunks USING ivfflat (embedding vector_cosine_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && self::pgvectorAvailable()) {
            DB::statement('DROP INDEX IF EXISTS chunks_embedding_idx');
        }

        Schema::dropIfExists('chunks');
    }

    private static function pgvectorAvailable(): bool
    {
        try {
            return DB::selectOne(
                "SELECT 1 AS ok FROM pg_available_extensions WHERE name = 'vector'"
            ) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
};
