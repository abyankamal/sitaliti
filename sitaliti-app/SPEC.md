# Specification: SI-TALITI v2.0 (Pre-Audit QA Gateway & Settlement Engine)

Dokumen ini adalah spesifikasi teknis implementasi resmi sistem **SI-TALITI v2.0** yang mengikat seluruh pengembangan kode pada backend Laravel 12 dan frontend Inertia.js (Vue 3).

---

## 1. Objective (Tujuan Pengembangan)

### Latar Belakang & Masalah
Sebelum pengajuan dokumen SPJ ke SIPD-RI, bendahara dan verifikatur di tingkat kecamatan sering menghadapi kendala verifikasi fisik:
- Ketidaksesuaian realisasi belanja dengan jadwal kas bulanan (RAK).
- Kesalahan perhitungan potongan pajak (PPN/PPh/Pajak Daerah) dan bea meterai.
- Ketiadaan kontrol terhadap duplikasi nota atau kuitansi yang diklaim ganda ke beberapa bundel SPJ.
- Alur revisi fisik yang lambat dan sulit dilacak riwayat perbaikannya.

### Solusi: SI-TALITI v2.0
Membangun *Pre-Audit Quality Assurance Gateway* dan *Two-Tier Settlement Engine* yang memvalidasi kuitansi belanja secara atomik (Tier 1) sebelum dikelompokkan ke dalam Bundel Pengesahan SPP-GU / LPJ BPP (Tier 2). Sistem ini memvalidasi anggaran kas, menghitung pajak secara legal, menyediakan antarmuka pemeriksaan *split-screen*, dan menerbitkan Lembar Kendali Verifikasi ber-QR Code sebagai syarat sah input SIPD-RI.

### Lokus & Dataset Pilot
- **Lokus**: Satuan Kerja Perangkat Daerah Kecamatan Tarogong Kidul dan 5 Kelurahan (Sukagalih, Sukakarya, Pataruman, Jayawaras, Haurpanggung).
- **Dataset Anggaran**: RAK Perubahan APBD 2026.
- **Pagu Total Dikelola**: Rp 14.252.126.458 (132 rekening leaf, 39 subkegiatan).

---

## 2. Tech Stack & Dependencies

| Lapisan | Teknologi | Versi / Keterangan |
|---|---|---|
| **Backend Framework** | Laravel | 12.x (PHP 8.2+) |
| **Frontend Framework** | Vue 3 + Inertia.js | Vue 3.4+, Inertia 2.0 |
| **CSS / Styling** | Tailwind CSS | Tailwind v3 / PostCSS (Gov Deep Navy Theme) |
| **Basis Data** | MySQL | 8.0+ (InnoDB Engine, Stored Virtual Columns) |
| **Autentikasi & RBAC** | Laravel Sanctum + Spatie Permission | SPA Stateful Cookies + Multi-Role Granular |
| **Audit Trail** | Spatie Activitylog | Pencatatan transisi status & revisi kuitansi |
| **Excel Parser** | OpenSpout (`openspout/openspout`) | Streaming XLSX parser hemat memori (<10MB) |
| **QR Code Generator** | Simple-QRCode | Generator token QR untuk cetakan lembar kendali |
| **Testing** | PHPUnit | 11.5+ |

---

## 3. Commands (Daftar Perintah Eksekusi Penuh)

```bash
# 1. Menjalankan Server Pengembangan (Concurrently: Laravel + Vite)
composer run dev

# 2. Pengecekan Kualitas Cepat (< 5 detik: Pint linter + Syntax Check)
composer run check:fast

# 3. Pengecekan Kualitas Task (< 90 detik: Pint + PHPUnit Suite)
composer run check:task

# 4. Pengujian Suite Lengkap dengan Laporan Coverage
php artisan test --coverage

# 5. Eksekusi Migrasi Database & Seeder Resmi
php artisan migrate:fresh --seed

# 6. Impor Data RAK dari File Excel (CLI)
php artisan sitaliti:import-rak "storage/app/rak/RAK_Belanja_7.01.0.00.0.00.41.0000 - Kecamatan Tarogong Kidul.xlsx"

# 7. Build Aset Frontend untuk Produksi
npm run build
```

---

## 4. Project Structure (Struktur Direktori)

