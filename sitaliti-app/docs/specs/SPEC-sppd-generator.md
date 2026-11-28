# Spec: SPPD Document & Lifecycle Generator (`sppd-generator`)

> **Status:** Reconciled v2 — Siap Review Akhir sebelum Phase 2 (`Plan`)  
> **Acuan Ide:** [sppd-generator-terintegrasi.md](../ideas/sppd-generator-terintegrasi.md)  
> **Acuan Template Klien:** `../SPPD kumplit kec.xlsx`  
> **Catatan Doubt Cycle:** Direkonsiliasi dari *single-model adversarial review* (cross-model dilewati sesuai progres dialog).

---

## DECISIONS LOCKED (Hasil Konfirmasi & Rekonsiliasi)
1. **Ukuran Kertas Baku:** **F4 / Folio (`215mm × 330mm`)** via `barryvdh/laravel-dompdf`.
2. **Aturan Rombongan (PMK 113/PMK.05/2012, Perpres 33/2020, Permendagri 77/2020):**
   - **Surat Tugas (ST):** **1 Surat Tugas kolektif** memuat seluruh peserta rombongan.
   - **SPD (Depan & Belakang):** **1 SPD per peserta** (masing-masing memiliki `nomor_spd` dan blok TTD tiba/berangkat sendiri), karena hak biaya melekat pada jabatan/golongan tiap pegawai. Kolom *Pengikut* (Butir 8) bersifat opsional untuk pendamping non-pelaksana SPJ.
   - **Kwitansi & Rincian Biaya:**
     - **Peserta Tunggal (1 orang):** 1 Kwitansi Tanda Pembayaran + 1 Rincian Biaya + 1 SPD Rampung atas nama peserta tersebut.
     - **Rombongan (>1 orang):** **1 Kuitansi SPJ kolektif** di sistem SI-TALITI (`sppd_assignments.kuitansi_id` unik 1-to-1) yang dicetak sebagai **1 Kwitansi Pembayaran Utama** (nominal = `Kuitansi.nominal_bruto`) + **Daftar Nominatif Penerima** + **N Lembar Rincian Biaya & Perhitungan SPD Rampung per peserta** (jumlah seluruh peserta = `Kuitansi.nominal_bruto`).
   - **Laporan Hasil (`hasil SPPD`):** 1 laporan per penugasan (memuat daftar pelaksana yang menandatangani, atau opsi per peserta).
3. **Peserta Non-ASN Disimpan di Master `pegawai`:**
   - Tabel `pegawai` diperluas dengan `status_kepegawaian` (`ASN` | `PPPK` | `NON_ASN`), `nip` **nullable** (wajib unik jika terisi; string kosong/strip dinormalisasi menjadi `null`), serta kolom opsional `nik`.
   - Seluruh peserta SPPD—baik ASN, PPPK, maupun Non-ASN—tetap memiliki `pegawai_id` (`NOT NULL`), sehingga **100% kompatibel** dengan `kuitansi_sppd_peserta`, anti-bentrok `SppdScheduleService`, dan rekap `SppdReportService`.
   - Pada output PDF, peserta `NON_ASN` (atau `nip === null`) otomatis mencetak `-` pada baris NIP dan Pangkat/Golongan (sesuai sheet `depan non ASN`).

---

## 1. Objective

Membangun modul penugasan SPPD pra-kuitansi dan generator 5 dokumen PDF siap tanda tangan basah di **SI-TALITI** (Kecamatan Tarogong Kidul & 5 Kelurahan) yang terhubung utuh dengan master `pegawai`, `master_unit_kerja`, `SppdScheduleService`, `BudgetReservationService`, dan `Kuitansi`.

### User Stories & Alur Utama
1. **Operator Unit Kerja (Pra-Berangkat):**
   - Membuat penugasan SPPD (`sppd_assignments`) berisi maksud tugas, tempat berangkat/tujuan, alat angkut, tanggal berangkat & kembali, pejabat pemberi perintah (snapshot nama/NIP/jabatan, default diambil dari `master_unit_kerja` tetapi dapat diubah jika Camat/Lurah sendiri yang bertugas), opsional `anggaran_rekening_id` (untuk Butir 9b *Mata Anggaran* pada SPD Depan), serta 1..N peserta dari master `pegawai` (ASN, PPPK, atau Non-ASN).
   - Setiap peserta dapat memiliki `nomor_spd` sendiri, `tingkat_biaya` (kode tingkat maupun nominal harian sesuai kebutuhan template), serta catatan `pengikut` opsional.
   - Sistem langsung memeriksa bentrok jadwal lintas **seluruh Kuitansi SPPD aktif DAN seluruh Penugasan SPPD pra-berangkat aktif** (`SppdScheduleService`).
   - Mengunduh **PDF Pra-Berangkat** (**Surat Tugas** + **N × SPD Depan** + **N × SPD Belakang**) untuk TTD basah.
