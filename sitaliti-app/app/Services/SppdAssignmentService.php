<?php

namespace App\Services;

use App\Models\SppdAssignment;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SppdAssignmentService
{
    public function __construct(
        protected SppdScheduleService $scheduleService
    ) {}

    /**
     * Mengambil daftar penugasan SPPD terpaginasi sesuai scoping unit kerja pengguna.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SppdAssignment::query()
            ->with(['unitKerja:id,kode_unit,nama_unit,tipe', 'kuitansi:id,nomor_kuitansi,status', 'peserta.pegawai:id,nama,nip,status_kepegawaian'])
            ->withCount('peserta');

        if (! $user->isSkpdWide()) {
            $query->where('unit_kerja_id', $user->unit_kerja_id);
        } elseif (! empty($filters['unit_kerja_id'])) {
            $query->where('unit_kerja_id', (int) $filters['unit_kerja_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($search, $like) {
                $q->where('nomor_surat_tugas', $like, "%{$search}%")
                    ->orWhere('maksud_perjalanan', $like, "%{$search}%")
                    ->orWhere('tempat_tujuan', $like, "%{$search}%")
                    ->orWhereHas('peserta.pegawai', function (Builder $pq) use ($search, $like) {
                        $pq->where('nama', $like, "%{$search}%");
                    });
            });
        }

        return $query->orderByDesc('tanggal_berangkat')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Membuat penugasan SPPD baru (pra-berangkat) beserta peserta dan rincian biaya opsional.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): SppdAssignment
    {
        $unitKerjaId = $user->isSkpdWide() && ! empty($data['unit_kerja_id'])
            ? (int) $data['unit_kerja_id']
            : (int) $user->unit_kerja_id;

        $unitKerja = UnitKerja::findOrFail($unitKerjaId);
        $participantsPayload = $this->buildSchedulePayload($data['peserta'], $data);

        return DB::transaction(function () use ($user, $unitKerja, $data, $participantsPayload): SppdAssignment {
            $this->scheduleService->assertNoConflictsWithLock($participantsPayload);

            $assignment = SppdAssignment::create([
                'unit_kerja_id' => $unitKerja->id,
                'user_id' => $user->id,
                'anggaran_rekening_id' => $data['anggaran_rekening_id'] ?? null,
                'nomor_surat_tugas' => $data['nomor_surat_tugas'] ?? null,
                'maksud_perjalanan' => trim((string) $data['maksud_perjalanan']),
                'alat_angkut' => $data['alat_angkut'] ?? null,
                'tempat_berangkat' => trim((string) $data['tempat_berangkat']),
                'tempat_tujuan' => trim((string) $data['tempat_tujuan']),
                'tanggal_berangkat' => $data['tanggal_berangkat'],
                'tanggal_kembali' => $data['tanggal_kembali'],
                'pemberi_perintah_nama' => $data['pemberi_perintah_nama'] ?? ($unitKerja->pejabat_nama ?: '-'),
                'pemberi_perintah_nip' => array_key_exists('pemberi_perintah_nip', $data)
                    ? $data['pemberi_perintah_nip']
                    : $unitKerja->pejabat_nip,
                'pemberi_perintah_jabatan' => $data['pemberi_perintah_jabatan']
                    ?? ($unitKerja->pejabat_jabatan ?: ($unitKerja->tipe === 'KELURAHAN' ? 'Lurah' : 'Camat Tarogong Kidul')),
                'dikeluarkan_di' => $data['dikeluarkan_di'] ?? 'Garut',
                'tanggal_penetapan' => $data['tanggal_penetapan'] ?? $data['tanggal_berangkat'],
                'laporan_hasil' => $data['laporan_hasil'] ?? null,
                'status' => $data['status'] ?? 'DRAFT',
            ]);

            $this->syncPeserta($assignment, $data['peserta']);

            return $assignment->load(['unitKerja', 'rekening', 'kuitansi', 'peserta.pegawai', 'peserta.biayaItems']);
        });
    }

    /**
     * Memperbarui penugasan SPPD beserta peserta & rincian biayanya.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SppdAssignment $assignment, array $data): SppdAssignment
    {
        $assignment->loadMissing('kuitansi');

        if ($assignment->kuitansi && ! in_array($assignment->kuitansi->status, ['DRAFT', 'REVISI_MAYOR'], true)) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ["Penugasan SPPD terkunci karena Kuitansi {$assignment->kuitansi->nomor_kuitansi} sudah berstatus {$assignment->kuitansi->status}."],
            ]);
        }

        $participantsPayload = $this->buildSchedulePayload($data['peserta'], $data);

        return DB::transaction(function () use ($assignment, $data, $participantsPayload): SppdAssignment {
            $this->scheduleService->assertNoConflictsWithLock(
                $participantsPayload,
                $assignment->kuitansi_id,
                $assignment->id
            );

            $assignment->update([
                'anggaran_rekening_id' => array_key_exists('anggaran_rekening_id', $data)
                    ? $data['anggaran_rekening_id']
                    : $assignment->anggaran_rekening_id,
                'nomor_surat_tugas' => array_key_exists('nomor_surat_tugas', $data)
                    ? $data['nomor_surat_tugas']
                    : $assignment->nomor_surat_tugas,
                'maksud_perjalanan' => trim((string) $data['maksud_perjalanan']),
                'alat_angkut' => array_key_exists('alat_angkut', $data) ? $data['alat_angkut'] : $assignment->alat_angkut,
                'tempat_berangkat' => trim((string) $data['tempat_berangkat']),
                'tempat_tujuan' => trim((string) $data['tempat_tujuan']),
                'tanggal_berangkat' => $data['tanggal_berangkat'],
                'tanggal_kembali' => $data['tanggal_kembali'],
                'pemberi_perintah_nama' => $data['pemberi_perintah_nama'] ?? $assignment->pemberi_perintah_nama,
                'pemberi_perintah_nip' => array_key_exists('pemberi_perintah_nip', $data)
                    ? $data['pemberi_perintah_nip']
                    : $assignment->pemberi_perintah_nip,
                'pemberi_perintah_jabatan' => $data['pemberi_perintah_jabatan'] ?? $assignment->pemberi_perintah_jabatan,
                'dikeluarkan_di' => $data['dikeluarkan_di'] ?? $assignment->dikeluarkan_di,
                'tanggal_penetapan' => $data['tanggal_penetapan'] ?? $assignment->tanggal_penetapan,
                'laporan_hasil' => array_key_exists('laporan_hasil', $data) ? $data['laporan_hasil'] : $assignment->laporan_hasil,
                'status' => $data['status'] ?? $assignment->status,
            ]);

            $this->syncPeserta($assignment, $data['peserta']);

            return $assignment->fresh(['unitKerja', 'rekening', 'kuitansi', 'peserta.pegawai', 'peserta.biayaItems']);
        });
    }

    /**
     * Menghapus penugasan SPPD yang belum tertaut ke Kuitansi SPJ.
     */
    public function delete(SppdAssignment $assignment): void
    {
        if ($assignment->kuitansi_id !== null) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Penugasan SPPD yang sudah terhubung dengan Kuitansi SPJ tidak dapat dihapus.'],
            ]);
        }

        $assignment->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $peserta
     * @param  array<string, mixed>  $assignmentData
     * @return array<int, array<string, mixed>>
     */
    public function buildSchedulePayload(array $peserta, array $assignmentData): array
    {
        return array_map(fn (array $row) => [
            'pegawai_id' => (int) $row['pegawai_id'],
            'tanggal_berangkat' => $assignmentData['tanggal_berangkat'],
            'tanggal_pulang' => $assignmentData['tanggal_kembali'],
        ], $peserta);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pesertaList
     */
    protected function syncPeserta(SppdAssignment $assignment, array $pesertaList): void
    {
        $assignment->peserta()->delete();

        foreach (array_values($pesertaList) as $idx => $row) {
            $peserta = $assignment->peserta()->create([
                'pegawai_id' => (int) $row['pegawai_id'],
                'urutan' => $row['urutan'] ?? ($idx + 1),
                'nomor_spd' => $row['nomor_spd'] ?? null,
                'tingkat_biaya' => $row['tingkat_biaya'] ?? null,
                'pengikut' => $row['pengikut'] ?? null,
                'dibayar_semula' => $row['dibayar_semula'] ?? 0,
                'keterangan_lain' => $row['keterangan_lain'] ?? null,
            ]);

            if (! empty($row['biaya_items']) && is_array($row['biaya_items'])) {
                foreach ($row['biaya_items'] as $biaya) {
                    $peserta->biayaItems()->create([
                        'uraian_biaya' => trim((string) $biaya['uraian_biaya']),
                        'nominal' => (float) ($biaya['nominal'] ?? 0),
                        'keterangan' => $biaya['keterangan'] ?? null,
                    ]);
                }
            }
        }
    }
}
