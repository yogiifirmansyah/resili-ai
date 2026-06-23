<?php

namespace App\Services;

use App\Contracts\FeedbackRepositoryInterface;
use App\Jobs\AnalyzeFeedbackSentiment;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class FeedbackService
{
    use DetectsLostConnections;

    public function __construct(
        private FeedbackRepositoryInterface $repository,
    ) {}

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function store(array $payload): StoreFeedbackResult
    {
        try {
            $feedback = $this->repository->create($payload);

            AnalyzeFeedbackSentiment::dispatch($feedback);

            return new StoreFeedbackResult(
                id: $payload['id'],
                feedback: $feedback,
            );
        } catch (QueryException|PDOException|Throwable $e) {
            if (! $this->isDatabaseConnectionFailure($e)) {
                throw $e;
            }

            Log::error('MySQL unavailable during feedback submission, routing to Redis fallback.', [
                'event' => 'feedback.database_connection_failure',
                'feedback_id' => $payload['id'],
                'feedback' => [
                    'id' => $payload['id'],
                    'customer_name' => $payload['customer_name'] ?? null,
                    'status_ai' => $payload['status_ai'] ?? 'pending',
                ],
                'exception' => $e,
            ]);

            try {
                $this->repository->pushToFallbackQueue($payload);
            } catch (Throwable $redisException) {
                Log::error('Redis fallback queue write failed.', [
                    'event' => 'feedback.redis_fallback_failure',
                    'feedback_id' => $payload['id'],
                    'feedback' => [
                        'id' => $payload['id'],
                        'customer_name' => $payload['customer_name'] ?? null,
                        'status_ai' => $payload['status_ai'] ?? 'pending',
                    ],
                    'exception' => $redisException,
                ]);

                throw $redisException;
            }

            return new StoreFeedbackResult(
                id: $payload['id'],
                queued: true,
            );
        }
    }

    private function isDatabaseConnectionFailure(Throwable $e): bool
    {
        $current = $e;

        while ($current !== null) {
            if ($current instanceof QueryException || $current instanceof PDOException) {
                if ($this->causedByLostConnection($current)) {
                    return true;
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}
