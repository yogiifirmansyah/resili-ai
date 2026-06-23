<?php

namespace App\Console\Commands;

use App\Models\Feedback;
use Illuminate\Console\Command;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PDOException;
use Throwable;

class SyncFallbackFeedback extends Command
{
    use DetectsLostConnections;

    private const FALLBACK_QUEUE_KEY = 'feedback_fallback_queue';

    protected $signature = 'resili:sync-feedback';

    protected $description = 'Sinkronkan feedback dari antrean Redis fallback ke MySQL';

    public function handle(): int
    {
        $syncedCount = 0;

        while (Redis::llen(self::FALLBACK_QUEUE_KEY) > 0) {
            $payload = Redis::lpop(self::FALLBACK_QUEUE_KEY);

            if ($payload === null || $payload === false) {
                break;
            }

            $data = json_decode($payload, true);

            if (! is_array($data) || ! isset($data['id'])) {
                Log::warning('Invalid fallback feedback payload skipped during sync.', [
                    'payload' => $payload,
                ]);

                continue;
            }

            try {
                $feedback = Feedback::firstOrCreate(
                    ['id' => $data['id']],
                    $data
                );

                if ($feedback->wasRecentlyCreated) {
                    $syncedCount++;
                } else {
                    Log::info('Fallback feedback already exists in database, skipping duplicate.', [
                        'feedback_id' => $data['id'],
                    ]);
                }
            } catch (UniqueConstraintViolationException $e) {
                Log::info('Fallback feedback already exists in database, skipping duplicate.', [
                    'feedback_id' => $data['id'] ?? null,
                ]);
            } catch (QueryException|PDOException|Throwable $e) {
                if (! $this->isDatabaseConnectionFailure($e)) {
                    throw $e;
                }

                Redis::lpush(self::FALLBACK_QUEUE_KEY, $payload);

                Log::error('MySQL unavailable during fallback feedback sync, stopping with remaining queue intact.', [
                    'feedback_id' => $data['id'] ?? null,
                    'synced_count' => $syncedCount,
                    'exception' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);

                break;
            }
        }

        Log::info('Fallback feedback sync completed.', [
            'synced_count' => $syncedCount,
        ]);

        $this->info("Berhasil menyinkronkan {$syncedCount} feedback dari antrean Redis fallback.");

        return self::SUCCESS;
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
