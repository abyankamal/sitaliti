<?php

use App\Http\Controllers\Api\ReceiptApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SI-TALITI v2.0
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Receipts Endpoints (Serving receiptService.js)
    Route::prefix('receipts')->group(function () {
        Route::post('validate-draft', [ReceiptApiController::class, 'validateDraft']);
        Route::post('/', [ReceiptApiController::class, 'store']);
        Route::post('{id}/submit', [ReceiptApiController::class, 'submit']);
    });
});

