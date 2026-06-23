<?php

use App\Contracts\FeedbackRepositoryInterface;
use App\Models\Feedback;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

function connectionRefusedQueryException(string $sql = 'select * from `feedback`'): QueryException
{
    return new QueryException(
        'mysql',
        $sql,
        [],
        new PDOException('SQLSTATE[HY000] [2002] Connection refused')
    );
}

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

test('feedback is queued to redis when idempotency check fails with connection error', function () {
    $id = '9c9e6679-7425-40de-944b-e07fc1f90ae7';

    $this->mock(FeedbackRepositoryInterface::class, function ($mock) use ($id) {
        $mock->shouldReceive('existsById')
            ->once()
            ->with($id)
            ->andThrow(connectionRefusedQueryException('select exists(select * from `feedback` where `id` = ?) as `exists`'));

        $mock->shouldReceive('pushToFallbackQueue')
            ->once()
            ->withArgs(function (array $payload) use ($id) {
                expect($payload)->toMatchArray([
                    'id' => $id,
                    'feedback_text' => 'Database mati saat pengecekan UUID.',
                    'status_ai' => 'pending',
                ]);

                return true;
            });
    });

    $this->postJson('/api/feedback', [
        'id' => $id,
        'feedback_text' => 'Database mati saat pengecekan UUID.',
    ])
        ->assertAccepted()
        ->assertJson([
            'id' => $id,
            'queued' => true,
        ]);
});

test('feedback list returns graceful 503 when database is unavailable', function () {
    $this->mock(FeedbackRepositoryInterface::class, function ($mock) {
        $mock->shouldReceive('all')
            ->once()
            ->andThrow(connectionRefusedQueryException());
    });

    $this->getJson('/api/feedback')
        ->assertStatus(503)
        ->assertJson([
            'data' => [],
            'message' => 'Sistem dalam pemulihan, gagal memuat data.',
        ]);
});

test('feedback insight returns graceful 503 when database is unavailable', function () {
    $this->mock(FeedbackRepositoryInterface::class, function ($mock) {
        $mock->shouldReceive('latestComplaints')
            ->once()
            ->andThrow(connectionRefusedQueryException('select * from `feedback` order by `created_at` desc limit 100'));
    });

    $this->getJson('/api/feedback/insight')
        ->assertStatus(503)
        ->assertJson([
            'insight' => 'AI tidak dapat memproses insight saat ini karena database sedang offline.',
        ]);
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
