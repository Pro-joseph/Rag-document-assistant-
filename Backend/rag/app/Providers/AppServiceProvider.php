<?php

namespace App\Providers;

use App\Services\DocumentParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(DocumentParser::class)
            ->needs('$maxExtractedCharacters')
            ->give(fn (): int => (int) config('rag.max_extracted_characters'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
