# ADR-007: Immutable Audit Trail and Null-on-Delete Actor for BPK RI Compliance

## Status
Accepted

## Date
2026-11-08

## Context
Dalam audit kepatuhan oleh Badan Pemeriksa Keuangan Republik Indonesia (BPK RI) dan Inspektorat Daerah, setiap pergeseran status kuitansi belanja, perubahan nominal, pelepasan pagu, dan penolakan berkas wajib dapat dilacak secara kronologis (*unalterable audit trail*).

Tantangan arsitektur:
1. **Penghapusan Pengguna (User Deletion):** Pegawai pemerintah, staf seksi, atau verifikator dapat dimutasi, pensiun, atau dihapus dari sistem. Jika relasi foreign key audit log menggunakan `cascadeOnDelete`, riwayat keuangan berharga akan terhapus secara permanen, yang berakibat fatal pada saat audit BPK.
2. **Kinerja Riwayat:** Menggabungkan tabel log dengan tabel operasional utama dapat memperlambat query transaksi harian.

## Decision
1. Membuat tabel terisolasi **`kuitansi_riwayat`** yang menyimpan `kuitansi_id`, `user_id`, `action`, `status_lama`, `status_baru`, `catatan`, `metadata` (JSON), dan `ip_address`.
2. Menetapkan relasi `user_id` dengan konfigurasi database **`nullOnDelete()`**. Jika pengguna dihapus dari sistem, catatan audit tetap utuh dan `user_id` diatur menjadi `NULL`, sementara nama aktor historis tetap tercatat di kolom metadata atau context snapshot jika diperlukan.
3. Mengotomasi pencatatan melalui **`App\Services\AuditTrailService`** pada setiap mutasi status signifikan (submit draf, persetujuan PPTK, lolos verifikasi, revisi minor/mayor, pembundelan SPP, realisasi SP2D, hingga deteksi keterlambatan SLA).

## Consequences
- Data riwayat audit mutlak kekal dan kebal terhadap mutasi/penghapusan akun pegawai (*tamper-resistant audit log*).
- Menyediakan endpoint audit `GET /api/v1/receipts/{id}/history` yang siap diinspeksi oleh auditor kapan pun dibutuhkan.
- Biaya penyimpanan minimal berbanding manfaat kepatuhan hukum (*regulatory compliance*).

