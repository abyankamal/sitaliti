# ADR-002: Accumulative Carry-Over and Future Month Budget Reservation

## Status
Accepted

## Date
2026-11-05

## Context
Rencana Anggaran Kas (RAK) SKPD mengatur alokasi pagu belanja per bulan (Januari s.d. Desember). Sesuai prinsip pengelolaan keuangan daerah:
1. Sisa anggaran dari bulan-bulan lampau yang belum terealisasi dapat diakumulasikan ke bulan berjalan (*carry-over*).
2. Operator tidak diperkenankan menarik atau membebani anggaran dari bulan masa depan ($n > \text{activeSystemMonth}$) karena melanggar periode pencairan kas daerah dan menyebabkan defisit kas bulanan.
3. Kuitansi yang diajukan ke PPTK harus langsung memesan (*reserve*) pagu agar rekening belanja tidak mengalami *over-commitment* akibat pengajuan paralel dari subkegiatan lain.

## Decision
Menerapkan `BudgetReservationService` dengan formula:
$$\text{Pagu Tersedia Bulan } n = \sum_{m=1}^{n-1} (\text{Alokasi}_m - \text{Realisasi}_m - \text{Terkunci}_m) + \text{Alokasi}_n - \text{Realisasi}_n - \text{Terkunci}_n$$

Dengan batasan guard:
1. Jika $n > \text{systemMonth}$, status kuncian otomatis `LOCKED_FUTURE` dengan sisa dapat digunakan $= 0$.
2. Transaksi kuitansi dievaluasi apakah $\text{Nominal Bruto} \le \text{Sisa Pagu Tersedia}$. Jika melebihi, sistem melempar `PaguOverlimitException`.
3. Siklus hidup pagu:
   - `reserveBudget`: Menambah `terkunci_bulan` dan mengurangi sisa kuota.
   - `releaseBudget`: Mengurangi `terkunci_bulan` jika kuitansi ditolak/revisi mayor, dengan proteksi underflow ($\ge 0$).
   - `settleBudget`: Memindahkan nominal dari `terkunci_bulan` ke `realisasi_bulan` saat SP2D SIPD-RI terbit.

## Consequences
- Mencegah terjadinya *overspending* dan penarikan anggaran melompati bulan berjalan.
- Menjamin integritas pagu RAK sebesar Rp 14.252.126.458 di seluruh 132 rekening leaf.
- Sinkronisasi real-time antara status transaksi kuitansi dengan tabel `anggaran_kas_bulanan`.

