<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

final class GeminiHttpFake
{
    /**
     * @param  array{sentiment?: string, category?: string}|null  $analysis
     */
    public static function fake(?array $analysis = null, ?string $insight = null): void
    {
        $analysis ??= [
            'sentiment' => 'negative',
            'category' => 'harga',
        ];

        $insight ??= 'Mayoritas keluhan berfokus pada harga produk. Manajemen disarankan meninjau struktur harga dan komunikasi value proposition.';

        Http::fake(function ($request) use ($analysis, $insight) {
            $body = $request->data();
            $systemInstruction = data_get($body, 'systemInstruction.parts.0.text', '');
            $jsonResponse = data_get($body, 'generationConfig.responseMimeType') === 'application/json';

            if ($jsonResponse || str_contains($systemInstruction, 'analis sentimen')) {
                return Http::response(self::geminiResponse(json_encode($analysis, JSON_THROW_ON_ERROR)), 200);
            }

            if (str_contains($systemInstruction, 'konsultan bisnis senior')) {
                return Http::response(self::geminiResponse($insight), 200);
            }

            return Http::response(self::geminiResponse(json_encode($analysis, JSON_THROW_ON_ERROR)), 200);
        });
    }

    public static function fakeFailure(int $status = 500): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Internal error',
                ],
            ], $status),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function geminiResponse(string $text): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ];
    }
}
