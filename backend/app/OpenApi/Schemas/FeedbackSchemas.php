<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreFeedbackRequest',
    required: ['id', 'feedback_text'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'string',
            format: 'uuid',
            pattern: '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
            example: '550e8400-e29b-41d4-a716-446655440000',
            description: 'UUID v4 yang dihasilkan klien, digunakan sebagai idempotency key.',
        ),
        new OA\Property(
            property: 'feedback_text',
            type: 'string',
            example: 'Produk rusak saat diterima.',
            description: 'Isi feedback dari pelanggan.',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeedbackResource',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'customer_name', type: 'string', nullable: true, example: 'Budi Santoso'),
        new OA\Property(property: 'feedback_text', type: 'string', example: 'Produk rusak saat diterima.'),
        new OA\Property(property: 'sentiment', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'status_ai', type: 'string', example: 'pending'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-23T10:00:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-23T10:00:00.000000Z'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeedbackQueuedResponse',
    required: ['message', 'id', 'queued'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Feedback disimpan di antrean fallback karena database sementara tidak tersedia.',
        ),
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '7c9e6679-7425-40de-944b-e07fc1f90ae7'),
        new OA\Property(property: 'queued', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The id field is required. (and 1 more error)'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: [
                'id' => ['The id field is required.'],
                'feedback_text' => ['The feedback text field is required.'],
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ServerErrorResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Server Error'),
    ],
    type: 'object',
)]
final class FeedbackSchemas
{
}
