<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DatabaseUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'Database is unavailable.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
