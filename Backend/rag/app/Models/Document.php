<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['filename', 'status'])]
class Document extends Model
{
    /** @use HasFactory<Document> */
    use HasFactory;

    /**
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class)->orderBy('chunk_index');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeReady($query): void
    {
        $query->where('status', 'ready');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeFailed($query): void
    {
        $query->where('status', 'failed');
    }
}
