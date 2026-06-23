<?php

use App\Jobs\AnalyzeFeedbackSentiment;
use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GeminiHttpFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.gemini.api_key' => 'test-gemini-api-key',
        'services.gemini.throttle_seconds' => 0,
    ]);
});

test('analyze feedback sentiment job processes pending feedback through completed with sentiment and category', function () {
    GeminiHttpFake::fake([
        'sentiment' => 'negative',
        'category' => 'harga',
    ]);

    $feedback = Feedback::create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'customer_name' => 'Budi Santoso',
        'feedback_text' => 'Harganya mahal sekali, saya rugi dan kecewa.',
        'status_ai' => 'pending',
    ]);

    (new AnalyzeFeedbackSentiment($feedback))->handle(app(\App\Services\GeminiClient::class));

    $feedback->refresh();

    expect($feedback->status_ai)->toBe('completed')
        ->and($feedback->sentiment)->toBe('negative')
        ->and($feedback->category)->toBe('harga');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request->url(), 'key=test-gemini-api-key')
            && data_get($request->data(), 'contents.0.parts.0.text') === 'Harganya mahal sekali, saya rugi dan kecewa.';
    });
});

test('analyze feedback sentiment job releases back to queue when gemini api is rate limited', function () {
    GeminiHttpFake::fakeFailure(429);

    $feedback = Feedback::create([
        'id' => '650e8400-e29b-41d4-a716-446655440001',
        'customer_name' => 'Ani Wijaya',
        'feedback_text' => 'Produk tidak sesuai harapan.',
        'status_ai' => 'pending',
    ]);

    $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $queueJob->shouldReceive('release')
        ->once()
        ->with(30);

    $job = new AnalyzeFeedbackSentiment($feedback);
    $job->setJob($queueJob);
    $job->handle(app(\App\Services\GeminiClient::class));

    $feedback->refresh();

    expect($feedback->status_ai)->toBe('processing')
        ->and($feedback->sentiment)->toBeNull()
        ->and($feedback->category)->toBeNull();
});

test('analyze feedback sentiment job marks feedback as failed when gemini api fails with non rate limit error', function () {
    GeminiHttpFake::fakeFailure(500);

    $feedback = Feedback::create([
        'id' => '750e8400-e29b-41d4-a716-446655440002',
        'customer_name' => 'Ani Wijaya',
        'feedback_text' => 'Produk tidak sesuai harapan.',
        'status_ai' => 'pending',
    ]);

    (new AnalyzeFeedbackSentiment($feedback))->handle(app(\App\Services\GeminiClient::class));

    $feedback->refresh();

    expect($feedback->status_ai)->toBe('failed')
        ->and($feedback->sentiment)->toBeNull()
        ->and($feedback->category)->toBeNull();
});

test('new feedback dispatches analyze feedback sentiment job to redis queue', function () {
    Queue::fake();

    $feedback = Feedback::create([
        'id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
        'customer_name' => 'Siti Aminah',
        'feedback_text' => 'Layanan sangat bagus dan memuaskan.',
        'status_ai' => 'pending',
    ]);

    AnalyzeFeedbackSentiment::dispatch($feedback);

    Queue::assertPushed(AnalyzeFeedbackSentiment::class, function (AnalyzeFeedbackSentiment $job) use ($feedback) {
        return $job->connection === 'redis' && $job->feedback->id === $feedback->id;
    });
});

test('feedback submission dispatches analyze feedback sentiment job to redis after mysql save', function () {
    Queue::fake();

    $id = '8d4e3f2a-1b5c-4d6e-9f0a-2b3c4d5e6f7a';

    $this->postJson('/api/feedback', [
        'id' => $id,
        'feedback_text' => 'Produk rusak saat diterima.',
        'customer_name' => 'Budi Santoso',
    ])->assertCreated();

    Queue::assertPushed(AnalyzeFeedbackSentiment::class, function (AnalyzeFeedbackSentiment $job) use ($id) {
        return $job->connection === 'redis' && $job->feedback->id === $id;
    });
});
