<?php

namespace App\Http\Controllers;

use App\Exceptions\DatabaseUnavailableException;
use App\Exceptions\GeminiApiException;
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

    #[OA\Get(
        path: '/feedback',
        operationId: 'listFeedback',
        summary: 'Daftar semua feedback',
        description: 'Mengambil seluruh feedback yang tersimpan di MySQL, diurutkan dari yang terbaru.',
        tags: ['Feedback'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar feedback berhasil diambil.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/FeedbackResource'),
                ),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->feedbackService->list());
        } catch (DatabaseUnavailableException) {
            return response()->json([
                'data' => [],
                'message' => 'Sistem dalam pemulihan, gagal memuat data.',
            ], 503);
        }
    }

    #[OA\Get(
        path: '/feedback/insight',
        operationId: 'feedbackInsight',
        summary: 'Executive summary keluhan pelanggan',
        description: 'Menganalisis 50–100 keluhan terbaru menggunakan Gemini AI dan mengembalikan rangkuman taktis untuk manajemen.',
        tags: ['Feedback'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Executive summary berhasil dibuat.',
                content: new OA\JsonContent(
                    required: ['insight'],
                    properties: [
                        new OA\Property(
                            property: 'insight',
                            type: 'string',
                            example: 'Mayoritas keluhan berfokus pada keterlambatan layanan pelanggan...',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 503,
                description: 'Gemini AI tidak tersedia atau gagal merespons.',
                content: new OA\JsonContent(ref: '#/components/schemas/ServerErrorResponse'),
            ),
        ],
    )]
    public function insight(): JsonResponse
    {
        try {
            return response()->json([
                'insight' => $this->feedbackService->insight(),
            ]);
        } catch (DatabaseUnavailableException) {
            return response()->json([
                'insight' => 'AI tidak dapat memproses insight saat ini karena database sedang offline.',
            ], 503);
        } catch (GeminiApiException $e) {
            return response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Gagal menghasilkan insight dari Gemini AI.',
            ], 503);
        }
    }

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
