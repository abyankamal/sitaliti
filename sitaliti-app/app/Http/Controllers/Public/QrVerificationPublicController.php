<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class QrVerificationPublicController extends Controller
{
    /**
     * Halaman verifikasi publik saat QR Code di Lembar Kendali fisik di-scan.
     * Bebas login, namun data sensitif vendor diredaksi untuk privasi.
     */
    public function show(string $qrToken): Response
    {
        return Inertia::render('Public/VerifyReceipt', [
            'qrToken' => $qrToken,
            'isVerified' => true,
        ]);
    }
}
