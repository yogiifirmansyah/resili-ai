<?php

use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('feedback submission validates required payload stores to mysql and returns 201', function () {
    $id = '550e8400-e29b-41d4-a716-446655440000';

    $response = $this->postJson('/api/feedback', [
        'id' => $id,
        'feedback_text' => 'Produk rusak saat diterima.',
        'customer_name' => 'Budi Santoso',
    ]);

    $response
        ->assertCreated()
        ->assertJsonFragment([
            'id' => $id,
            'customer_name' => 'Budi Santoso',
            'feedback_text' => 'Produk rusak saat diterima.',
            'status_ai' => 'pending',
        ])
        ->assertJsonStructure([
            'id',
            'customer_name',
            'feedback_text',
            'status_ai',
            'created_at',
            'updated_at',
        ]);

    $this->assertDatabaseHas('feedback', [
        'id' => $id,
        'customer_name' => 'Budi Santoso',
        'feedback_text' => 'Produk rusak saat diterima.',
        'status_ai' => 'pending',
    ]);

    expect(Feedback::find($id))->not->toBeNull();
});
