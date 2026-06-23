<?php

namespace App\Services;

use App\Models\Feedback;

readonly class StoreFeedbackResult
{
    public function __construct(
        public string $id,
        public ?Feedback $feedback = null,
        public bool $queued = false,
    ) {}
}