2. **Pelaksana / Bendahara (Pasca-Berangkat & SPJ):**
   - Mengisi **Laporan Hasil Perjalanan Dinas** dan rincian biaya per peserta (`sppd_peserta_biaya`, misal Uang Makan Harian, Uang Transpor Lokal).
   - Mengonversi penugasan menjadi **1 `Kuitansi` SPJ baru** (kategori `B4_PERJADIN`) atau menautkannya ke `Kuitansi` existing yang memenuhi syarat guard.
   - Mengunduh **PDF Pasca-Berangkat** (**Kwitansi Utama [+ Daftar Nominatif jika >1]** + **N × Rincian Biaya & SPD Rampung** + **Laporan Hasil**) atau **1 Bundel PDF Komplit (5 dokumen)**.

### Aturan Sinkronisasi & Kunci Integritas SPJ
- **Guard Penautan Kuitansi Existing:** Penautan `sppd_assignments.kuitansi_id` (constraint `UNIQUE`) hanya diizinkan bila `Kuitansi` memiliki `unit_kerja_id` yang sama, `SppdScheduleService::isSppdRekening($rekening) === true`, berstatus `DRAFT` atau `REVISI_MAYOR`, belum tertaut ke `sppd_assignment` lain, dan lolos `assertNoConflictsWithLock()`.
- **Kunci Mutasi Pasca-Submit:** Setelah `Kuitansi` tertaut keluar dari status `DRAFT` / `REVISI_MAYOR` (`DIAJUKAN_PPTK`, `REVISI_MINOR`, `LOLOS_VERIFIKASI`, `DIBUNDEL`, `SELESAI_SP2D`), perubahan peserta, tanggal, maupun komponen biaya pada `sppd_assignments` **ditolak (HTTP 422)** agar dokumen PDF tidak pernah menyimpang dari ledger `BudgetReservationService` dan `chk_keseimbangan`.
- **Konsistensi Pajak & Golongan:** Saat konversi ke `Kuitansi`, `golongan_asn` ditentukan eksplisit dari komposisi peserta (misal `NON_ASN` jika seluruh peserta Non-ASN), bukan fallback diam-diam ke `GOLONGAN_III`.
- **SPD Rampung & Terbilang:** Kolom `Yang telah dibayar semula` default `0` (`-`), `Sisa kurang/lebih` dihitung eksplisit, dan helper `Terbilang` wajib menangani nilai `0` (`Nol rupiah`) dengan aman.

### Skenario Khusus: Peserta Non-ASN
1. **Master Pegawai (`pegawai`):**
   - `status_kepegawaian`: `enum/string` (`ASN`, `PPPK`, `NON_ASN`), default `ASN`.
   - `nip`: `nullable` + `UNIQUE(nip)` (di MySQL beberapa baris `NULL` diperbolehkan pada unique index). `Pegawai::normalizeNip()` mengembalikan `null` bila input kosong, hanya spasi, atau `-`.
   - `nik`: `nullable string(20)` sebagai identitas opsional Non-ASN pada rekap laporan (`NIP / NIK`).
   - Validasi `PegawaiApiController`: `nip` wajib diisi untuk `ASN`/`PPPK`, opsional (`nullable`) untuk `NON_ASN`.
   - **Deletion Guard:** `PegawaiApiController::destroy()` menolak penghapusan pegawai bila sudah dipakai di `kuitansi_sppd_peserta` **atau** di `sppd_peserta`.
   - **UI Existing:** `Pegawai/Index.vue`, `SppdParticipantsSection.vue`, dan `Sppd/Index.vue` menampilkan badge `Non-ASN` atau `-` ketika `nip` bernilai `null`.
2. **Rendering PDF Non-ASN:**
   - **Surat Tugas, SPD Depan (`depan non ASN`), Kwitansi, Rincian Biaya, Laporan Hasil:** Jika peserta berstatus `NON_ASN` atau `nip === null`, baris NIP mencetak `-` dan baris Pangkat/Golongan mencetak `-`.

