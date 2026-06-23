<?php

use App\Jobs\AnalyzeFeedbackSentiment;
use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('analyze feedback sentiment job processes pending feedback through completed with sentiment and category', function () {
    $feedback = Feedback::create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'customer_name' => 'Budi Santoso',
        'feedback_text' => 'Harganya mahal sekali, saya rugi dan kecewa.',
        'status_ai' => 'pending',
    ]);

    (new AnalyzeFeedbackSentiment($feedback))->handle();

    $feedback->refresh();

    expect($feedback->status_ai)->toBe('completed')
        ->and($feedback->sentiment)->toBe('negative')
        ->and($feedback->category)->toBe('harga');
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
