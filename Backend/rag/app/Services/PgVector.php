<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class PgVector
{
    private static ?bool $available = null;

    /**
     * Whether native pgvector (`vector` type + `<=>` operator) is usable.
     * Memoized per process; false on non-pgsql drivers or when the
     * extension binary is not installed. Native path auto-activates once
     * the extension lands — no code change needed.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return self::$available = false;
        }

        try {
            self::$available = DB::selectOne(
                "SELECT 1 AS ok FROM pg_available_extensions WHERE name = 'vector'"
            ) !== null;
        } catch (Throwable) {
            self::$available = false;
        }

        return self::$available;
    }

    /**
     * Cosine similarity in [0, 1] for equal-length vectors.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($normA) * sqrt($normB))));
    }

    public static function flushMemo(): void
    {
        self::$available = null;
    }
}
