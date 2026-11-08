<?php

namespace App\Contracts;

use Barryvdh\DomPDF\PDF;

interface ControlSheetGeneratorServiceInterface
{
    /**
     * Mengambil data lengkap Lembar Kendali untuk dokumen cetak internal.
     *
     * @return array<string, mixed>
     */
    public function generateData(string $kuitansiIdOrNumber): array;

    /**
     * Menghasilkan dokumen PDF Lembar Kendali resmi siap unduh/cetak.
     */
    public function generatePdf(string|int $kuitansiIdOrNumber): PDF;

    /**
     * Mengambil data verifikasi publik untuk QR Code scanner dengan redaksi data sensitif (PII).
     *
     * @return array<string, mixed>
     */
    public function getPublicVerificationData(string $qrToken): array;

    /**
     * Sensor / redaksi data sensitif (NPWP, NIK, Rekening).
     */
    public function redactSensitiveData(?string $value, string $type = 'NPWP'): ?string;
}
