<?php

namespace App\Services;

use App\Models\AnggaranRekening;
use App\Models\Kuitansi;
use App\Models\KuitansiSppdPeserta;
use App\Models\Pegawai;
use App\Models\SppdPeserta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class SppdScheduleService
{
    /**
     * Status kuitansi yang tidak memblokir jadwal pegawai.
     *
     * @var list<string>
     */
    public const NON_BLOCKING_STATUSES = ['DITOLAK', 'REVISI_MAYOR'];

    /**
     * Menentukan apakah rekening anggaran termasuk kategori Perjalanan Dinas (SPPD / B.4).
     */
    public function isSppdRekening(?AnggaranRekening $rekening): bool
    {
        if (! $rekening) {
            return false;
        }

        $kategori = strtoupper(trim((string) $rekening->kategori_belanja));
        if (in_array($kategori, ['B.4', 'B4', 'B4_PERJADIN'], true)) {
            return true;
        }

        return str_starts_with(trim((string) $rekening->kode_rekening), '5.1.02.04');
    }

    /**
     * Mencari potensi bentrok jadwal (overlap tanggal atau duplikat peserta) lintas Sub Kegiatan & Unit Kerja.
     *
     * @param  array<int, array<string, mixed>>  $participants
     * @return array<int, array<string, mixed>>
     */
    public function findConflicts(
        array $participants,
        ?int $excludeKuitansiId = null,
        ?int $excludeSppdAssignmentId = null
    ): array {
        if (empty($participants)) {
            return [];
        }

        $conflicts = [];
        $seenPegawaiIds = [];
        $pegawaiIds = [];

        foreach ($participants as $index => $row) {
            $pegawaiId = (int) ($row['pegawai_id'] ?? 0);
            if ($pegawaiId <= 0) {
                continue;
            }

            if (isset($seenPegawaiIds[$pegawaiId])) {
                $conflicts[] = [
                    'index' => $index,
                    'pegawai_id' => $pegawaiId,
                    'type' => 'DUPLICATE_IN_RECEIPT',
                    'message' => 'Pegawai yang sama tidak boleh dimasukkan lebih dari satu kali dalam satu kuitansi SPPD.',
                ];

                continue;
            }

            $seenPegawaiIds[$pegawaiId] = true;
            $pegawaiIds[] = $pegawaiId;
        }

        if (empty($pegawaiIds)) {
            return $conflicts;
        }

        $pegawaiMap = Pegawai::whereIn('id', $pegawaiIds)
            ->get(['id', 'nip', 'nama'])
            ->keyBy('id');

        foreach ($participants as $index => $row) {
            $pegawaiId = (int) ($row['pegawai_id'] ?? 0);
            $start = (string) ($row['tanggal_berangkat'] ?? '');
            $end = (string) ($row['tanggal_pulang'] ?? $start);

            if ($pegawaiId <= 0 || $start === '' || $end === '') {
                continue;
            }

            $overlappingReceipts = KuitansiSppdPeserta::query()
                ->with(['kuitansi.rekening', 'kuitansi.unitKerja'])
                ->where('pegawai_id', $pegawaiId)
                ->whereDate('tanggal_berangkat', '<=', $end)
                ->whereDate('tanggal_pulang', '>=', $start)
                ->whereHas('kuitansi', function ($q) use ($excludeKuitansiId) {
                    $q->whereNotIn('status', self::NON_BLOCKING_STATUSES);
                    if ($excludeKuitansiId !== null) {
                        $q->where('id', '!=', $excludeKuitansiId);
                    }
                })
                ->get();

            $pegawai = $pegawaiMap->get($pegawaiId);
            $nama = $pegawai?->nama ?? "Pegawai #{$pegawaiId}";
            $nipLabel = $pegawai?->nip ? "NIP {$pegawai->nip}" : 'Non-ASN';

            foreach ($overlappingReceipts as $overlapping) {
                if (! $overlapping->kuitansi) {
                    continue;
                }

                $kuitansiBentrok = $overlapping->kuitansi;
                $subKegiatan = $kuitansiBentrok->rekening?->nama_sub_kegiatan ?? '-';
                $tglMulai = $overlapping->tanggal_berangkat?->format('d/m/Y') ?? $start;
                $tglSelesai = $overlapping->tanggal_pulang?->format('d/m/Y') ?? $end;

                $conflicts[] = [
                    'index' => $index,
                    'pegawai_id' => $pegawaiId,
                    'pegawai_nama' => $nama,
                    'pegawai_nip' => $pegawai?->nip ?? '-',
                    'type' => 'SCHEDULE_OVERLAP',
                    'source' => 'KUITANSI',
                    'kuitansi_id' => $kuitansiBentrok->id,
                    'nomor_kuitansi' => $kuitansiBentrok->nomor_kuitansi,
                    'sub_kegiatan' => $subKegiatan,
                    'unit_kerja' => $kuitansiBentrok->unitKerja?->nama_unit ?? '-',
                    'tanggal_berangkat' => $overlapping->tanggal_berangkat?->toDateString(),
                    'tanggal_pulang' => $overlapping->tanggal_pulang?->toDateString(),
                    'message' => "{$nama} ({$nipLabel}) sudah terjadwal berangkat pada {$tglMulai} s/d {$tglSelesai} di Kuitansi {$kuitansiBentrok->nomor_kuitansi} ({$subKegiatan}).",
                ];
            }

            $overlappingAssignments = SppdPeserta::query()
                ->with(['assignment.unitKerja', 'assignment.rekening'])
                ->where('pegawai_id', $pegawaiId)
                ->whereHas('assignment', function ($q) use ($start, $end, $excludeSppdAssignmentId) {
                    $q->whereNull('kuitansi_id')
                        ->whereIn('status', ['DRAFT', 'TERBIT'])
                        ->whereDate('tanggal_berangkat', '<=', $end)
                        ->whereDate('tanggal_kembali', '>=', $start);

                    if ($excludeSppdAssignmentId !== null) {
                        $q->where('id', '!=', $excludeSppdAssignmentId);
                    }
                })
                ->get();

            foreach ($overlappingAssignments as $overlappingAssignment) {
                $assignmentBentrok = $overlappingAssignment->assignment;
                if (! $assignmentBentrok) {
                    continue;
                }

                $nomorSt = $assignmentBentrok->nomor_surat_tugas ?: "Penugasan #{$assignmentBentrok->id}";
                $tglMulai = $assignmentBentrok->tanggal_berangkat?->format('d/m/Y') ?? $start;
                $tglSelesai = $assignmentBentrok->tanggal_kembali?->format('d/m/Y') ?? $end;

                $conflicts[] = [
                    'index' => $index,
                    'pegawai_id' => $pegawaiId,
                    'pegawai_nama' => $nama,
                    'pegawai_nip' => $pegawai?->nip ?? '-',
                    'type' => 'SCHEDULE_OVERLAP',
                    'source' => 'SPPD_ASSIGNMENT',
                    'sppd_assignment_id' => $assignmentBentrok->id,
                    'nomor_surat_tugas' => $nomorSt,
                    'sub_kegiatan' => $assignmentBentrok->rekening?->nama_sub_kegiatan ?? '-',
                    'unit_kerja' => $assignmentBentrok->unitKerja?->nama_unit ?? '-',
                    'tanggal_berangkat' => $assignmentBentrok->tanggal_berangkat?->toDateString(),
                    'tanggal_pulang' => $assignmentBentrok->tanggal_kembali?->toDateString(),
                    'message' => "{$nama} ({$nipLabel}) sudah terjadwal berangkat pada {$tglMulai} s/d {$tglSelesai} di Penugasan SPPD {$nomorSt}.",
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Mengunci baris pegawai secara deterministik (ORDER BY id ASC) dan memvalidasi bebas bentrok jadwal.
     *
     * @param  array<int, array<string, mixed>>  $participants
     *
     * @throws ValidationException
     */
    public function assertNoConflictsWithLock(
        array $participants,
        ?int $excludeKuitansiId = null,
        ?int $excludeSppdAssignmentId = null
    ): void {
        $pegawaiIds = collect($participants)
            ->pluck('pegawai_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (! empty($pegawaiIds)) {
            Pegawai::whereIn('id', $pegawaiIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }

        $conflicts = $this->findConflicts($participants, $excludeKuitansiId, $excludeSppdAssignmentId);
        if (! empty($conflicts)) {
            $messages = array_values(array_unique(array_map(
                fn (array $conflict) => (string) $conflict['message'],
                $conflicts
            )));

            throw ValidationException::withMessages([
                'sppd_peserta' => $messages,
            ]);
        }
    }

    /**
     * Menyinkronkan daftar peserta SPPD dan foto keberangkatan di dalam transaksi DB secara aman.
     *
     * @param  array<int, array<string, mixed>>  $participants
     */
    public function syncParticipantsAndPhotos(
        Kuitansi $kuitansi,
        array $participants,
        ?UploadedFile $groupPhoto = null,
        bool $removeGroupPhoto = false
    ): void {
        $this->assertNoConflictsWithLock($participants, $kuitansi->id);

        $newlyStoredPaths = [];
        $pathsToDeleteAfterCommit = [];
        $tahun = date('Y');
        $bulan = str_pad((string) ($kuitansi->bulan_anggaran ?: date('n')), 2, '0', STR_PAD_LEFT);
        $baseDir = "sppd-photos/{$tahun}/{$bulan}";

        try {
            // 1. Kelola foto keberangkatan rombongan pada tabel kuitansi
            if ($groupPhoto instanceof UploadedFile) {
                $storedPath = $this->storePhotoFile($groupPhoto, $baseDir);
                $newlyStoredPaths[] = $storedPath;

                if ($kuitansi->sppd_foto_path) {
                    $pathsToDeleteAfterCommit[] = $kuitansi->sppd_foto_path;
                }

                $kuitansi->update([
                    'sppd_foto_path' => $storedPath,
                    'sppd_foto_name' => $groupPhoto->getClientOriginalName(),
                    'sppd_foto_size' => $groupPhoto->getSize() ?: 0,
                    'sppd_foto_mime' => $groupPhoto->getMimeType() ?: 'image/jpeg',
                ]);
            } elseif ($removeGroupPhoto && $kuitansi->sppd_foto_path) {
                $pathsToDeleteAfterCommit[] = $kuitansi->sppd_foto_path;
                $kuitansi->update([
                    'sppd_foto_path' => null,
                    'sppd_foto_name' => null,
                    'sppd_foto_size' => null,
                    'sppd_foto_mime' => null,
                ]);
            }

            // 2. Preservasi metadata foto eksisting per pegawai_id sebelum sinkronisasi
            $existingByPegawai = $kuitansi->sppdPeserta()->get()->keyBy('pegawai_id');
            $incomingPegawaiIds = collect($participants)
                ->pluck('pegawai_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($existingByPegawai as $pegawaiId => $existing) {
                if (! in_array((int) $pegawaiId, $incomingPegawaiIds, true) && $existing->foto_path) {
                    $pathsToDeleteAfterCommit[] = $existing->foto_path;
                }
            }

            $kuitansi->sppdPeserta()->delete();

            foreach ($participants as $row) {
                $pegawaiId = (int) ($row['pegawai_id'] ?? 0);
                if ($pegawaiId <= 0) {
                    continue;
                }

                $existing = $existingByPegawai->get($pegawaiId);
                $fotoPath = $existing?->foto_path;
                $fotoName = $existing?->foto_name;
                $fotoSize = $existing?->foto_size;
                $fotoMime = $existing?->foto_mime;

                $participantPhoto = $row['foto'] ?? null;
                $removeParticipantPhoto = filter_var($row['remove_foto'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($participantPhoto instanceof UploadedFile) {
                    $storedPath = $this->storePhotoFile($participantPhoto, $baseDir);
                    $newlyStoredPaths[] = $storedPath;

                    if ($fotoPath) {
                        $pathsToDeleteAfterCommit[] = $fotoPath;
                    }

                    $fotoPath = $storedPath;
                    $fotoName = $participantPhoto->getClientOriginalName();
                    $fotoSize = $participantPhoto->getSize() ?: 0;
                    $fotoMime = $participantPhoto->getMimeType() ?: 'image/jpeg';
                } elseif ($removeParticipantPhoto && $fotoPath) {
                    $pathsToDeleteAfterCommit[] = $fotoPath;
                    $fotoPath = null;
                    $fotoName = null;
                    $fotoSize = null;
                    $fotoMime = null;
                }

                KuitansiSppdPeserta::create([
                    'kuitansi_id' => $kuitansi->id,
                    'pegawai_id' => $pegawaiId,
                    'tanggal_berangkat' => $row['tanggal_berangkat'],
                    'tanggal_pulang' => $row['tanggal_pulang'] ?? $row['tanggal_berangkat'],
                    'tujuan' => isset($row['tujuan']) ? trim((string) $row['tujuan']) : null,
                    'foto_path' => $fotoPath,
                    'foto_name' => $fotoName,
                    'foto_size' => $fotoSize,
                    'foto_mime' => $fotoMime,
                ]);
            }

            if (! empty($pathsToDeleteAfterCommit)) {
                DB::afterCommit(function () use ($pathsToDeleteAfterCommit) {
                    foreach (array_unique($pathsToDeleteAfterCommit) as $path) {
                        if ($path && Storage::disk('local')->exists($path)) {
                            Storage::disk('local')->delete($path);
                        }
                    }
                });
            }
        } catch (Throwable $e) {
            foreach ($newlyStoredPaths as $path) {
                if ($path && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }

            throw $e;
        }
    }

    /**
     * Memvalidasi kesiapan kuitansi SPPD sebelum Submit ke PPTK atau Kirim Ulang Revisi Minor.
     *
     * @throws InvalidArgumentException|ValidationException
     */
    public function validateReadyForSubmission(Kuitansi $kuitansi): void
    {
        $kuitansi->loadMissing('rekening');
        if (! $this->isSppdRekening($kuitansi->rekening)) {
            return;
        }

        $peserta = $kuitansi->sppdPeserta()->get();
        if ($peserta->isEmpty()) {
            throw new InvalidArgumentException('Kuitansi Perjalanan Dinas (SPPD) wajib memiliki minimal 1 pelaksana/peserta sebelum diajukan.');
        }

        $participantsPayload = $peserta->map(fn (KuitansiSppdPeserta $item) => [
            'pegawai_id' => $item->pegawai_id,
            'tanggal_berangkat' => $item->tanggal_berangkat?->toDateString(),
            'tanggal_pulang' => $item->tanggal_pulang?->toDateString(),
        ])->all();

        $this->assertNoConflictsWithLock($participantsPayload, $kuitansi->id);

        $hasGroupPhoto = filled($kuitansi->sppd_foto_path);
        if (! $hasGroupPhoto) {
            $missingPhotoCount = $peserta->filter(fn (KuitansiSppdPeserta $item) => blank($item->foto_path))->count();
            if ($missingPhotoCount > 0) {
                throw new InvalidArgumentException('Kuitansi SPPD wajib melampirkan foto keberangkatan (foto bersama rombongan atau foto masing-masing peserta) sebelum diajukan.');
            }
        }
    }

    private function storePhotoFile(UploadedFile $file, string $directory): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$ext;

        return (string) $file->storeAs($directory, $filename, 'local');
    }
}
