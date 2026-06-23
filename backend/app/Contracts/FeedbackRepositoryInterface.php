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

    public function existsById(string $id): bool;

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function pushToFallbackQueue(array $payload): void;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Feedback>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Feedback>
     */
    public function latestComplaints(int $limit = 100): \Illuminate\Database\Eloquent\Collection;
}
