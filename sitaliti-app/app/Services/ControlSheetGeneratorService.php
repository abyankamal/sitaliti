<?php

namespace App\Services;

use App\Contracts\ControlSheetGeneratorServiceInterface;
use App\Models\Kuitansi;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class ControlSheetGeneratorService implements ControlSheetGeneratorServiceInterface
{
    /**
     * Mengambil data lengkap Lembar Kendali untuk dokumen cetak internal.
     *
     * @return array<string, mixed>
     */
    public function generateData(string $kuitansiIdOrNumber): array
    {
        $kuitansi = Kuitansi::with([
            'unitKerja',
            'rekening',
            'items',
            'pajak',
            'creator',
            'verifikator',
            'checklistVerifikasi.checklistItem',
        ])
            ->where('id', $kuitansiIdOrNumber)
            ->orWhere('nomor_kuitansi', $kuitansiIdOrNumber)
            ->firstOrFail();

        $validStatuses = ['LOLOS_VERIFIKASI', 'TERVERIFIKASI', 'DIBUNDEL', 'SELESAI_SP2D', 'SYNCED_SIPD'];
        if (! in_array($kuitansi->status, $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Lembar Kendali hanya dapat diterbitkan untuk kuitansi yang telah lolos verifikasi akuntabilitas. Status kuitansi saat ini: {$kuitansi->status}"
            );
        }

        $tahun = $kuitansi->tanggal_transaksi ? $kuitansi->tanggal_transaksi->format('Y') : date('Y');
        $registerNumber = "LK-V2/{$kuitansi->nomor_kuitansi}/{$tahun}";

        return [
            'id' => $kuitansi->id,
            'nomor_kuitansi' => $kuitansi->nomor_kuitansi,
            'nomor_register' => $registerNumber,
            'status' => $kuitansi->status,
            'tanggal_transaksi' => $kuitansi->tanggal_transaksi?->format('Y-m-d'),
            'tanggal_verifikasi' => $kuitansi->tanggal_verifikasi?->format('Y-m-d H:i:s'),
            'uraian' => $kuitansi->uraian,
            'nama_penerima' => $kuitansi->nama_penerima,
            'npwp_penerima' => $kuitansi->npwp_penerima,
            'nominal_bruto' => (float) $kuitansi->nominal_bruto,
            'total_pajak' => (float) $kuitansi->total_pajak,
            'nominal_neto' => (float) $kuitansi->nominal_neto,
            'qr_token' => $kuitansi->qr_token,
            'verification_url' => $kuitansi->qr_token ? url("/verify/{$kuitansi->qr_token}") : null,
            'catatan_verifikasi' => $kuitansi->catatan_verifikasi,
            'unit_kerja' => $kuitansi->unitKerja,
            'rekening' => $kuitansi->rekening,
            'items' => $kuitansi->items,
            'pajak' => $kuitansi->pajak,
            'verifikator' => $kuitansi->verifikator ? [
                'id' => $kuitansi->verifikator->id,
                'name' => $kuitansi->verifikator->name,
                'nip' => $kuitansi->verifikator->nip ?? '-',
                'jabatan' => $kuitansi->verifikator->jabatan ?? 'Tim Verifikasi Akuntabilitas SPJ',
            ] : null,
            'checklist' => $kuitansi->checklistVerifikasi->map(function ($cv) {
                return [
                    'kode_item' => $cv->checklistItem?->kode_item,
                    'nama_item' => $cv->checklistItem?->nama_item,
                    'is_verified' => $cv->is_verified,
                    'status_kesesuaian' => $cv->status_kesesuaian,
                    'catatan' => $cv->catatan,
                ];
            })->all(),
        ];
    }

    /**
     * Menghasilkan dokumen PDF Lembar Kendali resmi siap unduh/cetak.
     */
    public function generatePdf(string|int $kuitansiIdOrNumber): DomPdfInstance
    {
        $data = $this->generateData((string) $kuitansiIdOrNumber);

        // Render QR Code SVG offline menggunakan BaconQrCode
        $verificationUrl = $data['verification_url'] ?? ($data['qr_token'] ? url("/verify/{$data['qr_token']}") : url("/verify/{$kuitansiIdOrNumber}"));

        $renderer = new ImageRenderer(
            new RendererStyle(120, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $data['qr_code_svg'] = $writer->writeString($verificationUrl);

        /** @var DomPdfInstance $pdf */
        $pdf = Pdf::loadView('pdf.lembar-kendali', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Mengambil data verifikasi publik untuk QR Code scanner dengan redaksi data sensitif (PII).
     *
     * @return array<string, mixed>
     */
    public function getPublicVerificationData(string $qrToken): array
    {
        $kuitansi = Kuitansi::with([
            'unitKerja',
            'rekening',
            'verifikator',
        ])
            ->where('qr_token', $qrToken)
            ->first();

        if (! $kuitansi) {
            throw new ModelNotFoundException('Dokumen Lembar Kendali tidak ditemukan atau token QR tidak valid.');
        }

        $validStatuses = ['LOLOS_VERIFIKASI', 'DIBUNDEL', 'SELESAI_SP2D'];
        $isValid = in_array($kuitansi->status, $validStatuses, true);

        $statusLabels = [
            'LOLOS_VERIFIKASI' => 'Lolos Verifikasi Akuntabel',
            'DIBUNDEL' => 'Tergabung dalam Pengajuan SPP/LPJ',
            'SELESAI_SP2D' => 'Sah dan Telah Terbit SP2D SIPD-RI',
            'REVISI_MINOR' => 'Perlu Revisi Minor',
            'REVISI_MAYOR' => 'Dikembalikan / Revisi Mayor',
            'DRAFT' => 'Belum Diverifikasi',
        ];

        $tahun = $kuitansi->tanggal_transaksi ? $kuitansi->tanggal_transaksi->format('Y') : date('Y');

        return [
            'qr_token' => $kuitansi->qr_token,
            'is_valid' => $isValid,
            'nomor_kuitansi' => $kuitansi->nomor_kuitansi,
            'nomor_register' => "LK-V2/{$kuitansi->nomor_kuitansi}/{$tahun}",
            'status' => $kuitansi->status,
            'status_label' => $statusLabels[$kuitansi->status] ?? $kuitansi->status,
            'unit_kerja' => $kuitansi->unitKerja?->nama_unit ?? 'Pemerintah Kabupaten Garut',
            'tipe_unit' => $kuitansi->unitKerja?->tipe ?? 'KECAMATAN',
            'sub_kegiatan' => $kuitansi->rekening?->nama_sub_kegiatan ?? '-',
            'kode_rekening' => $kuitansi->rekening?->kode_rekening ?? '-',
            'nama_rekening' => $kuitansi->rekening?->nama_rekening ?? '-',
            'kategori_belanja' => $kuitansi->rekening?->kategori_belanja ?? 'B.1',
            'uraian' => $kuitansi->uraian,
            'tanggal_transaksi' => $kuitansi->tanggal_transaksi?->format('Y-m-d'),
            'tanggal_verifikasi' => $kuitansi->tanggal_verifikasi?->format('Y-m-d H:i:s'),
            'verifikator' => [
                'nama' => $kuitansi->verifikator?->name ?? 'Tim Verifikasi Kecamatan Tarogong Kidul',
                'nip' => $kuitansi->verifikator?->nip ? $this->redactSensitiveData($kuitansi->verifikator->nip, 'NIP') : '-',
                'jabatan' => $kuitansi->verifikator?->jabatan ?? 'Verifikator SPJ',
            ],
            'nominal_bruto' => (float) $kuitansi->nominal_bruto,
            'total_pajak' => (float) $kuitansi->total_pajak,
            'nominal_neto' => (float) $kuitansi->nominal_neto,
            'nama_penerima_redacted' => $this->redactSensitiveData($kuitansi->nama_penerima, 'NAMA'),
            'npwp_penerima_redacted' => $this->redactSensitiveData($kuitansi->npwp_penerima, 'NPWP'),
            'is_sensored' => true,
            'catatan_verifikasi' => $kuitansi->catatan_verifikasi,
        ];
    }

    /**
     * Sensor / redaksi data sensitif (NPWP, NIK, Nama Rekanan, Rekening).
     */
    public function redactSensitiveData(?string $value, string $type = 'NPWP'): ?string
    {
        if (empty($value)) {
            return null;
        }

        $type = strtoupper($type);

        if ($type === 'NPWP') {
            // NPWP format 15 digits: XX.XXX.XXX.X-XXX.XXX
            // Sensor digit tengah dan belakang: XX.***.***.*-***.***
            $len = strlen($value);
            if ($len <= 4) {
                return str_repeat('*', $len);
            }

            // Pertahankan 2 karakter pertama, ganti angka selanjutnya dengan '*'
            return substr($value, 0, 2).preg_replace('/[0-9]/', '*', substr($value, 2));
        }

        if ($type === 'NAMA') {
            // Sensor nama rekanan per kata: "CV Sinar Garut" -> "CV S**** G****"
            $words = explode(' ', trim($value));
            $maskedWords = [];

            foreach ($words as $word) {
                $wordLen = strlen($word);
                if ($wordLen <= 2) {
                    $maskedWords[] = $word; // CV, PT, UD dibiarkan terbaca
                } else {
                    $maskedWords[] = substr($word, 0, 1).str_repeat('*', $wordLen - 1);
                }
            }

            return implode(' ', $maskedWords);
        }

        if ($type === 'NIK' || $type === 'NIP' || $type === 'REKENING') {
            // Pertahankan 4 digit awal dan 2 digit akhir, sensor bagian tengah
            $len = strlen($value);
            if ($len <= 6) {
                return str_repeat('*', $len);
            }

            $prefix = substr($value, 0, 4);
            $suffix = substr($value, -2);
            $maskedMiddle = str_repeat('*', $len - 6);

            return "{$prefix}{$maskedMiddle}{$suffix}";
        }

        return substr($value, 0, 2).'******';
    }
}
