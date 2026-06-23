<?php

use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

test('sync command moves queued feedback from redis to mysql and clears redis list', function () {
    $queueKey = 'feedback_fallback_queue';

    $items = [
        [
            'id' => '11111111-1111-4111-8111-111111111111',
            'customer_name' => 'Alice',
            'feedback_text' => 'Layanan sangat memuaskan.',
            'status_ai' => 'pending',
        ],
        [
            'id' => '22222222-2222-4222-8222-222222222222',
            'customer_name' => 'Bob',
            'feedback_text' => 'Respon agen terlalu lambat.',
            'status_ai' => 'pending',
        ],
        [
            'id' => '33333333-3333-4333-8333-333333333333',
            'customer_name' => 'Charlie',
            'feedback_text' => 'Perlu perbaikan proses refund.',
            'status_ai' => 'pending',
        ],
    ];

    $remaining = array_map(fn (array $item) => json_encode($item), $items);

    Redis::shouldReceive('llen')
        ->with($queueKey)
        ->andReturnUsing(fn () => count($remaining));

    Redis::shouldReceive('lpop')
        ->with($queueKey)
        ->andReturnUsing(function () use (&$remaining) {
            return array_shift($remaining);
        });

    Redis::shouldReceive('lpush')->never();

    $this->artisan('resili:sync-feedback')->assertSuccessful();

    foreach ($items as $item) {
        $this->assertDatabaseHas('feedback', [
            'id' => $item['id'],
            'customer_name' => $item['customer_name'],
            'feedback_text' => $item['feedback_text'],
            'status_ai' => 'pending',
        ]);
    }

    expect($remaining)->toBeEmpty();
    expect(Feedback::count())->toBe(3);
});

test('sync command skips feedback that already exists in mysql and continues processing queue', function () {
    $queueKey = 'feedback_fallback_queue';
    $existingId = '550e8400-e29b-41d4-a716-446655440000';
    $newId = '44444444-4444-4444-8444-444444444444';

    Feedback::create([
        'id' => $existingId,
        'customer_name' => 'Budi Santoso',
        'feedback_text' => 'Produk rusak saat diterima.',
        'status_ai' => 'pending',
    ]);

    $items = [
        [
            'id' => $existingId,
            'customer_name' => 'Budi Santoso',
            'feedback_text' => 'Produk rusak saat diterima.',
            'status_ai' => 'pending',
        ],
        [
            'id' => $newId,
            'customer_name' => 'Diana',
            'feedback_text' => 'Pengiriman tepat waktu.',
            'status_ai' => 'pending',
        ],
    ];

    $remaining = array_map(fn (array $item) => json_encode($item), $items);

    Redis::shouldReceive('llen')
        ->with($queueKey)
        ->andReturnUsing(fn () => count($remaining));

    Redis::shouldReceive('lpop')
        ->with($queueKey)
        ->andReturnUsing(function () use (&$remaining) {
            return array_shift($remaining);
        });

    Redis::shouldReceive('lpush')->never();

    $this->artisan('resili:sync-feedback')->assertSuccessful();

    $this->assertDatabaseHas('feedback', [
        'id' => $newId,
        'customer_name' => 'Diana',
        'feedback_text' => 'Pengiriman tepat waktu.',
    ]);

    expect($remaining)->toBeEmpty();
    expect(Feedback::count())->toBe(2);
});
