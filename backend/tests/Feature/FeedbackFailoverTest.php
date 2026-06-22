<?php

use App\Models\Feedback;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

test('feedback request validates payload without requiring a live mysql connection', function () {
    $this->postJson('/api/feedback', [
        'id' => 'bukan-uuid',
        'feedback_text' => '',
    ])->assertUnprocessable();
});

test('feedback is queued to redis when database create fails with connection error', function () {
    $id = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    Feedback::creating(function () {
        throw new QueryException(
            'mysql',
            'insert into `feedback` (`id`, `feedback_text`, `updated_at`, `created_at`) values (?, ?, ?, ?)',
            [],
            new PDOException('SQLSTATE[HY000] [2002] Connection refused')
        );
    });

    Redis::shouldReceive('rpush')
        ->once()
        ->withArgs(function (string $key, string $payload) use ($id) {
            expect($key)->toBe('feedback_fallback_queue');

            $data = json_decode($payload, true);

            expect($data)->toMatchArray([
                'id' => $id,
                'customer_name' => 'Siti Aminah',
                'feedback_text' => 'Layanan lambat merespons.',
                'status_ai' => 'pending',
            ]);

            return true;
        })
        ->andReturn(1);

    $response = $this->postJson('/api/feedback', [
        'id' => $id,
        'feedback_text' => 'Layanan lambat merespons.',
        'customer_name' => 'Siti Aminah',
    ]);

    $response
        ->assertAccepted()
        ->assertJson([
            'id' => $id,
            'queued' => true,
            'message' => 'Feedback disimpan di antrean fallback karena database sementara tidak tersedia.',
        ]);

    $this->assertDatabaseMissing('feedback', ['id' => $id]);
});

test('non connection query exceptions are not sent to redis fallback', function () {
    $id = '8d4e3f2a-1b5c-4d6e-9f0a-2b3c4d5e6f7a';

    Feedback::creating(function () {
        throw new QueryException(
            'mysql',
            'insert into `feedback` (`id`, `feedback_text`) values (?, ?)',
            [],
            new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')
        );
    });

    Redis::shouldReceive('rpush')->never();

    $this->postJson('/api/feedback', [
        'id' => $id,
        'feedback_text' => 'Duplikasi data.',
    ])->assertStatus(500);
});
