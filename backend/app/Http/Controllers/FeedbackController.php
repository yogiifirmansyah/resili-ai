<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Feedback',
    description: 'Endpoint pengiriman feedback pelanggan',
)]
class FeedbackController extends Controller
{
    public function __construct(
        private FeedbackService $feedbackService,
    ) {}

    #[OA\Post(
        path: '/feedback',
        operationId: 'storeFeedback',
        summary: 'Kirim feedback pelanggan',
        description: 'Menyimpan feedback ke MySQL. Jika koneksi database gagal, data dialihkan ke antrean Redis fallback.',
        tags: ['Feedback'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreFeedbackRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Feedback berhasil disimpan ke MySQL dan job analisis AI dijadwalkan.',
                content: new OA\JsonContent(ref: '#/components/schemas/FeedbackResource'),
            ),
            new OA\Response(
                response: 202,
                description: 'MySQL tidak tersedia; feedback dimasukkan ke antrean Redis fallback.',
                content: new OA\JsonContent(ref: '#/components/schemas/FeedbackQueuedResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi input gagal (UUID tidak valid, field wajib kosong, atau ID duplikat).',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
            new OA\Response(
                response: 500,
                description: 'Kesalahan sistem internal (misalnya kegagalan non-koneksi database atau Redis).',
                content: new OA\JsonContent(ref: '#/components/schemas/ServerErrorResponse'),
            ),
        ],
    )]
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $result = $this->feedbackService->store(array_merge($request->validated(), [
            'status_ai' => 'pending',
        ]));

        if ($result->queued) {
            return response()->json([
                'message' => 'Feedback disimpan di antrean fallback karena database sementara tidak tersedia.',
                'id' => $result->id,
                'queued' => true,
            ], 202);
        }

        return response()->json($result->feedback, 201);
    }
}
