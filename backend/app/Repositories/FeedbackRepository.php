<?php

namespace App\Repositories;

use App\Contracts\FeedbackRepositoryInterface;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;

class FeedbackRepository implements FeedbackRepositoryInterface
{
    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function create(array $payload): Feedback
    {
        $id = $this->requireIdempotencyKey($payload);

        return Feedback::create([
            ...$payload,
            'id' => $id,
        ]);
    }

    public function existsById(string $id): bool
    {
        return Feedback::query()->whereKey($id)->exists();
    }

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function pushToFallbackQueue(array $payload): void
    {
        $this->requireIdempotencyKey($payload);

        Redis::rpush(self::FALLBACK_QUEUE_KEY, json_encode($payload));
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function all(): Collection
    {
        return Feedback::query()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function latestComplaints(int $limit = 100): Collection
    {
        $limit = max(50, min($limit, 100));

        return Feedback::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireIdempotencyKey(array $payload): string
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Feedback id (UUID) is required as idempotency key.');
        }

        return $id;
    }
}
