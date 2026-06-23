<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const MAX_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 2000;

    private const SENTIMENT_SYSTEM_INSTRUCTION = <<<'PROMPT'
Anda adalah analis sentimen pelanggan. Analisis teks feedback pelanggan berikut.

Tentukan:
- sentiment: salah satu dari positive, neutral, negative
- category: salah satu dari pelayanan, harga, produk, teknis, umum

Aturan respons:
- HANYA kembalikan JSON valid tanpa markdown, tanpa penjelasan tambahan
- Format persis: {"sentiment": "...", "category": "..."}
PROMPT;

    private const INSIGHT_SYSTEM_INSTRUCTION = <<<'PROMPT'
Anda adalah konsultan bisnis senior. Analisis kumpulan keluhan pelanggan ini. Berikan Executive Summary maksimal 1 paragraf yang berisi rangkuman masalah utama dan 1 saran tindakan taktis untuk manajemen.

Aturan respons:
- Hanya kembalikan teks paragraf Executive Summary
- Tanpa judul, bullet point, atau format markdown
PROMPT;

    /**
     * @var list<string>
     */
    private const ALLOWED_SENTIMENTS = ['positive', 'neutral', 'negative'];

    /**
     * @var list<string>
     */
    private const ALLOWED_CATEGORIES = ['pelayanan', 'harga', 'produk', 'teknis', 'umum'];

    public function __construct(
        private ?string $apiKey = null,
        private ?string $model = null,
        private int $timeout = 30,
    ) {
        $this->apiKey ??= config('services.gemini.api_key');
        $this->model ??= config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    /**
     * @return array{sentiment: string, category: string}
     */
    public function analyzeSentiment(string $feedbackText): array
    {
        $responseText = $this->generateContent(
            systemInstruction: self::SENTIMENT_SYSTEM_INSTRUCTION,
            userContent: $feedbackText,
            jsonResponse: true,
        );

        $parsed = $this->decodeJsonResponse($responseText);

        $sentiment = $parsed['sentiment'] ?? null;
        $category = $parsed['category'] ?? null;

        if (! is_string($sentiment) || ! in_array($sentiment, self::ALLOWED_SENTIMENTS, true)) {
            throw new GeminiApiException('Gemini returned invalid sentiment value.');
        }

        if (! is_string($category) || ! in_array($category, self::ALLOWED_CATEGORIES, true)) {
            throw new GeminiApiException('Gemini returned invalid category value.');
        }

        return [
            'sentiment' => $sentiment,
            'category' => $category,
        ];
    }

    public function generateInsight(string $complaintsCompilation): string
    {
        $summary = trim($this->generateContent(
            systemInstruction: self::INSIGHT_SYSTEM_INSTRUCTION,
            userContent: $complaintsCompilation,
            jsonResponse: false,
        ));

        if ($summary === '') {
            throw new GeminiApiException('Gemini returned empty insight summary.');
        }

        return $summary;
    }

    private function generateContent(
        string $systemInstruction,
        string $userContent,
        bool $jsonResponse,
    ): string {
        if (! is_string($this->apiKey) || $this->apiKey === '') {
            throw new GeminiApiException('GEMINI_API_KEY is not configured.');
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userContent],
                    ],
                ],
            ],
        ];

        if ($jsonResponse) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
            ];
        }

        try {
            $response = $this->sendRequest($payload);
        } catch (ConnectionException $e) {
            throw new GeminiApiException('Gemini API connection timeout or unreachable.', 0, $e);
        } catch (Throwable $e) {
            throw new GeminiApiException('Gemini API request failed unexpectedly.', 0, $e);
        }

        if ($response->status() === 429) {
            throw new GeminiApiException($this->extractApiErrorMessage($response, 'Gemini API rate limit exceeded.'), 429);
        }

        if ($response->failed()) {
            throw new GeminiApiException(
                $this->extractApiErrorMessage($response, 'Gemini API request failed with HTTP '.$response->status().'.'),
                $response->status(),
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new GeminiApiException('Gemini API returned empty or invalid response.');
        }

        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $text): array
    {
        $cleaned = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $cleaned, $matches) === 1) {
            $cleaned = trim($matches[1]);
        }

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            throw new GeminiApiException('Gemini returned malformed JSON response.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendRequest(array $payload): \Illuminate\Http\Client\Response
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpointUrl(), $payload);

            if ($response->status() !== 503 || $attempt >= self::MAX_ATTEMPTS) {
                return $response;
            }

            usleep(self::RETRY_DELAY_MS * 1000);
        }
    }

    private function endpointUrl(): string
    {
        return self::BASE_URL.'/'.$this->model.':generateContent?key='.$this->apiKey;
    }

    private function extractApiErrorMessage(
        \Illuminate\Http\Client\Response $response,
        string $fallback,
    ): string {
        $apiMessage = data_get($response->json(), 'error.message');

        if (is_string($apiMessage) && $apiMessage !== '') {
            return 'Gemini API error: '.$apiMessage;
        }

        return $fallback;
    }
}
