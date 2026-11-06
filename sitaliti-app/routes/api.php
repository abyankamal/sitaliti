<?php

use App\Http\Controllers\Api\ReceiptApiController;
use App\Http\Controllers\Api\VerificationApiController;
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

    // Verification Workspace Endpoints
    Route::prefix('verification')->group(function () {
        Route::get('queue', [VerificationApiController::class, 'getQueue']);
        Route::get('{id}', [VerificationApiController::class, 'getDetail']);
        Route::post('{id}/action', [VerificationApiController::class, 'processAction']);
    });
});
