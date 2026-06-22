<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PDOException;
use Throwable;

class FeedbackController extends Controller
{
    use DetectsLostConnections;

    private const FALLBACK_QUEUE_KEY = 'feedback_fallback_queue';

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $payload = array_merge($request->validated(), [
            'status_ai' => 'pending',
        ]);

        try {
            $feedback = Feedback::create($payload);

            return response()->json($feedback, 201);
        } catch (QueryException|PDOException|Throwable $e) {
            if (! $this->isDatabaseConnectionFailure($e)) {
                throw $e;
            }

            Log::error('MySQL unavailable during feedback submission, routing to Redis fallback.', [
                'feedback_id' => $payload['id'],
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            try {
                Redis::rpush(self::FALLBACK_QUEUE_KEY, json_encode($payload));
            } catch (Throwable $redisException) {
                Log::error('Redis fallback queue write failed.', [
                    'feedback_id' => $payload['id'],
                    'exception' => $redisException->getMessage(),
                    'exception_class' => $redisException::class,
                ]);

                throw $redisException;
            }

            return response()->json([
                'message' => 'Feedback disimpan di antrean fallback karena database sementara tidak tersedia.',
                'id' => $payload['id'],
                'queued' => true,
            ], 202);
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
