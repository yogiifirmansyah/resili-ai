<?php

namespace App\Jobs;

use App\Exceptions\GeminiApiException;
use App\Models\Feedback;
use App\Services\GeminiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeFeedbackSentiment implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Feedback $feedback)
    {
        $this->onConnection('redis');
    }

    public function handle(GeminiClient $geminiClient): void
    {
        try {
            $this->feedback->update(['status_ai' => 'processing']);

            $analysis = $geminiClient->analyzeSentiment($this->feedback->feedback_text);

            $this->feedback->update([
                'sentiment' => $analysis['sentiment'],
                'category' => $analysis['category'],
                'status_ai' => 'completed',
            ]);

            $this->throttleAfterSuccess();
        } catch (GeminiApiException $e) {
            if ($e->isRateLimit()) {
                $this->handleRateLimit($e);

                return;
            }

            $this->markAsFailed($e);
        } catch (Throwable $e) {
            $this->markAsFailed($e);
        }
    }

    private function handleRateLimit(GeminiApiException $e): void
    {
        $releaseDelay = (int) config('services.gemini.rate_limit_release_seconds', 30);

        Log::warning('AI sentiment analysis rate limited, releasing job back to queue.', [
            'event' => 'gemini.sentiment_analysis_rate_limited',
            'feedback_id' => $this->feedback->id,
            'release_delay_seconds' => $releaseDelay,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
        ]);

        $this->release($releaseDelay);
    }

    private function throttleAfterSuccess(): void
    {
        $delay = (int) config('services.gemini.throttle_seconds', 3);

        if ($delay > 0) {
            sleep($delay);
        }
    }

    private function markAsFailed(Throwable $e): void
    {
        Log::error('AI sentiment analysis failed.', [
            'event' => 'gemini.sentiment_analysis_failed',
            'feedback_id' => $this->feedback->id,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->feedback->update(['status_ai' => 'failed']);
    }
}