```text
sitaliti-app/
├── app/
│   ├── Contracts/             → Interface contracts (Budget, Tax, Importer, Verification)
│   ├── Exceptions/            → Domain exceptions (PaguOverlimitException)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/         → RakUploadController, MasterDataController
│   │   │   ├── Api/           → BudgetApiController, ReceiptApiController, VerificationApiController
│   │   │   ├── Web/           → KuitansiWebController, VerificationWorkspaceController, BundlesWebController
│   │   │   └── Auth/          → Authentication controllers
│   │   ├── Middleware/        → HandleInertiaRequests, RoleMiddleware
│   │   └── Requests/          → Form Request validators (StoreReceiptRequest, VerificationActionRequest)
│   ├── Models/                → UnitKerja, AnggaranRekening, AnggaranKasBulanan, Kuitansi, TaxRule, dll.
│   └── Services/              → BudgetReservationService, TaxCalculationService, RakImporterService, VerificationService
├── database/
│   ├── migrations/            → DDL MySQL 8.0 (presisi DECIMAL(15,2), virtual column)
│   └── seeders/               → DatabaseSeeder, UnitKerjaSeeder, TaxRuleSeeder, MasterChecklistSeeder
├── resources/
│   ├── css/                   → app.css (Gov theme)
│   └── js/
│       ├── Components/        → GovButton, GovCard, GovBadge, GovCurrencyInput
│       ├── Layouts/           → GovAppLayout.vue, GuestLayout.vue
│       ├── Modules/           → Gatekeeper/, Verification/, Checklist/, ControlSheet/
│       ├── Pages/             → Dashboard.vue, Receipts/Create.vue, Verification/Workspace.vue, Bundles/Index.vue
│       ├── Services/          → budgetService.js, receiptService.js, verificationService.js
│       └── Utils/             → currency.js, date.js
├── tests/
│   ├── Feature/               → HTTP tests (ReceiptApiTest, VerificationApiTest, QrPublicTest)
│   └── Unit/                  → Pure domain tests (BudgetReservationTest, TaxCalculationTest, OpenSpoutTest)
├── AGENTS.md                  → Instruksi wajib untuk coding agent
├── CAPABILITY_MAP.md          → Peta modul kapabilitas & urutan bangun
├── CONSTRAINTS.md             → Kontrak kualitas & invarian finansial zero-tolerance
└── SPEC.md                    → Spesifikasi sistem (dokumen ini)
```

---

## 5. Code Style & Konvensi

### Contoh Standar Implementasi PHP (Domain Service):
```php
<?php

namespace App\Services;

use App\Contracts\BudgetReservationServiceInterface;
use App\Exceptions\PaguOverlimitException;
use App\Models\AnggaranKasBulanan;
use Illuminate\Support\Facades\DB;

final class BudgetReservationService implements BudgetReservationServiceInterface
{
    public function reserveBudget(string $anggaranRekeningId, int $bulan, float $nominalBruto): void
    {
        // DB Transaction dengan pessimistic locking atomic sub-milidetik
        DB::transaction(function () use ($anggaranRekeningId, $bulan, $nominalBruto) {
            // Urutan deterministik menghindari deadlock InnoDB
            $alokasiBulan = AnggaranKasBulanan::where('anggaran_rekening_id', $anggaranRekeningId)
                ->where('bulan', $bulan)
                ->lockForUpdate()
                ->firstOrFail();

            $carryOver = $this->calculateCarryOver($anggaranRekeningId, $bulan);
            $totalTersedia = ($alokasiBulan->pagu_bulan + $carryOver) - $alokasiBulan->realisasi_bulan;
            $sisaDapatDigunakan = $totalTersedia - $alokasiBulan->terkunci_bulan;

            if ($nominalBruto > $sisaDapatDigunakan) {
                throw new PaguOverlimitException(
                    "Pagu tidak mencukupi. Tersedia: Rp " . number_format($sisaDapatDigunakan, 2)
                );
            }

            // Validasi proteksi saldo kumulatif bulan mendatang (bulan n..12)
            $this->assertDownstreamLiquidity($anggaranRekeningId, $bulan, $nominalBruto);

            $alokasiBulan->increment('terkunci_bulan', $nominalBruto);
        });
    }
}
```

### Konvensi Penamaan:
- **Tabel Basis Data**: Jamak snake_case (`master_unit_kerja`, `anggaran_rekening`, `kuitansi_item`).
- **Model Eloquent**: Singular PascalCase (`UnitKerja`, `AnggaranRekening`, `Kuitansi`).
- **Service Interfaces**: Sufiks `Interface` di namespace `App\Contracts` (`BudgetReservationServiceInterface`).
- **Nominal Keuangan**: Wajib `DECIMAL(15,2)` di database dan `float` / `string numeric` di kode PHP.
- **Form UI**: Format angka rupiah wajib menggunakan utility class `tabular-nums` dan filter `formatRupiah()`.

---

## 6. Testing Strategy

