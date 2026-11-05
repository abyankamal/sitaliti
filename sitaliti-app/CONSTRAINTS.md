# Constraints — SI-TALITI v2.0 Backend & Core Engine

Last reviewed: 2026-11-05 by Developer 1 (Backend Lead) & Antigravity AI  
Acuan Desain: `Master_Blueprint_Backend_SITALITI.md`  
Stack: Laravel 12, MySQL 8.0, Vue 3, Inertia.js, Tailwind CSS

---

## 1. Floor (Selalu Ditegakkan, Zero-Tolerance)

- **Dilarang menekan pemeriksaan (No suppression comments)**: Dilarang menambahkan `@phpstan-ignore`, `@psalm-suppress`, `@ts-ignore`, atau `eslint-disable` untuk meloloskan kode bermasalah.
- **Dilarang stub kosong (No unimplemented stubs)**: Dilarang menggunakan `throw new Exception("Not implemented")` atau blok `catch (\Throwable $e) {}` kosong yang menelan error tanpa logging/penanganan.
- **Dilarang menghapus atau men-skip unit test**: Dilarang menambahkan `->skip()`, `markTestSkipped()`, atau menghapus assertion finansial hanya agar test suite hijau.
- **Dilarang hardcode kredensial/rahasia**: Seluruh token, kunci privat, dan kredensial database wajib melalui environment variable (`.env`).
- **File kontrak ini tidak boleh dilemahkan**: Standar dalam dokumen ini tidak boleh diturunkan demi mengejar kelulusan commit.

---

## 2. Invarian Hukum & Finansial (Financial Invariants)

Aturan matematis dan hukum tata kelola belanja daerah yang **wajib lolos 100% tanpa pengecualian**:

| Invarian Finansial | Formula / Aturan Wajib | Pemeriksa | Waktu Eksekusi |
|---|---|---|---|
| **Keseimbangan Kuitansi** | $\text{Nilai Bruto} - \sum \text{Potongan Pajak} = \text{Nilai Neto}$ | PHPUnit + MySQL Check Constraint `chk_keseimbangan` | Setiap edit kuitansi & test |
| **Pencegahan Defisit Pagu** | $\text{Terkunci Bulan } n + \text{Realisasi Bulan } n \le \text{Pagu Tersedia Bulan } n$ | `BudgetReservationService` (Atomic Lock) | Setiap submit ke PPTK |
| **Carry-Over Non-Retroaktif** | Sisa pagu bulan $1 \dots n-1$ dapat diakumulasikan ke bulan $n$, namun pagu bulan $n+1 \dots 12$ **dilarang keras** ditarik mendahului jadwal kas. | Unit Test `BudgetReservationTest` | Setiap kalkulasi kuota RAK |
| **Kepatuhan Pajak UU HPP** | Untuk belanja barang ber-PPN: $\text{DPP} = \text{Bruto} / 1.11$. PPh 22 (1.5%) & PPh 23 (2%) dipotong dari **DPP**, bukan dari Bruto. | Unit Test `TaxCalculationTest` | Setiap kalkulasi pajak |
| **Pemisahan Mutlak PPN & PBJT** | Belanja Mamin Restoran/Katering (Kategori B.2) **hanya dikenakan PBJT 10%** (Pajak Daerah Garut), dilarang dipungut PPN 11% pusat. | Unit Test `TaxCalculationTest` | Setiap kalkulasi pajak B.2 |
| **Bea Meterai Flat** | Transaksi $> \text{Rp } 5.000.000$ wajib Bea Meterai flat **Rp 10.000** (bukan persentase). | Unit Test `TaxCalculationTest` | Setiap transaksi > Rp5jt |
| **Anti-Klaim Ganda Bundel** | 1 kuitansi aktif hanya boleh masuk ke maksimal 1 bundel SPP-GU / LPJ aktif. | MySQL Stored Generated Column Unique Index | Database commit |
| **Anti-Duplikasi Nota Instan** | Nota/bukti transaksi dengan hash SHA-256 identik wajib ditolak seketika (*fail-fast HTTP 422*). | Synchronous `hash_file('sha256')` | Saat upload lampiran |

---

## 3. Batas Numerik & Penegakan Teknis (Enforced with Numbers)

| Dimensi | Ambang Batas (Rule) | Perintah Pemeriksa | Frekuensi / Waktu | Toleransi |
|---|---|---|---|---|
| **Code Style (Lint)** | Zero error / warning | `./vendor/bin/pint --test` | Setiap task selesai | Block |
| **Coverage Domain Finansial** | Changed lines pada `App\Services\*` $\ge 80\%$ | `php artisan test --coverage` | Akhir task & PR | Warn di Sprint awal, Block di Sprint 3 |
| **Waktu Eksekusi Kunci Pagu** | Transaksi `reserveBudget()` $\le 50\text{ ms}$ | Performance test / sub-millisecond atomic | Akhir sprint | Block jika deadlock |
| **Keamanan File Upload** | Maksimal 10MB per file, MIME type whitelist (`pdf`, `jpg`, `png`), private storage | Controller Validation | Request upload | Block |
| **Privasi Data Publik** | Endpoint publik `/verify/{qr_token}` meredaksi 100% NPWP lengkap dan nomor rekening | Feature Test `QrVerificationTest` | CI & Test Run | Block |

---

## 4. Batasan Arsitektur (Architectural Boundaries)

1. **Controller Tipis, Service Tebal**:
   - Controller dilarang melakukan query manipulasi saldo pagu secara langsung (`DB::table('anggaran_kas_bulanan')->update(...)`).
   - Seluruh mutasi pagu wajib melalui implementasi `BudgetReservationServiceInterface`.
   - Seluruh perhitungan pajak wajib melalui implementasi `TaxCalculationServiceInterface`.
2. **Pola Transaksi Database**:
   - `lockForUpdate()` dilarang digantung di luar request HTTP. Wajib dibungkus dalam `DB::transaction()` sub-milidetik.
   - Urutan query tabel `anggaran_kas_bulanan` wajib deterministik (`ORDER BY bulan ASC`) untuk mencegah deadlock InnoDB.
3. **Kompatibilitas Kontrak Frontend**:
   - Respons API `/api/v1/budget/*`, `/api/v1/receipts/*`, dan `/api/v1/verification/*` wajib mempertahankan struktur payload JSON yang dikonsumsi oleh `budgetService.js`, `receiptService.js`, dan `verificationService.js`.

---

## 5. Script Pemeriksaan Lifecycle

Convenience scripts di `composer.json` / `package.json`:

- `composer check:fast`: Menjalankan linter Laravel Pint dan syntax check php (`< 5 detik`).
- `composer check:task`: Menjalankan `check:fast` ditambah seluruh unit test suite finansial (`< 90 detik`).
- `composer check:full`: Menjalankan suite lengkap termasuk laporan test coverage.

---

## 6. Pengecualian Resmi (Exceptions Log)

| ID | Aturan yang Dikecualikan | Path / Modul | Alasan Teknis | Penanggung Jawab | Kedaluwarsa |
|---|---|---|---|---|---|
| *Belum ada pengecualian* | - | - | - | - | - |