### RBAC & Isolasi Multi-Unit Kerja
- Operator / PPTK / BPP hanya dapat melihat, mengubah, menghapus, dan mencetak `sppd_assignments` milik `unit_kerja_id` masing-masing.
- Role SKPD-wide (`VERIFIKATOR`, `KPA`, `BP`) dapat melihat lintas unit kerja; role read-only (`LURAH`, `KPA` sesuai `User::isReadOnlyRole()`) **dilarang** melakukan mutasi (create/update/delete/convert).

---

## 2. Tech Stack
- **Backend:** PHP `^8.2`, Laravel `^12.0`
- **Frontend:** Vue 3 + Inertia.js (`inertiajs/inertia-laravel ^2.0`) + Tailwind CSS + Ziggy (`tightenco/ziggy ^2.0`)
- **Database:** MySQL 8.0
- **PDF Engine:** `barryvdh/laravel-dompdf ^3.1` (Blade HTML Table Templates → PDF F4)
- **Code Style & Testing:** Laravel Pint (`^1.13`), PHPUnit (`^11.5.3`)

---

## 3. Commands
```bash
# Development server
composer dev

# Code style check (wajib zero error)
composer check:fast

# Full verification (Pint + PHPUnit)
composer check:task

# Filtered SPPD test run
php artisan test --filter=Sppd

# Database migration & seeder
php artisan migrate
```

---

## 4. Project Structure
```text
sitaliti-app/
├── app/
│   ├── Http/Controllers/           # SppdAssignmentController (Inertia + PDF stream)
│   ├── Http/Controllers/Api/       # SppdAssignmentApiController, PegawaiApiController
│   ├── Http/Requests/              # StoreSppdAssignmentRequest, UpdateSppdAssignmentRequest, ConvertSppdToKuitansiRequest
│   ├── Models/                     # SppdAssignment, SppdPeserta, SppdPesertaBiaya, Pegawai, UnitKerja (`master_unit_kerja`), Kuitansi
│   └── Services/                   # SppdAssignmentService, SppdScheduleService, SppdPdfService, TerbilangService
├── database/
│   ├── migrations/                 # alter pegawai, alter master_unit_kerja, create sppd_assignments, sppd_peserta, sppd_peserta_biaya
│   └── seeders/                    # UnitKerjaSeeder (kop surat & pejabat default Kecamatan + 5 Kelurahan)
├── resources/
│   ├── js/Pages/                   # SppdAssignments/* (Create/Edit/Show), pembaruan Pegawai/Index.vue & Sppd/Index.vue
│   └── views/pdf/sppd/             # Blade F4 table-based templates (surat-tugas, spd-depan, spd-belakang, kwitansi, daftar-nominatif, rincian-biaya, laporan-hasil, bundle)
└── tests/
    ├── Unit/                       # TerbilangServiceTest, SppdNonAsnFormattingTest
    └── Feature/                    # SppdAssignmentTest, SppdConflictPreDepartureTest, SppdPdfGenerationTest, PegawaiNonAsnTest
```

---

## 5. Code Style & DomPDF Rules
- **Controller Tipis, Service Tebal:** Validasi di `FormRequest`, seluruh orkestrasi DB transaction, locking jadwal, konversi Kuitansi, dan PDF di `App\Services\*`.
- **DomPDF Layout Constraints (Wajib):**
  - Gunakan `@page { size: 215mm 330mm; margin: 12mm 14mm; }` secara konsisten di CSS agar tidak tertimpa.
  - Gunakan HTML `<table>` klasik (dilarang Flexbox/CSS Grid di Blade PDF karena tidak didukung DomPDF).
  - Bungkus setiap halaman dokumen dalam `.doc-page { page-break-after: always; }` dengan `.doc-page:last-child { page-break-after: auto; }` agar tidak menghasilkan halaman kosong di akhir bundel.
  - Beri `page-break-inside: avoid` pada setiap blok tanda tangan (TTD) dan tiap blok `I–VI` pada SPD Belakang (`BLKG OKE`).

```php
<?php

namespace App\Services;

use App\Models\SppdAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

class SppdPdfService
{
    /**
     * Menghasilkan bundel PDF SPPD ukuran F4 (215mm x 330mm) siap tanda tangan basah.
     */
    public function generateBundle(SppdAssignment $assignment, string $scope = 'all'): DomPdf
    {
        $assignment->loadMissing([
            'unitKerja',
            'rekening',
            'peserta.pegawai',
            'peserta.biayaItems',
            'kuitansi.rekening',
        ]);

        return Pdf::loadView('pdf.sppd.bundle', [
            'assignment' => $assignment,
            'scope' => $scope, // 'pre' | 'post' | 'all'
        ])->setPaper([0, 0, 609.45, 935.43], 'portrait');
    }
}
```