| Tingkat Uji | Lokasi Direktori | Cakupan yang Diuji | Target Kelulusan |
|---|---|---|---|
| **Unit Test Finansial** | `tests/Unit/Services/` | Logika carry-over RAK, proteksi defisit masa depan, formula DPP/PPN/PBJT/PPh, perhitungan Bea Meterai | 100% lulus, Coverage $\ge 80\%$ |
| **Feature Test API** | `tests/Feature/Api/` | Endpoint `/api/v1/budget/*`, validasi draft kuitansi, submit PPTK, transisi revisi minor/mayor, upload lampiran | 100% lulus, status HTTP & skema JSON valid |
| **Security & Privacy** | `tests/Feature/Security/` | Deteksi instan duplikasi SHA-256 (HTTP 422), redaksi PII vendor pada `/verify/{qr_token}`, streaming otorisasi PDF | 100% lulus, zero data leakage |
| **Invarian Bundel** | `tests/Feature/Settlement/` | Pencegahan kuitansi tunggal masuk ke 2 bundel aktif via MySQL Virtual Column | Error MySQL ditangkap & ditolak |

---

## 7. Boundaries (Batasan Tiga Tingkat)

### ALWAYS DO (Wajib Selalu Dilakukan):
- Jalankan `composer run check:fast` sebelum membuat commit kode.
- Bungkus operasi mutasi pagu (`reserveBudget`, `releaseBudget`, `settleBudget`) dalam `DB::transaction()` dengan pessimistic lock singkat.
- Validasi seluruh input nominal belanja: wajib positif ($> 0$), tipe `DECIMAL(15,2)`, dan lolos uji keseimbangan matematis ($\text{Bruto} - \sum \text{Pajak} = \text{Neto}$).
- Hitung hash SHA-256 secara sinkron seketika file diunggah.

### ASK FIRST (Wajib Minta Konfirmasi Pengguna Dahulu):
- Mengubah skema migrasi tabel yang sudah di-commit atau mengubah nama kolom database.
- Menambahkan dependensi baru di `composer.json` atau `package.json`.
- Mengubah batasan hak akses role (misalnya memberikan wewenang verifikasi kepada Operator).

### NEVER DO (Dilarang Keras):
- Menahan koneksi database `lockForUpdate` di luar siklus request HTTP (misal menunggu approval PPTK yang berhari-hari).
- Mengurangi atau menghapus batasan kualitas pada `CONSTRAINTS.md`.
- Memungut PPN 11% dan PBJT Mamin 10% secara bersamaan pada 1 transaksi.
- Memotong PPh 22/23 dari Nilai Bruto pada kuitansi ber-PPN (wajib dari DPP).
- Menampilkan data pribadi vendor (NPWP lengkap, nomor rekening) di halaman publik QR verify.

---

## 8. Success Criteria (Kriteria Keberhasilan / Definition of Done)

1. **Integritas Anggaran**:
   - Seluruh 132 rekening leaf dari file RAK Perubahan 2026 terisi di database dengan total pagu tepat Rp 14.252.126.458.
   - Tidak ada transaksi yang dapat lolos jika nominal kuitansi melampaui sisa pagu bulan berjalan + carry-over.
   - Pagu bulan $n+1$ hingga 12 terkunci dan tidak dapat ditarik mendahului jadwal kas.
2. **Kepatuhan Regulasi Pajak**:
   - Transaksi $> \text{Rp } 5.000.000$ otomatis mewajibkan Bea Meterai Rp 10.000.
   - Kategori B.2 (Mamin) otomatis memungut PBJT 10% dan memblokir pungutan PPN 11%.
   - Kategori B.1 (ATK) $> \text{Rp } 2.000.000$ menghitung DPP, PPN 11%, dan PPh 22 secara presisi.
3. **Kesiapan Integrasi Frontend**:
   - Service adapter `resources/js/Services/` (`budgetService.js`, `receiptService.js`, `verificationService.js`) terhubung mulus ke endpoint Laravel tanpa mengubah komponen antarmuka yang dibuat Developer 2.
4. **Keamanan & Akuntabilitas**:
   - Nota kembar tertolak seketika via SHA-256 hash collision detection.
   - Setiap revisi tercatat di Spatie Activitylog dengan tenggat SLA 2 hari kerja efektif.
   - Lembar Kendali ber-QR Code dapat divalidasi keabsahannya di URL publik tanpa membocorkan data rahasia vendor.

---

## 9. Open Questions (Pertanyaan Terbuka / Keputusan Lanjutan)
1. Apakah format penomoran internal kuitansi memiliki pola resmi dinas tertentu (misal: `KUI/{KODE_SUBKEG}/{TAHUN}/{NO_URUT}`) atau menggunakan standar default sistem?
2. Pada migrasi pilot, apakah ada data saldo carry-over riil dari bulan-bulan awal 2026 yang perlu langsung diset sebagai data awal?

