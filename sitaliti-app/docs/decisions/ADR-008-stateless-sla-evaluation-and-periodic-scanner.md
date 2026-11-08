# ADR-008: Stateless SLA Evaluation and Periodic Artisan Breach Scanner

## Status
Accepted

## Date
2026-11-09

## Context
Kecamatan Tarogong Kidul memberlakukan Service Level Agreement (SLA) perbaikan revisi minor kuitansi maksimal 2 (dua) hari kerja efektif bagi operator seksi dan kelurahan. 

Pendekatan umum penyimpanan status `is_breached` langsung sebagai kolom boolean di tabel transaksi menimbulkan masalah:
1. **Data Stale:** Kolom boolean di database membutuhkan cron konstan untuk memutakhirkan baris setiap menit. Jika cron tertunda, antarmuka pengguna menampilkan status yang tidak akurat.
2. **State Pollution:** Ketika kuitansi diperbaiki oleh operator dan berstatus `MENUNGGU_VERIFIKASI`, flag `is_breached` sering tertinggal jika pembersihan flag tidak ditangani secara manual.

## Decision
1. **Stateless Dynamic Evaluation pada Model:**
   Menambahkan Eloquent dynamic attribute accessors pada [`app/Models/Kuitansi.php`](file:///Users/muhammadabyankamal/proyek/sitaliti/sitaliti-app/app/Models/Kuitansi.php):
   - `is_sla_breached`: bernilai `true` hanya jika `status === 'REVISI_MINOR'`, `tenggat_sla !== null`, dan `now()->gt(tenggat_sla)`.
   - `sla_remaining_hours`: menghitung sisa jam secara dinamis melalui `now()->floatDiffInHours(tenggat_sla, false)`.
2. **Periodic Artisan Audit Scanner:**
   Menyediakan perintah konsol `php artisan sitaliti:check-sla` ([`CheckSlaBreachCommand.php`](file:///Users/muhammadabyankamal/proyek/sitaliti/sitaliti-app/app/Console/Commands/CheckSlaBreachCommand.php)) yang dipicu secara terjadwal (cron) untuk memindai pelanggaran SLA dan secara idempoten mencatat event `SLA_BREACHED` ke tabel audit trail `kuitansi_riwayat` tepat satu kali per kejadian.

## Consequences
- Tampilan status keterlambatan di antarmuka web selalu 100% akurat secara *real-time* tanpa risiko data basi (*zero stale data*).
- Riwayat audit keterlambatan tetap tercatat permanen untuk penilaian kinerja penatausahaan tanpa mengotori state operasional kuitansi.

