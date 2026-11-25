<?php

namespace App\Services;

use App\Models\MasterSbmItem;
use App\Models\PengaturanKepatuhan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SbmValidationService
{
    private static bool $hasTable = false;

    /**
     * Memeriksa apakah harga satuan item melebihi batas Standar Biaya Masukan (SBM/SSH).
     * Bersifat non-blocking (soft-warning).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function checkItems(string $kategoriBelanja, array $items, ?int $tahun = null): array
    {
        if (empty($items) || ! PengaturanKepatuhan::isEnabled('sbm_soft_warning_enabled', false)) {
            return [];
        }

        if (! self::$hasTable) {
            if (! Schema::hasTable('master_sbm_item')) {
                return [];
            }
            self::$hasTable = true;
        }

        $tahun = $tahun ?? (int) date('Y');
        $normalizedCategory = $this->normalizeCategory($kategoriBelanja);

        $sbmItems = MasterSbmItem::activeForYear($tahun)
            ->where('kategori_belanja', $normalizedCategory)
            ->get();

        if ($sbmItems->isEmpty()) {
            return [];
        }

        $byId = $sbmItems->keyBy('id');
        $warnings = [];

        foreach ($items as $item) {
            $hargaSatuan = (float) ($item['harga_satuan'] ?? 0);
            if ($hargaSatuan <= 0) {
                continue;
            }

            $namaItem = trim((string) ($item['nama_barang'] ?? $item['nama_item'] ?? $item['uraian'] ?? ''));
            $matched = null;

            if (! empty($item['sbm_item_id']) && $byId->has((int) $item['sbm_item_id'])) {
                $matched = $byId->get((int) $item['sbm_item_id']);
            } elseif ($namaItem !== '') {
                $needle = Str::lower($namaItem);
                $matched = $sbmItems->first(function (MasterSbmItem $sbm) use ($needle) {
                    $standar = Str::lower($sbm->uraian_standar);

                    return str_contains($standar, $needle) || str_contains($needle, $standar);
                });
            }

            if ($matched && $hargaSatuan > (float) $matched->harga_maksimal) {
                $displayName = $namaItem !== '' ? $namaItem : $matched->uraian_standar;
                $maksimal = (float) $matched->harga_maksimal;

                $warnings[] = [
                    'nama_item' => $displayName,
                    'kode_sbm' => $matched->kode_sbm,
                    'uraian_standar' => $matched->uraian_standar,
                    'satuan' => $matched->satuan,
                    'harga_input' => $hargaSatuan,
                    'harga_maksimal' => $maksimal,
                    'selisih' => round($hargaSatuan - $maksimal, 2),
                    'message' => "Harga satuan {$displayName} (Rp ".number_format($hargaSatuan, 0, ',', '.').") melebihi batas SBM {$matched->uraian_standar} (maks. Rp ".number_format($maksimal, 0, ',', '.')."/{$matched->satuan}).",
                ];
            }
        }

        return $warnings;
    }

    private function normalizeCategory(string $kategori): string
    {
        return match ($kategori) {
            'B.1', 'B1_ATK' => 'B1_ATK',
            'B.2', 'B2_MAMIN' => 'B2_MAMIN',
            'B.3', 'B3_HONOR' => 'B3_HONOR',
            'B.4', 'B4_PERJADIN' => 'B4_PERJADIN',
            'B.5', 'B5_PEMELIHARAAN', 'B5_MODAL' => 'B5_PEMELIHARAAN',
            'B.6', 'B6_MODAL', 'B6_LAINNYA' => 'B6_MODAL',
            default => $kategori,
        };
    }
}
