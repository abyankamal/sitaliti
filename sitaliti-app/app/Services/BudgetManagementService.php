<?php

namespace App\Services;

use App\Models\AnggaranAuditLog;
use App\Models\AnggaranKasBulanan;
use App\Models\AnggaranRekening;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BudgetManagementService
{
    public const MAX_DECIMAL_15_2 = 9999999999999.99;

    /**
     * Menormalisasi array alokasi 12 bulan (menerima list 0..11 maupun map 1..12)
     * menjadi map terurut [1 => float, ..., 12 => float] dengan validasi batas total decimal(15,2).
     *
     * @param  array<int|string, mixed>  $alokasi
     * @return array<int, float>
     */
    public function normalizeAllocations(array $alokasi): array
    {
        if (count($alokasi) !== 12) {
            throw ValidationException::withMessages([
                'alokasi' => ['Alokasi kas bulanan wajib terdiri dari tepat 12 bulan (Januari–Desember).'],
            ]);
        }

        $normalized = [];

        if (array_is_list($alokasi)) {
            foreach ($alokasi as $idx => $value) {
                $normalized[$idx + 1] = $this->parseMonthlyAmount($value, $idx + 1);
            }
        } else {
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                if (! array_key_exists($bulan, $alokasi) && ! array_key_exists((string) $bulan, $alokasi)) {
                    throw ValidationException::withMessages([
                        'alokasi' => ["Key bulan {$bulan} tidak ditemukan pada array alokasi."],
                    ]);
                }

                $raw = $alokasi[$bulan] ?? $alokasi[(string) $bulan];
                $normalized[$bulan] = $this->parseMonthlyAmount($raw, $bulan);
            }
        }

        $total = round(array_sum($normalized), 2);

        if ($total <= 0.0) {
            throw ValidationException::withMessages([
                'alokasi' => ['Total alokasi pagu 12 bulan harus lebih besar dari Rp 0.'],
            ]);
        }

        if ($total > self::MAX_DECIMAL_15_2) {
            throw ValidationException::withMessages([
                'alokasi' => ['Total alokasi pagu 12 bulan melebihi batas maksimum sistem (Rp 9.999.999.999.999,99).'],
            ]);
        }

        return $normalized;
    }

    /**
     * Membuat kode rekening baru beserta tepat 12 baris alokasi kas bulanan secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRekening(array $data, ?User $actor = null, ?string $ipAddress = null): AnggaranRekening
    {
        $alokasi = $this->normalizeAllocations($data['alokasi'] ?? []);
        $total = round(array_sum($alokasi), 2);

        $unitKerjaId = (int) $data['unit_kerja_id'];
        $kodeSubkegiatan = trim((string) $data['kode_sub_kegiatan']);
        $kodeRekening = trim((string) $data['kode_rekening']);

        $exists = AnggaranRekening::where('unit_kerja_id', $unitKerjaId)
            ->where('kode_sub_kegiatan', $kodeSubkegiatan)
            ->where('kode_rekening', $kodeRekening)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'kode_rekening' => ["Kode rekening {$kodeRekening} sudah terdaftar pada subkegiatan {$kodeSubkegiatan}."],
            ]);
        }

        $namaSubkegiatan = $this->resolveSubkegiatanName(
            $unitKerjaId,
            $kodeSubkegiatan,
            trim((string) $data['nama_sub_kegiatan'])
        );

        try {
            return DB::transaction(function () use ($data, $unitKerjaId, $kodeSubkegiatan, $namaSubkegiatan, $kodeRekening, $alokasi, $total, $actor, $ipAddress) {
                $rekening = AnggaranRekening::create([
                    'unit_kerja_id' => $unitKerjaId,
                    'kode_sub_kegiatan' => $kodeSubkegiatan,
                    'nama_sub_kegiatan' => $namaSubkegiatan,
                    'kode_rekening' => $kodeRekening,
                    'nama_rekening' => trim((string) $data['nama_rekening']),
                    'pagu_murni' => $total,
                    'pagu_perubahan' => $total,
                    'kategori_belanja' => (string) $data['kategori_belanja'],
                    'is_leaf' => true,
                ]);

                foreach ($alokasi as $bulan => $nilai) {
                    AnggaranKasBulanan::create([
                        'anggaran_rekening_id' => $rekening->id,
                        'bulan' => $bulan,
                        'alokasi_pagu' => $nilai,
                        'terkunci_bulan' => 0.00,
                        'realisasi_bulan' => 0.00,
                    ]);
                }

                AnggaranAuditLog::create([
                    'anggaran_rekening_id' => $rekening->id,
                    'unit_kerja_id' => $rekening->unit_kerja_id,
                    'user_id' => $actor?->id,
                    'actor_role' => $actor?->role,
                    'action' => 'CREATED',
                    'old_pagu_perubahan' => 0.00,
                    'new_pagu_perubahan' => $total,
                    'old_alokasi' => null,
                    'new_alokasi' => $alokasi,
                    'ip_address' => $ipAddress,
                ]);

                return $rekening->load('kasBulanan');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'kode_rekening' => ["Kode rekening {$kodeRekening} sudah terdaftar pada subkegiatan {$kodeSubkegiatan}."],
            ]);
        }
    }

    /**
     * Memperbarui alokasi kas 12 bulan dengan proteksi terhadap saldo terkunci + realisasi,
     * serta memperbarui pagu_perubahan tanpa mengubah pagu_murni awal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAllocations(int $rekeningId, array $data, ?User $actor = null, ?string $ipAddress = null): AnggaranRekening
    {
        $alokasi = $this->normalizeAllocations($data['alokasi'] ?? []);
        $newTotal = round(array_sum($alokasi), 2);

        return DB::transaction(function () use ($rekeningId, $alokasi, $newTotal, $actor, $ipAddress) {
            $rekening = AnggaranRekening::where('id', $rekeningId)
                ->lockForUpdate()
                ->firstOrFail();

            $kasRows = AnggaranKasBulanan::where('anggaran_rekening_id', $rekening->id)
                ->orderBy('bulan', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('bulan');

            $oldAlokasi = [];
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $existing = $kasRows->get($bulan);
                $oldAlokasi[$bulan] = $existing ? (float) $existing->alokasi_pagu : 0.0;
            }

            $cumAlokasiCents = 0;
            $cumUsedCents = 0;

            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $cumAlokasiCents += (int) round($alokasi[$bulan] * 100);
                $existing = $kasRows->get($bulan);

                if ($existing) {
                    $cumUsedCents += (int) round((float) $existing->terkunci_bulan * 100)
                        + (int) round((float) $existing->realisasi_bulan * 100);
                }

                if ($cumAlokasiCents < $cumUsedCents) {
                    throw ValidationException::withMessages([
                        'alokasi' => [
                            sprintf(
                                'Akumulasi alokasi s.d. bulan %d (Rp %s) tidak boleh kurang dari akumulasi dana terkunci + realisasi (minimum Rp %s).',
                                $bulan,
                                number_format($cumAlokasiCents / 100, 0, ',', '.'),
                                number_format($cumUsedCents / 100, 0, ',', '.')
                            ),
                        ],
                    ]);
                }
            }

            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $existing = $kasRows->get($bulan);
                if ($existing) {
                    $existing->update(['alokasi_pagu' => $alokasi[$bulan]]);
                } else {
                    AnggaranKasBulanan::create([
                        'anggaran_rekening_id' => $rekening->id,
                        'bulan' => $bulan,
                        'alokasi_pagu' => $alokasi[$bulan],
                        'terkunci_bulan' => 0.00,
                        'realisasi_bulan' => 0.00,
                    ]);
                }
            }

            $oldPaguPerubahan = (float) $rekening->pagu_perubahan;
            $rekening->update(['pagu_perubahan' => $newTotal]);

            AnggaranAuditLog::create([
                'anggaran_rekening_id' => $rekening->id,
                'unit_kerja_id' => $rekening->unit_kerja_id,
                'user_id' => $actor?->id,
                'actor_role' => $actor?->role,
                'action' => 'ALLOCATIONS_UPDATED',
                'old_pagu_perubahan' => $oldPaguPerubahan,
                'new_pagu_perubahan' => $newTotal,
                'old_alokasi' => $oldAlokasi,
                'new_alokasi' => $alokasi,
                'ip_address' => $ipAddress,
            ]);

            Log::info('BUDGET_ALLOCATION_UPDATED', [
                'actor_id' => $actor?->id,
                'actor_username' => $actor?->username,
                'actor_role' => $actor?->role,
                'rekening_id' => $rekening->id,
                'kode_rekening' => $rekening->kode_rekening,
                'old_pagu_perubahan' => $oldPaguPerubahan,
                'new_pagu_perubahan' => $newTotal,
            ]);

            return $rekening->load(['unitKerja', 'kasBulanan' => fn ($q) => $q->orderBy('bulan', 'asc')]);
        });
    }

    private function parseMonthlyAmount(mixed $value, int $bulan): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'alokasi' => ["Nominal alokasi bulan {$bulan} harus berupa angka."],
            ]);
        }

        $rounded = round((float) $value, 2);
        if ($rounded < 0.0) {
            throw ValidationException::withMessages([
                'alokasi' => ["Nominal alokasi bulan {$bulan} tidak boleh bernilai negatif."],
            ]);
        }

        return $rounded;
    }

    private function resolveSubkegiatanName(int $unitKerjaId, string $kodeSubkegiatan, string $fallback): string
    {
        $sameUnit = AnggaranRekening::where('unit_kerja_id', $unitKerjaId)
            ->where('kode_sub_kegiatan', $kodeSubkegiatan)
            ->whereNotNull('nama_sub_kegiatan')
            ->where('nama_sub_kegiatan', '!=', '')
            ->value('nama_sub_kegiatan');

        if (is_string($sameUnit) && $sameUnit !== '') {
            return $sameUnit;
        }

        $otherUnit = AnggaranRekening::where('kode_sub_kegiatan', $kodeSubkegiatan)
            ->whereNotNull('nama_sub_kegiatan')
            ->where('nama_sub_kegiatan', '!=', '')
            ->value('nama_sub_kegiatan');

        return (is_string($otherUnit) && $otherUnit !== '') ? $otherUnit : $fallback;
    }
}
