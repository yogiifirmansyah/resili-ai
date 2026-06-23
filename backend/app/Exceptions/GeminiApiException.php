<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GeminiApiException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isRateLimit(): bool
    {
        if ($this->getCode() === 429) {
            return true;
        }

        return stripos($this->getMessage(), 'quota exceeded') !== false;
    }
}
