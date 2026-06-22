<?php

use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'ResiliAI API',
    ]);
});

Route::post('/feedback', [FeedbackController::class, 'store']);
