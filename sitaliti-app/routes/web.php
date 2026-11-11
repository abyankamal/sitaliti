<?php

use App\Http\Controllers\ControlSheetPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\QrVerificationPublicController;
use App\Http\Controllers\ReceiptAttachmentController;
use App\Http\Controllers\Web\ReceiptWebController;
use App\Models\Kuitansi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rute Verifikasi Publik (Scan QR Code Fisik - Bebas Login)
Route::get('/verify/{qr_token}', [QrVerificationPublicController::class, 'show'])->name('public.verify');

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

    // Routes Verifikasi & Workspace
    Route::get('/verification', function () {
        return Inertia::render('Verification/Index');
    })->middleware('permission:verification.queue')->name('verification.index');

    Route::get('/verification/{id}/workspace', function (Request $request, $id) {
        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', (string) $id)->firstOrFail();
        if (! $request->user()->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka ruang verifikasi kuitansi ini.');
        }

        return Inertia::render('Verification/Workspace', ['id' => $id]);
    })->middleware('permission:verification.audit')->name('verification.workspace');

    // Routes Lembar Kendali & Lampiran
    Route::get('/control-sheet/{id}', function (Request $request, $id) {
        $kuitansi = Kuitansi::where('id', $id)->orWhere('nomor_kuitansi', (string) $id)->firstOrFail();
        if (! $request->user()->canAccessReceipt($kuitansi)) {
            abort(403, 'Anda tidak memiliki hak akses untuk membuka lembar kendali kuitansi ini.');
        }

        return Inertia::render('ControlSheet/Show', ['id' => $id]);
    })->middleware('permission:kuitansi.view')->name('control-sheet.show');

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

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
