# Spec: Rekening SPPD Kecamatan untuk Unit Kelurahan

> **Label:** `ready-for-agent`  
> **Konteks:** Lanjutan dari `SPEC-sppd-generator`

---

## Problem Statement

Saat ini seluruh 5 Kelurahan di bawah Kecamatan Tarogong Kidul hanya memiliki kode rekening belanja Hibah pada DPA unit kerjanya masing-masing, sedangkan belanja perjalanan dinas (`5.1.02.04.*` / kategori `B.4`) berada pada DPA Kecamatan Tarogong Kidul. Akibatnya, ketika operator atau BPP Kelurahan membuat penugasan SPPD dan hendak mencetak SPD Depan (Butir 9b Mata Anggaran) atau mengonversi penugasan tersebut menjadi Kuitansi SPJ, sistem menolak pilihan rekening SPPD milik Kecamatan dan tidak menampilkan opsi rekening perjalanan dinas pada antarmuka Kelurahan.

---

## Solution

Memperbolehkan unit kerja bertipe Kelurahan untuk menggunakan rekening perjalanan dinas (`B.4` / `5.1.02.04.*`) milik unit induk Kecamatan dalam siklus SPPD dan konversi Kuitansi SPJ, dengan tetap mempertahankan kepemilikan `unit_kerja_id` Kuitansi pada Kelurahan pembuat. Dengan demikian:
- Pencadangan dan realisasi pagu anggaran (`reserve`, `release`, `settle`) tetap memotong DPA Kecamatan secara akurat.
- Alur persetujuan dan verifikasi Kuitansi SPJ tetap berjalan di unit Kelurahan (**BPP Kelurahan → PPTK → Lurah → Verifikator**).
- Dokumen PDF SPD Depan (Butir 9a & 9b) dan Kwitansi secara otomatis mencantumkan kode rekening Kecamatan beserta keterangan pembebanan instansi yang sesuai.

---

## User Stories

1. As a BPP Kelurahan, I want to see and select Kecamatan's `5.1.02.04.*` (`B.4`) travel budget accounts when creating or updating an SPPD assignment, so that Butir 9b (*Mata Anggaran*) on the pre-departure SPD PDF shows the valid budget code.
2. As a BPP Kelurahan, I want to convert a Kelurahan SPPD assignment into a `Kuitansi` SPJ using Kecamatan's SPPD budget account, so that I am not blocked by the absence of `5.1.02.04.*` accounts in the Kelurahan's own DPA.
3. As a BPP Kelurahan, I want the converted `Kuitansi` to remain owned by my Kelurahan's unit kerja, so that I can view, edit, attach evidence, and submit it from my own Kelurahan workspace.
4. As a Lurah, I want SPPD `Kuitansi` records submitted by my Kelurahan's BPP using Kecamatan's SPPD account to still route through my Kelurahan approval queue before reaching Verifikator, so that internal Kelurahan oversight is preserved.
5. As a Bendahara Pengeluaran (BP) or Verifikator Kecamatan, I want Kelurahan SPPD receipts to reserve and deduct pagu from Kecamatan's `5.1.02.04.*` monthly allocation (RAK), so that Kecamatan's travel budget balance always reflects true realization across Kecamatan and all 5 Kelurahan.
6. As a BPP Kelurahan, I want to remain strictly blocked from selecting Kecamatan's non-SPPD accounts (such as ATK, Mamin, Honorarium, Pemeliharaan, or Modal), so that cross-unit budget access is narrowly scoped to SPPD (`B.4`) only.
7. As a BPP Kelurahan A, I want to remain strictly blocked from selecting accounts belonging to Kelurahan B, so that peer-kelurahan isolation is never weakened.
8. As a Verifikator or Auditor, I want the generated SPD Depan PDF (Butir 9a Instansi) to clearly indicate when a Kelurahan SPPD is charged to Kecamatan's budget (`Kecamatan Tarogong Kidul (qq. Kelurahan ...)`), so that printed physical documents match the budget ledger.

---

## Implementation Decisions

- **Otorisasi Rekening Lintas Unit Khusus SPPD:**
  - Kebijakan otorisasi input rekening pada entitas pengguna diperluas khusus untuk peran operasional (`BPP` / `BP`) di unit bertipe `KELURAHAN`: mereka diizinkan menggunakan rekening dari unit bertipe `KECAMATAN` **hanya jika** rekening tersebut tergolong rekening perjalanan dinas (`B.4` / awalan kode `5.1.02.04`).
  - Rekening non-SPPD milik Kecamatan maupun seluruh rekening milik Kelurahan lain tetap ditolak (HTTP 403 / 422).
