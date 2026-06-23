<?php

namespace App\Services;

use App\Contracts\FeedbackRepositoryInterface;
use App\Exceptions\DatabaseUnavailableException;
use App\Exceptions\GeminiApiException;
use App\Jobs\AnalyzeFeedbackSentiment;
use App\Models\Feedback;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class FeedbackService
{
    use DetectsLostConnections;

    public function __construct(
        private FeedbackRepositoryInterface $repository,
        private GeminiClient $geminiClient,
    ) {}

    /**
     * @return Collection<int, Feedback>
     */
    public function list(): Collection
    {
        try {
            return $this->repository->all();
        } catch (QueryException|PDOException|Throwable $e) {
            if (! $this->isDatabaseConnectionFailure($e)) {
                throw $e;
            }

            $this->logDatabaseUnavailable('feedback.list_database_unavailable', $e);

            throw new DatabaseUnavailableException('Database is unavailable.', 0, $e);
        }
    }

    /**
     * @param  array{id: string, customer_name?: ?string, feedback_text: string, status_ai?: string, sentiment?: ?string, category?: ?string}  $payload
     */
    public function store(array $payload): StoreFeedbackResult
    {
        try {
            if ($this->repository->existsById($payload['id'])) {
                throw ValidationException::withMessages([
                    'id' => ['The id has already been taken.'],
                ]);
            }

            $feedback = $this->repository->create($payload);

            AnalyzeFeedbackSentiment::dispatch($feedback);

            return new StoreFeedbackResult(
                id: $payload['id'],
                feedback: $feedback,
            );
        } catch (ValidationException $e) {
            throw $e;
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
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                    'exception_class' => $redisException::class,
                    'message' => $redisException->getMessage(),
                    'trace' => $redisException->getTraceAsString(),
                ]);

                throw $redisException;
            }

            return new StoreFeedbackResult(
                id: $payload['id'],
                queued: true,
            );
        }
    }

    public function insight(): string
    {
        try {
            $complaints = $this->repository->latestComplaints();
        } catch (QueryException|PDOException|Throwable $e) {
            if (! $this->isDatabaseConnectionFailure($e)) {
                throw $e;
            }

            $this->logDatabaseUnavailable('feedback.insight_database_unavailable', $e);

            throw new DatabaseUnavailableException('Database is unavailable.', 0, $e);
        }

        if ($complaints->isEmpty()) {
            return 'Belum ada keluhan pelanggan yang dapat dianalisis.';
        }

        $compilation = $complaints
            ->values()
            ->map(fn (Feedback $feedback, int $index): string => ($index + 1).'. '.$feedback->feedback_text)
            ->implode("\n");

        try {
            return $this->geminiClient->generateInsight($compilation);
        } catch (GeminiApiException $e) {
            Log::error('Gemini insight generation failed.', [
                'event' => 'gemini.insight_generation_failed',
                'complaint_count' => $complaints->count(),
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function logDatabaseUnavailable(string $event, Throwable $e): void
    {
        Log::error('MySQL unavailable during feedback read operation.', [
            'event' => $event,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
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
