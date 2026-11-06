# ADR-004: Stored Generated Column for Anti-Double Claim in Two-Tier Settlement

## Status
Accepted

## Date
2026-11-06

## Context
Pada model *Two-Tier Settlement*, kuitansi transaksi atomik (Tier 1) dihimpun ke dalam berkas pengesahan SPP/LPJ (Tier 2). Risiko kritis yang harus dicegah adalah *Double Claim*: sebuah kuitansi yang telah dimasukkan ke dalam draf SPP-GU A tidak boleh secara sengaja maupun tidak sengaja dimasukkan ke dalam draf SPP-GU B. 

Namun, jika kuitansi tersebut dikeluarkan dari SPP-GU A (*unbundle*) karena catatan revisi administratif, kuitansi tersebut harus dapat dimasukkan kembali ke bundel lain di masa depan. Indeks unik sederhana `unique(kuitansi_id)` pada tabel relasi bundel tidak dapat digunakan karena riwayat pengeluaran kuitansi harus tetap tercatat untuk audit trail.

## Decision
Menggunakan *Stored Generated Column* (kolom virtual tersimpan) ANSI SQL yang didukung secara identik oleh MySQL 8.0 dan SQLite 3.31+:
1. Tabel `pengajuan_spj_item` memiliki kolom:
   - `status_item`: ENUM/VARCHAR (`AKTIF`, `DIKELUARKAN`)
   - `active_kuitansi_id`: `STORED AS (CASE WHEN status_item = 'AKTIF' THEN kuitansi_id ELSE NULL END)`
2. Menetapkan indeks unik pada kolom generated:
   ```sql
   UNIQUE KEY uq_satu_kuitansi_aktif (active_kuitansi_id)
   ```
3. Berdasarkan semantik SQL standard, nilai `NULL` tidak melanggar batasan `UNIQUE` (multiple `NULL` diperbolehkan).

## Consequences
- Kuitansi hanya dapat berstatus `AKTIF` di tepat satu bundel SPJ pada satu waktu. Upaya memasukkan kuitansi yang sama ke bundel lain langsung memicu *Integrity Constraint Violation*.
- Saat kuitansi di-*unbundle*, statusnya diubah menjadi `DIKELUARKAN`. Kolom `active_kuitansi_id` otomatis terhitung ulang menjadi `NULL`, membebaskan kuitansi tersebut untuk dibundel ulang ke pengajuan baru.
- Menjamin integritas relasional anti-klaim ganda tanpa memerlukan *soft-delete lock* rumit di level aplikasi.

