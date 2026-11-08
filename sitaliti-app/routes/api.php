<?php

use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\ReceiptApiController;
use App\Http\Controllers\Api\SettlementApiController;
use App\Http\Controllers\Api\VerificationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SI-TALITI v2.0
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Seluruh rute API operasional wajib terautentikasi (auth / stateful session)
    Route::middleware(['auth'])->group(function () {
        // Budget & RAK Endpoints (Open Access Terpusat: Semua peran terautentikasi dapat melihat)
        Route::prefix('budget')->group(function () {
            Route::get('summary', [BudgetApiController::class, 'summary']);
            Route::get('units', [BudgetApiController::class, 'units']);
            Route::get('sub-activities', [BudgetApiController::class, 'subActivities']);
            Route::get('rak-context/{rekeningId}', [BudgetApiController::class, 'rakContext']);
        });

        // Receipts Endpoints
        Route::prefix('receipts')->group(function () {
            Route::post('validate-draft', [ReceiptApiController::class, 'validateDraft']);
            Route::post('/', [ReceiptApiController::class, 'store'])
                ->middleware('permission:kuitansi.create');
            Route::post('{id}/submit', [ReceiptApiController::class, 'submit'])
                ->middleware('permission:kuitansi.submit_pptk');
            Route::post('{id}/pptk-approve', [ReceiptApiController::class, 'pptkApprove'])
                ->middleware('permission:kuitansi.pptk_approve');
            Route::post('{id}/pptk-reject', [ReceiptApiController::class, 'pptkReject'])
                ->middleware('permission:kuitansi.pptk_reject');
        });

        // Verification Workspace Endpoints
        Route::prefix('verification')->group(function () {
            Route::get('queue', [VerificationApiController::class, 'getQueue'])
                ->middleware('permission:verification.queue');
            Route::get('{id}', [VerificationApiController::class, 'getDetail'])
                ->middleware('permission:verification.audit');
            Route::post('{id}/action', [VerificationApiController::class, 'processAction'])
                ->middleware('permission:verification.process_action');
        });

        // Bundles / Settlement Endpoints (Two-Tier Settlement: BPP & KPA)
        Route::prefix('bundles')->group(function () {
            Route::get('/', [SettlementApiController::class, 'index'])
                ->middleware('permission:bundle.view');
            Route::post('/', [SettlementApiController::class, 'store'])
                ->middleware('permission:bundle.create');
            Route::get('{id}', [SettlementApiController::class, 'show'])
                ->middleware('permission:bundle.view');
            Route::post('{id}/unbundle/{kuitansiId}', [SettlementApiController::class, 'unbundle'])
                ->middleware('permission:bundle.unbundle');
            Route::post('{id}/sp2d', [SettlementApiController::class, 'recordSp2d'])
                ->middleware('permission:bundle.record_sp2d');
        });
    });
});
