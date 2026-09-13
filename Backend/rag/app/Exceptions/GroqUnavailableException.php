<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GroqUnavailableException extends RuntimeException
{
    /**
     * @param  array<int, mixed>  $sources
     */
    public function __construct(
        string $message = 'AI service temporarily unavailable. Please retry.',
        public array $sources = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
