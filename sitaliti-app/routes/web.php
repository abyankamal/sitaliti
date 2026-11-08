<?php

use App\Http\Controllers\ControlSheetPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\QrVerificationPublicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

// Routes Kuitansi, Workspace Verifikasi & Lembar Kendali SI-TALITI
Route::get('/receipts/create', function () {
    return Inertia::render('Receipts/Create');
})->name('receipts.create');

Route::get('/verification', function () {
    return Inertia::render('Verification/Index');
})->name('verification.index');

Route::get('/verification/{id}/workspace', function ($id) {
    return Inertia::render('Verification/Workspace', ['id' => $id]);
})->name('verification.workspace');

Route::get('/control-sheet/{id}', function ($id) {
    return Inertia::render('ControlSheet/Show', ['id' => $id]);
})->name('control-sheet.show');

Route::get('/control-sheet/{id}/download-pdf', [ControlSheetPdfController::class, 'download'])
    ->middleware('auth')
    ->name('control-sheet.download-pdf');

Route::get('/bundles', function () {
    return Inertia::render('Bundles/Index');
})->name('bundles.index');

// Rute Verifikasi Publik (Scan QR Code Fisik - Bebas Login)
Route::get('/verify/{qr_token}', [QrVerificationPublicController::class, 'show'])->name('public.verify');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
