# ADR-003: Synchronous SHA-256 Hash for Receipt Anti-Duplication

## Status
Accepted

## Date
2026-11-06

## Context
Salah satu celah kecurangan administrasi dan audit temuan BPK dalam SPJ belanja daerah adalah penggunaan nota atau kuitansi fisik kembar (misal satu nota foto copy/scan digunakan berulang kali pada dua transaksi kuitansi berbeda dalam subkegiatan yang sama). Validasi manual oleh verifikator manusia sering kali gagal mendeteksi kesamaan berkas nota fisik jika tanggal transaksi atau uraian dibuat sedikit berbeda.

## Decision
Menerapkan proteksi cryptographic hash SHA-256 pada setiap berkas fisik lampiran kuitansi:
1. Saat file diunggah via `POST /api/v1/receipts`, backend menghitung `hash_file('sha256', $file->getRealPath())` secara sinkron.
2. Memasang indeks unik gabungan (*composite unique index*) di tabel `kuitansi_lampiran`:
   ```php
   $table->unique(['anggaran_rekening_id', 'file_hash'], 'uq_rekening_file_hash');
   ```
3. Jika berkas dengan hash yang sama telah tercatat pada rekening anggaran yang sama, request ditolak secara instan dengan HTTP 422 dan error code `DUPLICATE_RECEIPT_ATTACHMENT`.

## Consequences
- Deteksi nota fiktif ganda berjalan secara deterministik pada tingkat basis data tanpa latensi background job.
- File yang diunggah disimpan di *private local storage* dengan nama unik berstruktur tanggal, dan checksum integritas tersimpan permanen.
- Operator yang mencoba mengunggah nota kembar langsung mendapat umpan balik penolakan instan.

