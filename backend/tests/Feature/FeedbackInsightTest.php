<?php

use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\GeminiHttpFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.gemini.api_key' => 'test-gemini-api-key']);
});

test('feedback insight endpoint returns executive summary from gemini', function () {
    GeminiHttpFake::fake(
        insight: 'Keluhan terbaru menunjukkan masalah utama pada keterlambatan layanan. Manajemen disarankan menambah kapasitas tim support di jam sibuk.',
    );

    Feedback::create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'customer_name' => 'Budi',
        'feedback_text' => 'Layanan sangat lambat.',
        'status_ai' => 'completed',
        'sentiment' => 'negative',
        'category' => 'pelayanan',
    ]);

    $response = $this->getJson('/api/feedback/insight');

    $response->assertOk()
        ->assertJson([
            'insight' => 'Keluhan terbaru menunjukkan masalah utama pada keterlambatan layanan. Manajemen disarankan menambah kapasitas tim support di jam sibuk.',
        ]);

    Http::assertSent(function ($request) {
        $systemInstruction = data_get($request->data(), 'systemInstruction.parts.0.text', '');

        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($systemInstruction, 'konsultan bisnis senior')
            && str_contains(data_get($request->data(), 'contents.0.parts.0.text', ''), 'Layanan sangat lambat.');
    });
});

test('feedback insight endpoint returns fallback message when no complaints exist', function () {
    Http::fake();

    $this->getJson('/api/feedback/insight')
        ->assertOk()
        ->assertJson([
            'insight' => 'Belum ada keluhan pelanggan yang dapat dianalisis.',
        ]);

    Http::assertNothingSent();
});

test('feedback insight endpoint returns service unavailable when gemini fails', function () {
    GeminiHttpFake::fakeFailure(503);

    Feedback::create([
        'id' => '650e8400-e29b-41d4-a716-446655440001',
        'customer_name' => 'Siti',
        'feedback_text' => 'Harga terlalu mahal.',
        'status_ai' => 'completed',
        'sentiment' => 'negative',
        'category' => 'harga',
    ]);

    $this->getJson('/api/feedback/insight')
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});