---

## 6. Testing Strategy
- **Unit Tests (`tests/Unit/`):**
  - `TerbilangServiceTest`: nominal bulat ribuan/jutaan, angka `0`, dan batas validasi negatif.
  - `Pegawai::normalizeNip()`: input `''`, `'   '`, `'-'` menghasilkan `null`; input `'1970 0510-199009'` menghasilkan `'19700510199009'`.
- **Feature Tests (`tests/Feature/`):**
  - **Pegawai Non-ASN:** Create/update beberapa `NON_ASN` dengan `nip = null` tidak bentrok unique constraint; `ASN` tetap wajib `nip`; penghapusan pegawai yang dipakai di `sppd_peserta` ditolak HTTP 422 (`PEGAWAI_IN_USE`).
  - **Konflik Jadwal Lintas Fase:** Bentrok antara 2 `sppd_assignments` pra-berangkat (`kuitansi_id = null`), dan bentrok antara `sppd_assignments` dengan `kuitansi_sppd_peserta` terdeteksi oleh `SppdScheduleService` (dan edit draft sendiri tidak memicu false-conflict).
  - **Konversi & Kunci SPJ:** Konversi ke `Kuitansi` menyalin peserta & total biaya (`SUM(biaya) === nominal_bruto`); penautan ke `Kuitansi` non-SPPD / beda unit / status terkunci ditolak HTTP 422/403; edit penugasan setelah `Kuitansi` diajukan ditolak HTTP 422.
  - **RBAC Multi-Unit:** Operator kelurahan A tidak bisa akses/ubah/cetak penugasan kelurahan B (HTTP 403).
  - **PDF Output:** Endpoint `scope=pre|post|all` untuk skenario tunggal ASN, tunggal Non-ASN (`-` di NIP & golongan), dan rombongan (1 ST + N SPD + Kwitansi Kolektif + Daftar Nominatif + N Rincian) mengembalikan HTTP `200`, `application/pdf`, dan header `%PDF-`.

---

## 7. Boundaries
- **Always do:**
  - Jalankan `composer check:task` sebelum menyelesaikan setiap task.
  - Terapkan `unit_kerja_id` scoping & `isReadOnlyRole()` guard di setiap endpoint SPPD.
  - Validasi kecocokan total biaya peserta terhadap `Kuitansi.nominal_bruto`.
- **Ask first:**
  - Perubahan struktur kolom pada `pegawai`, `master_unit_kerja`, atau `kuitansi` di luar cakupan yang tertera di spesifikasi ini.
  - Penambahan dependensi Composer/NPM baru.
- **Never do:**
  - Melemahkan `CONSTRAINTS.md`, invarian `BudgetReservationService`, atau `TaxCalculationService`.
  - Menghapus atau men-skip unit/feature test (`->skip()`, `markTestSkipped()`).
  - Menambahkan `@phpstan-ignore`, `@ts-ignore`, atau `eslint-disable`.

---

## 8. Success Criteria
- [ ] Master `pegawai` mendukung `ASN`, `PPPK`, dan `NON_ASN` (`nip` nullable) tanpa memecah unique constraint maupun halaman UI existing.
- [ ] Operator Kecamatan dan 5 Kelurahan dapat membuat penugasan SPPD (1–5+ peserta ASN/PPPK/Non-ASN) pra-berangkat tanpa membuat `Kuitansi` terlebih dahulu.
- [ ] `SppdScheduleService` mendeteksi bentrok jadwal baik antar `sppd_assignments` pra-berangkat maupun dengan `kuitansi_sppd_peserta`.
- [ ] PDF ukuran **F4** ter-generate rapi tanpa halaman kosong berlebih dan tanpa blok TTD terpotong untuk:
  - Tahap 1 (`pre`): Surat Tugas + N × SPD Depan + N × SPD Belakang.
  - Tahap 2 (`post`) & Bundel (`all`): + Kwitansi Utama (+ Daftar Nominatif jika >1) + N × Rincian Biaya & SPD Rampung + Laporan Hasil.
- [ ] Konversi/penautan ke `Kuitansi` SPJ menjaga invarian `nominal_bruto`, mengunci mutasi pasca-submit, dan tidak memutus rekap `SppdReportService`.
- [ ] `composer check:task` lulus 100% (0 error Pint, seluruh test hijau).
