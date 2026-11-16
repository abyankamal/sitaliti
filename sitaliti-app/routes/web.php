<?php

use App\Http\Controllers\ControlSheetPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\QrVerificationPublicController;
use App\Http\Controllers\ReceiptAttachmentController;
use App\Http\Controllers\Web\ReceiptWebController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rute Verifikasi Publik (Scan QR Code Fisik - Bebas Login)
Route::get('/verify/{qr_token}', [QrVerificationPublicController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('public.verify');

// Rute Internal Terproteksi Autentikasi & RBAC
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Routes Kuitansi
    Route::get('/receipts', [ReceiptWebController::class, 'index'])
        ->middleware('permission:kuitansi.view')
        ->name('receipts.index');

    Route::get('/receipts/create', function () {
        return Inertia::render('Receipts/Create');
    })->middleware('permission:kuitansi.create')->name('receipts.create');

    Route::get('/receipts/{id}/edit', [ReceiptWebController::class, 'edit'])
        ->middleware('permission:kuitansi.create')
        ->name('receipts.edit');

    // Routes Verifikasi & Workspace
    Route::get('/verification', function () {
        return Inertia::render('Verification/Index');
    })->middleware('permission:verification.queue')->name('verification.index');

    Route::get('/verification/{id}/workspace', [ReceiptWebController::class, 'verificationWorkspace'])
        ->middleware('permission:verification.audit')
        ->name('verification.workspace');

    // Routes Lembar Kendali & Lampiran
    Route::get('/control-sheet/{id}', [ReceiptWebController::class, 'controlSheet'])
        ->middleware('permission:kuitansi.view')
        ->name('control-sheet.show');

    Route::get('/control-sheet/{id}/download-pdf', [ControlSheetPdfController::class, 'download'])
        ->middleware('permission:kuitansi.view')
        ->name('control-sheet.download-pdf');

    Route::get('/receipts/{kuitansiId}/attachments/{attachmentId}/preview', [ReceiptAttachmentController::class, 'preview'])
        ->middleware('permission:kuitansi.view')
        ->name('receipts.attachments.preview');

    // Routes Bundles / SPP
    Route::get('/bundles', function () {
        return Inertia::render('Bundles/Index');
    })->middleware('permission:bundle.view')->name('bundles.index');

    // Routes Manajemen Pengguna & Penugasan PPTK (Kecamatan Administrator / Kepegawaian)
    Route::get('/users', function () {
        return Inertia::render('Users/Index');
    })->middleware('permission:user.manage')->name('users.index');

    // Routes Manajemen Kode Rekening & Alokasi Pagu (Administrator Anggaran SKPD)
    Route::get('/budget-management', function (Request $request) {
        abort_if($request->user()?->isKelurahanScoped(), 403, 'Hanya administrator tingkat SKPD yang dapat mengakses halaman ini.');

        return Inertia::render('Budget/Manage');
    })->middleware('permission:user.manage')->name('budget-management.index');

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
