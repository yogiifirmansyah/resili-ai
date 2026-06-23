<?php

namespace App\Jobs;

use App\Models\Feedback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeFeedbackSentiment implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var list<string>
     */
    private const POSITIVE_KEYWORDS = ['bagus', 'puas'];

    /**
     * @var list<string>
     */
    private const NEGATIVE_KEYWORDS = ['mahal', 'rugi', 'kecewa'];

    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_KEYWORDS = [
        'layanan' => ['layanan', 'service', 'agen', 'lambat', 'respon'],
        'harga' => ['mahal', 'rugi', 'harga', 'biaya'],
    ];

    public function __construct(public Feedback $feedback)
    {
        $this->onConnection('redis');
    }

    public function handle(): void
    {
        try {
            $this->feedback->update(['status_ai' => 'processing']);

            $text = strtolower($this->feedback->feedback_text);

            $this->feedback->update([
                'sentiment' => $this->detectSentiment($text),
                'category' => $this->detectCategory($text),
                'status_ai' => 'completed',
            ]);
        } catch (Throwable $e) {
            Log::error('AI sentiment analysis failed.', [
                'feedback_id' => $this->feedback->id,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            $this->feedback->update(['status_ai' => 'failed']);
        }
    }

    private function detectSentiment(string $text): string
    {
        foreach (self::NEGATIVE_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return 'negative';
            }
        }

        foreach (self::POSITIVE_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return 'positive';
            }
        }

        return 'neutral';
    }

    private function detectCategory(string $text): string
    {
        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return 'umum';
    }
}
