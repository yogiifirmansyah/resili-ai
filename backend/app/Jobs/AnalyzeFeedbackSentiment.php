<?php

namespace App\Jobs;

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
        } catch (Throwable $e) {
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
}
