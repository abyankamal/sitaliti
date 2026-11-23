<?php

namespace App\Services;

use App\Models\AnggaranRekening;
use App\Models\MasterRekapBelanjaOperasi;
use App\Models\RekapBelanjaOperasiRekening;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterRekapBelanjaOperasiService
{
    /**
     * Mengambil daftar pos Master Rekap Belanja Operasi beserta mapping kode rekeningnya.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MasterRekapBelanjaOperasi>
     */
    public function list(array $filters = []): Collection
    {
        $query = MasterRekapBelanjaOperasi::query()
            ->with(['rekeningMappings' => fn ($q) => $q->orderBy('kode_rekening')])
            ->withCount('rekeningMappings');

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('kode', 'like', $term)
                    ->orWhere('nama', 'like', $term)
                    ->orWhere('deskripsi', 'like', $term);
            });
        }

        return $query->orderBy('urutan')->orderBy('nama')->get();
    }

    /**
     * Mengambil daftar unik kode rekening Belanja Operasi (5.1.% leaf) beserta status pemetaannya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRekeningOptions(?string $search = null, ?int $excludeMasterRekapId = null): array
    {
        $query = AnggaranRekening::query()
            ->where('is_leaf', true)
            ->where('kode_rekening', 'like', '5.1.%');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('kode_rekening', 'like', $term)
                    ->orWhere('nama_rekening', 'like', $term);
            });
        }

        $rekeningRows = $query
            ->selectRaw('kode_rekening, MAX(nama_rekening) as nama_rekening, COUNT(DISTINCT unit_kerja_id) as jumlah_unit, COALESCE(SUM(pagu_perubahan), 0) as total_pagu')
            ->groupBy('kode_rekening')
            ->orderBy('kode_rekening')
            ->get();

        $mappings = RekapBelanjaOperasiRekening::query()
            ->with('masterRekap:id,kode,nama')
            ->get()
            ->keyBy('kode_rekening');

        return $rekeningRows->map(function ($row) use ($mappings, $excludeMasterRekapId): array {
            $mapping = $mappings->get($row->kode_rekening);
            $mappedPos = $mapping?->masterRekap;
            $isCurrent = $mappedPos && $excludeMasterRekapId !== null && $mappedPos->id === $excludeMasterRekapId;

            return [
                'kode_rekening' => $row->kode_rekening,
                'nama_rekening' => $row->nama_rekening,
                'jumlah_unit' => (int) $row->jumlah_unit,
                'total_pagu' => (float) $row->total_pagu,
                'is_mapped' => $mapping !== null,
                'is_current_pos' => $isCurrent,
                'mapped_to' => $mappedPos ? [
                    'id' => $mappedPos->id,
                    'kode' => $mappedPos->kode,
                    'nama' => $mappedPos->nama,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * Membuat pos Master Rekap Belanja Operasi baru beserta mapping kode rekeningnya.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MasterRekapBelanjaOperasi
    {
        return DB::transaction(function () use ($data): MasterRekapBelanjaOperasi {
            $pos = MasterRekapBelanjaOperasi::create([
                'kode' => trim((string) $data['kode']),
                'nama' => trim((string) $data['nama']),
                'deskripsi' => isset($data['deskripsi']) ? trim((string) $data['deskripsi']) : null,
                'urutan' => (int) ($data['urutan'] ?? 0),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            if (array_key_exists('kode_rekening', $data)) {
                $this->syncRekeningMappings($pos, (array) $data['kode_rekening']);
            }

            return $pos->load(['rekeningMappings' => fn ($q) => $q->orderBy('kode_rekening')])
                ->loadCount('rekeningMappings');
        });
    }

    /**
     * Memperbarui pos Master Rekap Belanja Operasi dan menyinkronkan mapping kode rekeningnya.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MasterRekapBelanjaOperasi $pos, array $data): MasterRekapBelanjaOperasi
    {
        return DB::transaction(function () use ($pos, $data): MasterRekapBelanjaOperasi {
            $pos->update([
                'kode' => trim((string) $data['kode']),
                'nama' => trim((string) $data['nama']),
                'deskripsi' => array_key_exists('deskripsi', $data)
                    ? ($data['deskripsi'] !== null ? trim((string) $data['deskripsi']) : null)
                    : $pos->deskripsi,
                'urutan' => array_key_exists('urutan', $data) ? (int) $data['urutan'] : $pos->urutan,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $pos->is_active,
            ]);

            if (array_key_exists('kode_rekening', $data)) {
                $this->syncRekeningMappings($pos, (array) $data['kode_rekening']);
            }

            return $pos->load(['rekeningMappings' => fn ($q) => $q->orderBy('kode_rekening')])
                ->loadCount('rekeningMappings');
        });
    }

    /**
     * Menghapus pos Master Rekap Belanja Operasi (mapping ikut terhapus cascade).
     */
    public function delete(MasterRekapBelanjaOperasi $pos): void
    {
        DB::transaction(function () use ($pos): void {
            $pos->delete();
        });
    }

    /**
     * Menyinkronkan mapping kode rekening 5.1.% secara eksklusif untuk satu pos rekap.
     *
     * @param  array<int, string>  $rawCodes
     */
    private function syncRekeningMappings(MasterRekapBelanjaOperasi $pos, array $rawCodes): void
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $rawCodes),
            static fn (string $code): bool => $code !== ''
        )));

        $invalidCodes = array_values(array_filter(
            $codes,
            static fn (string $code): bool => ! str_starts_with($code, '5.1.')
        ));

        if (! empty($invalidCodes)) {
            throw ValidationException::withMessages([
                'kode_rekening' => [
                    'Hanya kode rekening Belanja Operasi (awalan 5.1.) yang dapat dipetakan. Kode tidak valid: '.implode(', ', $invalidCodes),
                ],
            ]);
        }

        if (! empty($codes)) {
            $conflicts = RekapBelanjaOperasiRekening::query()
                ->with('masterRekap:id,kode,nama')
                ->whereIn('kode_rekening', $codes)
                ->where('master_rekap_id', '!=', $pos->id)
                ->lockForUpdate()
                ->get();

            if ($conflicts->isNotEmpty()) {
                $messages = $conflicts->map(static function (RekapBelanjaOperasiRekening $item): string {
                    $owner = $item->masterRekap ? "{$item->masterRekap->kode} - {$item->masterRekap->nama}" : 'pos lain';

                    return "{$item->kode_rekening} (sudah dipetakan ke {$owner})";
                })->all();

                throw ValidationException::withMessages([
                    'kode_rekening' => [
                        'Satu kode rekening hanya boleh masuk ke 1 pos rekap: '.implode('; ', $messages),
                    ],
                ]);
            }
        }

        RekapBelanjaOperasiRekening::query()
            ->where('master_rekap_id', $pos->id)
            ->delete();

        if (! empty($codes)) {
            $now = now();
            $rows = array_map(static fn (string $code): array => [
                'master_rekap_id' => $pos->id,
                'kode_rekening' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ], $codes);

            RekapBelanjaOperasiRekening::query()->insert($rows);
        }
    }
}
