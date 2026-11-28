<?php

namespace App\Services;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
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
        $this->assertValidAssignmentRekening($unitKerja, $data['anggaran_rekening_id'] ?? null);
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
        $assignment->loadMissing(['kuitansi', 'unitKerja']);

        if ($assignment->kuitansi && ! in_array($assignment->kuitansi->status, ['DRAFT', 'REVISI_MAYOR'], true)) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ["Penugasan SPPD terkunci karena Kuitansi {$assignment->kuitansi->nomor_kuitansi} sudah berstatus {$assignment->kuitansi->status}."],
            ]);
        }

        if (array_key_exists('anggaran_rekening_id', $data)) {
            $this->assertValidAssignmentRekening($assignment->unitKerja, $data['anggaran_rekening_id']);
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
     * Mengonversi penugasan SPPD menjadi Kuitansi SPJ baru (kategori B.4) secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function convertToKuitansi(User $user, SppdAssignment $assignment, array $data): SppdAssignment
    {
        if ($assignment->kuitansi_id !== null) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Penugasan SPPD ini sudah terhubung dengan Kuitansi SPJ.'],
            ]);
        }

        $assignment->loadMissing(['unitKerja', 'peserta.pegawai', 'peserta.biayaItems']);

        $rekening = AnggaranRekening::with('unitKerja')->findOrFail((int) $data['anggaran_rekening_id']);
        if (! $assignment->unitKerja || ! $this->canAssignmentUseRekening($assignment->unitKerja, $rekening)) {
            throw ValidationException::withMessages([
                'anggaran_rekening_id' => ['Rekening belanja harus berasal dari unit kerja yang sama atau rekening SPPD Kecamatan untuk unit Kelurahan.'],
            ]);
        }

        if (! $this->scheduleService->isSppdRekening($rekening)) {
            throw ValidationException::withMessages([
                'anggaran_rekening_id' => ['Rekening yang dipilih bukan kategori Perjalanan Dinas (B.4 / 5.1.02.04).'],
            ]);
        }

        $items = [];
        $totalBiaya = 0.0;
        foreach ($assignment->peserta as $peserta) {
            $nama = $peserta->pegawai?->nama ?? "Peserta #{$peserta->pegawai_id}";
            foreach ($peserta->biayaItems as $biaya) {
                $nominal = (float) $biaya->nominal;
                if ($nominal <= 0) {
                    continue;
                }
                $totalBiaya += $nominal;
                $items[] = [
                    'nama_barang' => "{$nama} - {$biaya->uraian_biaya}",
                    'volume' => 1,
                    'satuan' => 'Kegiatan',
                    'harga_satuan' => $nominal,
                ];
            }
        }

        if ($items === [] || $totalBiaya <= 0) {
            throw ValidationException::withMessages([
                'peserta' => ['Rincian biaya perjalanan dinas harus diisi lebih dari Rp 0 sebelum dikonversi ke Kuitansi SPJ.'],
            ]);
        }

        $pesertaCount = $assignment->peserta->count();
        $firstNama = $assignment->peserta->first()?->pegawai?->nama ?? 'Pelaksana SPPD';
        $namaPenerima = $pesertaCount > 1
            ? "{$firstNama} (Rombongan {$pesertaCount} Org)"
            : $firstNama;

        $sppdPesertaPayload = $assignment->peserta->map(fn ($p) => [
            'pegawai_id' => $p->pegawai_id,
            'tanggal_berangkat' => $assignment->tanggal_berangkat->toDateString(),
            'tanggal_pulang' => $assignment->tanggal_kembali->toDateString(),
            'tujuan' => $assignment->tempat_tujuan,
        ])->values()->all();

        $golonganAsn = $this->resolveGolonganAsn($assignment);

        return DB::transaction(function () use ($user, $assignment, $rekening, $data, $items, $namaPenerima, $sppdPesertaPayload, $golonganAsn): SppdAssignment {
            $receiptService = app(ReceiptService::class);
            $kuitansi = $receiptService->createDraft([
                'unit_kerja_id' => $assignment->unit_kerja_id,
                'anggaran_rekening_id' => $rekening->id,
                'bulan_anggaran' => (int) $data['bulan_anggaran'],
                'tanggal_transaksi' => $data['tanggal_transaksi'] ?? $assignment->tanggal_kembali->toDateString(),
                'uraian' => $data['uraian'] ?? $assignment->maksud_perjalanan,
                'nama_penerima' => $namaPenerima,
                'metode_pembayaran' => $data['metode_pembayaran'] ?? 'NON_TUNAI_IBC',
                'golongan_asn' => $golonganAsn,
                'items' => $items,
                'sppd_peserta' => $sppdPesertaPayload,
                'exclude_sppd_assignment_id' => $assignment->id,
            ], [], $user);

            $assignment->update([
                'kuitansi_id' => $kuitansi->id,
                'anggaran_rekening_id' => $rekening->id,
                'status' => 'SELESAI',
            ]);

            return $assignment->fresh(['unitKerja', 'rekening', 'kuitansi.rekening', 'peserta.pegawai', 'peserta.biayaItems']);
        });
    }

    /**
     * Menautkan penugasan SPPD ke Kuitansi SPJ existing dengan guard integritas lengkap.
     */
    public function linkExistingKuitansi(SppdAssignment $assignment, int $kuitansiId): SppdAssignment
    {
        if ($assignment->kuitansi_id !== null) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Penugasan SPPD ini sudah terhubung dengan Kuitansi SPJ.'],
            ]);
        }

        $kuitansi = Kuitansi::with('rekening')->findOrFail($kuitansiId);

        if ((int) $kuitansi->unit_kerja_id !== (int) $assignment->unit_kerja_id) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Kuitansi harus berasal dari unit kerja yang sama dengan penugasan SPPD.'],
            ]);
        }

        if (! $this->scheduleService->isSppdRekening($kuitansi->rekening)) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Kuitansi yang ditautkan harus menggunakan rekening Perjalanan Dinas (B.4 / 5.1.02.04).'],
            ]);
        }

        if (! in_array($kuitansi->status, ['DRAFT', 'REVISI_MAYOR'], true)) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ["Hanya Kuitansi berstatus DRAFT atau REVISI_MAYOR yang dapat ditautkan (status saat ini: {$kuitansi->status})."],
            ]);
        }

        if (SppdAssignment::where('kuitansi_id', $kuitansi->id)->exists()) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Kuitansi ini sudah tertaut ke penugasan SPPD lain.'],
            ]);
        }

        $assignment->loadMissing(['peserta.biayaItems']);
        $totalBiaya = (float) $assignment->peserta->sum(fn ($p) => $p->totalBiaya());
        if ($totalBiaya > 0 && abs($totalBiaya - (float) $kuitansi->nominal_bruto) >= 0.01) {
            throw ValidationException::withMessages([
                'kuitansi_id' => ['Total rincian biaya peserta SPPD harus sama persis dengan nominal bruto Kuitansi.'],
            ]);
        }

        $sppdPesertaPayload = $assignment->peserta->map(fn ($p) => [
            'pegawai_id' => $p->pegawai_id,
            'tanggal_berangkat' => $assignment->tanggal_berangkat->toDateString(),
            'tanggal_pulang' => $assignment->tanggal_kembali->toDateString(),
            'tujuan' => $assignment->tempat_tujuan,
        ])->values()->all();

        return DB::transaction(function () use ($assignment, $kuitansi, $sppdPesertaPayload): SppdAssignment {
            $this->scheduleService->syncParticipantsAndPhotos(
                $kuitansi,
                $sppdPesertaPayload,
                null,
                false,
                $assignment->id
            );

            $assignment->update([
                'kuitansi_id' => $kuitansi->id,
                'anggaran_rekening_id' => $kuitansi->anggaran_rekening_id,
                'status' => 'SELESAI',
            ]);

            return $assignment->fresh(['unitKerja', 'rekening', 'kuitansi.rekening', 'peserta.pegawai', 'peserta.biayaItems']);
        });
    }

    private function resolveGolonganAsn(SppdAssignment $assignment): string
    {
        $peserta = $assignment->peserta;
        if ($peserta->isEmpty()) {
            return 'GOLONGAN_III';
        }

        $allNonAsn = $peserta->every(fn ($p) => ($p->pegawai?->status_kepegawaian ?? 'ASN') === 'NON_ASN');
        if ($allNonAsn) {
            return 'NON_ASN';
        }

        $gol = strtoupper((string) ($peserta->first()?->pegawai?->golongan ?? ''));
        if (str_contains($gol, 'IV/')) {
            return 'GOLONGAN_IV';
        }
        if (str_contains($gol, 'III/')) {
            return 'GOLONGAN_III';
        }
        if (str_contains($gol, 'II/')) {
            return 'GOLONGAN_II';
        }
        if (str_contains($gol, 'I/')) {
            return 'GOLONGAN_I';
        }

        return 'GOLONGAN_III';
    }

    /**
     * Memeriksa apakah penugasan SPPD suatu unit kerja berhak memakai rekening tertentu.
     */
    public function canAssignmentUseRekening(UnitKerja $assignmentUnit, AnggaranRekening $rekening): bool
    {
        if ((int) $rekening->unit_kerja_id === (int) $assignmentUnit->id) {
            return true;
        }

        if ($assignmentUnit->tipe === 'KELURAHAN' && $this->scheduleService->isSppdRekening($rekening)) {
            $rekening->loadMissing('unitKerja');

            return $rekening->unitKerja?->tipe === 'KECAMATAN';
        }

        return false;
    }

    private function assertValidAssignmentRekening(?UnitKerja $assignmentUnit, mixed $rekeningId): void
    {
        if ($rekeningId === null || $rekeningId === '') {
            return;
        }

        $rekening = AnggaranRekening::with('unitKerja')->findOrFail((int) $rekeningId);
        if (! $this->scheduleService->isSppdRekening($rekening)) {
            throw ValidationException::withMessages([
                'anggaran_rekening_id' => ['Mata anggaran penugasan SPPD harus menggunakan rekening Perjalanan Dinas (B.4 / 5.1.02.04).'],
            ]);
        }

        if (! $assignmentUnit || ! $this->canAssignmentUseRekening($assignmentUnit, $rekening)) {
            throw ValidationException::withMessages([
                'anggaran_rekening_id' => ['Rekening belanja tidak tersedia untuk unit kerja penugasan SPPD ini.'],
            ]);
        }
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
