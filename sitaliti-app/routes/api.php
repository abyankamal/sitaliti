<?php

use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\MasterDataApiController;
use App\Http\Controllers\Api\ReceiptApiController;
use App\Http\Controllers\Api\ReceiptWorkflowApiController;
use App\Http\Controllers\Api\RekeningManagementApiController;
use App\Http\Controllers\Api\SettlementApiController;
use App\Http\Controllers\Api\UserManagementApiController;
use App\Http\Controllers\Api\VerificationApiController;
use App\Http\Controllers\ControlSheetPdfController;
use App\Http\Controllers\ReceiptAttachmentController;
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
            Route::get('/', [ReceiptApiController::class, 'index'])
                ->middleware('permission:kuitansi.view');
            Route::get('export', [ReceiptApiController::class, 'export'])
                ->middleware('permission:kuitansi.view');
            Route::post('validate-draft', [ReceiptApiController::class, 'validateDraft'])
                ->middleware('permission:kuitansi.create|kuitansi.edit');
            Route::post('/', [ReceiptApiController::class, 'store'])
                ->middleware('permission:kuitansi.create');
            Route::put('{id}', [ReceiptApiController::class, 'update'])
                ->middleware('permission:kuitansi.edit');
            Route::post('{id}/update', [ReceiptApiController::class, 'update'])
                ->middleware('permission:kuitansi.edit');
            Route::delete('{id}/attachments/{attachmentId}', [ReceiptApiController::class, 'deleteAttachment'])
                ->middleware('permission:kuitansi.edit');
            Route::post('{id}/resubmit-revision', [ReceiptWorkflowApiController::class, 'resubmitRevision'])
                ->middleware('permission:kuitansi.submit_pptk|kuitansi.edit');
            Route::post('{id}/submit', [ReceiptWorkflowApiController::class, 'submit'])
                ->middleware('permission:kuitansi.submit_pptk');
            Route::post('{id}/pptk-approve', [ReceiptWorkflowApiController::class, 'pptkApprove'])
                ->middleware('permission:kuitansi.pptk_approve');
            Route::post('{id}/pptk-reject', [ReceiptWorkflowApiController::class, 'pptkReject'])
                ->middleware('permission:kuitansi.pptk_reject');
            Route::post('{id}/lurah-approve', [ReceiptWorkflowApiController::class, 'lurahApprove'])
                ->middleware('permission:kuitansi.lurah_approve');
            Route::post('{id}/lurah-reject', [ReceiptWorkflowApiController::class, 'lurahReject'])
                ->middleware('permission:kuitansi.lurah_reject');
            Route::get('{id}', [ReceiptApiController::class, 'show'])
                ->middleware('permission:kuitansi.view');
            Route::get('{kuitansiId}/attachments/{attachmentId}/preview', [ReceiptAttachmentController::class, 'preview'])
                ->middleware('permission:kuitansi.view');
            Route::get('{id}/history', [ReceiptApiController::class, 'history'])
                ->middleware('permission:kuitansi.view');
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
            Route::post('{id}/submit-ppk', [SettlementApiController::class, 'submitPpk'])
                ->middleware('permission:bundle.create');
            Route::post('{id}/approve-ppk', [SettlementApiController::class, 'approvePpk'])
                ->middleware('permission:verification.process_action');
            Route::post('{id}/sp2d', [SettlementApiController::class, 'recordSp2d'])
                ->middleware('permission:bundle.record_sp2d');
        });

        // Control Sheet Endpoints
        Route::prefix('control-sheet')->group(function () {
            Route::get('{id}', [ControlSheetPdfController::class, 'show'])
                ->middleware('permission:kuitansi.view');
            Route::get('{id}/pdf', [ControlSheetPdfController::class, 'download'])
                ->middleware('permission:kuitansi.view');
        });

        // Master Data Endpoints
        Route::prefix('master')->group(function () {
            Route::get('checklist/{category}', [MasterDataApiController::class, 'checklist']);
        });

        // User Management & PPTK Assignment Endpoints (Kecamatan Administrator / Kepegawaian)
        Route::prefix('users')->middleware('permission:user.manage')->group(function () {
            Route::get('/', [UserManagementApiController::class, 'index']);
            Route::get('sub-activities-options', [UserManagementApiController::class, 'subActivitiesOptions']);
            Route::post('/', [UserManagementApiController::class, 'store']);
            Route::put('{id}', [UserManagementApiController::class, 'update']);
            Route::delete('{id}', [UserManagementApiController::class, 'destroy']);
        });

        // Budget & Rekening Management Endpoints (Administrator Anggaran SKPD)
        Route::prefix('budget-management')->middleware('permission:user.manage')->group(function () {
            Route::get('rekening', [RekeningManagementApiController::class, 'index']);
            Route::get('sub-activities', [RekeningManagementApiController::class, 'subActivities']);
            Route::post('rekening', [RekeningManagementApiController::class, 'store']);
        });
    });
});