- **Pemisahan Kepemilikan Unit Kuitansi vs Sumber Pagu Rekening:**
  - Layanan pembuatan draf Kuitansi menerima parameter opsional kepemilikan unit kerja; apabila tidak diisi, tetap menggunakan unit kerja milik rekening (mempertahankan perilaku existing).
  - Saat konversi penugasan SPPD Kelurahan menjadi Kuitansi SPJ, Kuitansi yang terbentuk mencatat `unit_kerja_id` milik **Kelurahan** penugas, sedangkan `anggaran_rekening_id` menunjuk ke rekening **Kecamatan**.
  - Alur persetujuan (termasuk kewajiban persetujuan Lurah untuk unit Kelurahan) tetap membaca `unit_kerja_id` pada Kuitansi, sementara layanan reservasi pagu tetap mengunci dan memotong pagu pada `anggaran_rekening_id` Kecamatan.
- **Guard Konversi & Penautan SPPD:**
  - Layanan konversi dan penautan SPPD mengizinkan rekening `B.4` milik unit sendiri **atau** milik unit `KECAMATAN` apabila penugasan berasal dari unit `KELURAHAN`.
  - Penautan ke Kuitansi existing tetap mewajibkan kesamaan `unit_kerja_id` antara penugasan SPPD dan Kuitansi tersebut.
- **Penyediaan Opsi Rekening SPPD untuk Antarmuka Kelurahan:**
  - Endpoint pengambilan daftar subkegiatan/rekening anggaran mendukung filter/opsi khusus SPPD agar saat dipanggil oleh unit `KELURAHAN`, daftar rekening `B.4` milik `KECAMATAN` ikut dikembalikan dan dapat dipilih pada form penugasan maupun modal konversi Kuitansi SPJ.
- **Penyesuaian Tampilan PDF Pembebanan Anggaran:**
  - Pada template PDF SPD Depan (Butir 9a Instansi) dan Kwitansi, apabila unit rekening sumber dana berbeda dengan unit kerja penugasan (yaitu Kelurahan memakai rekening Kecamatan), instansi pembebanan anggaran ditampilkan sebagai `Kecamatan Tarogong Kidul (qq. <Nama Kelurahan>)`, sementara kop surat dan pejabat penandatangan tetap mengikuti unit Kelurahan pembuat tugas.

---

## Testing Decisions

- **Prinsip Pengujian:** Menguji perilaku eksternal melalui HTTP endpoint API, layanan workflow, dan output PDF/HTML tanpa menguji detail implementasi privat.
- **Modul & Seam yang Diuji:**
  1. **Seam Konversi & Workflow SPPD Kelurahan (`Feature`):** Skenario BPP Kelurahan membuat penugasan SPPD, mengonversinya memakai rekening `B.4` milik Kecamatan, memverifikasi bahwa `Kuitansi.unit_kerja_id` adalah Kelurahan, pagu Kecamatan terpotong saat diajukan, dan Kuitansi wajib melewati persetujuan Lurah Kelurahan sebelum ke Verifikator.
  2. **Seam Keamanan & Isolasi (`Feature`):** BPP Kelurahan ditolak saat mencoba memakai rekening non-SPPD (`B.1`/`B.2`) milik Kecamatan atau rekening milik Kelurahan lain.
  3. **Seam Rendering PDF (`Feature`):** PDF SPD Depan untuk penugasan Kelurahan dengan rekening Kecamatan menampilkan kode rekening Kecamatan dan keterangan instansi `qq. Kelurahan ...`.
- **Prior Art:** Mengacu pada pola pengujian di `SppdKuitansiIntegrationTest`, `KelurahanLurahWorkflowTest`, dan `SppdPdfGenerationTest`.

---

## Out of Scope

- Penambahan/perubahan data DPA Kelurahan di luar modul SPPD (seperti membolehkan Kelurahan memakai rekening ATK atau Mamin milik Kecamatan).
- Perubahan formula perhitungan pajak, ambang Bea Meterai, atau logika carry-over pada layanan reservasi anggaran.
- Pembuatan struktur hierarki SKPD multi-kecamatan baru (diasumsikan satu SKPD induk `KECAMATAN` dalam database).

---

## Further Notes

- Pastikan seluruh pengujian regresi existing (`KelurahanLurahWorkflowTest`, `ReceiptApiTest`, `BudgetReservationTest`) tetap lulus 100% setelah perubahan pada otorisasi rekening dan pembuatan draf Kuitansi.
