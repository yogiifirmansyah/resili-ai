<?php

namespace App\Contracts;

use App\Models\Feedback;

interface FeedbackRepositoryInterface
{
    public const FALLBACK_QUEUE_KEY = 'feedback_fallback_queue';

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function create(array $payload): Feedback;

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function pushToFallbackQueue(array $payload): void;
}
