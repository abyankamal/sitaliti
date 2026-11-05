# Rencana Implementasi Frontend SI-TALITI v2.0
## Panduan Developer 2 — Frontend & Fullstack UI Lead dengan Antigravity AI

**Basis analisis:** `Brief_Kerja_Tim_SI_TALITI_v2.docx`  
**Stack yang ditetapkan:** Laravel 12, Inertia.js, Vue 3, Tailwind CSS, MySQL 8.0  
**Repository:** `sitaliti-app`  
**Target:** 8 minggu / 4 sprint  
**Fokus dokumen ini:** pekerjaan frontend (Developer 2)

---

# 1. Ringkasan dan pemahaman proyek

SI-TALITI adalah *Pre-Audit Quality Assurance Gateway* untuk membantu tata kelola, asistensi, dan verifikasi akuntabilitas SPJ sebelum dokumen diajukan ke SIPD-RI. SI-TALITI **bukan pengganti SIPD-RI**, tidak menerbitkan SP2D, dan tidak memproses pencairan kas resmi. Antarmuka harus membantu pengguna menyiapkan, memeriksa, memperbaiki, mengesahkan, dan mengarsipkan dokumen pertanggungjawaban.

Sistem memiliki tiga prinsip arsitektur yang perlu tercermin pada UI:

1. **Pre-Audit Clearing House:** antarmuka menampilkan status kelengkapan kuitansi, kepatuhan SSH/SBM, pajak, dan hasil verifikasi. UI tidak boleh memberi kesan bahwa aplikasi memproses pencairan resmi.
2. **Two-Tier Settlement Model:** bedakan dengan jelas kuitansi/bukti transaksi atomik dan bundel pengesahan (SPP-GU, SPP-TU, SPP-LS, LPJ BPP). Jangan mencampur halaman transaksi individual dengan halaman bundel.
3. **Real-Time Budget Locking dengan Carry-Over RAK:** tampilkan alokasi bulan terpilih, sisa pagu bulan sebelumnya yang dapat dibawa, pagu yang telah digunakan/terkunci, serta batas bahwa pagu bulan mendatang tidak boleh ditarik.

Data acuan dokumen:
- Total pagu: **Rp14.252.126.458**
- Struktur: **6 unit organisasi, 39 subkegiatan, 132 rekening**
- Pilot: Kecamatan Tarogong Kidul dan kelurahan
- Dataset anggaran: RAK Perubahan 2026 Kecamatan Tarogong Kidul

Angka dan data tersebut harus berasal dari backend/seeder resmi ketika integrasi dilakukan. Jangan menanam angka ringkasan sebagai data permanen di komponen UI.

---

# 2. Ruang lingkup tanggung jawab frontend

Berdasarkan pembagian tugas Developer 2 pada dokumen, ruang lingkup utamanya adalah:

| Modul | Tanggung jawab frontend | Hasil yang harus terlihat |
|---|---|---|
| Design System & Layout | Tema Deep Gov Navy, layout, navigasi, komponen umum, tabel angka `tabular-nums` | Fondasi UI konsisten |
| Pre-Entry Gatekeeper | Modal pemilihan unit/subkegiatan/rekening dan RAK 12 bulan | Pengguna memilih konteks anggaran sebelum input |
| Form Kuitansi | Form transaksi atomik dan unggah lampiran terstruktur | Data transaksi dan dokumen dapat dimasukkan |
| Split-Screen Verification | PDF viewer 55%, checklist 45% | Pemeriksa melihat bukti dan checklist bersamaan |
| Dynamic Checklist | Checklist B.1–B.6 sesuai kategori belanja | Dokumen wajib dan aturan kategori ditampilkan dinamis |
| Loopback State Machine | Tampilan status, revisi minor/mayor, alur pengembalian | Pengguna memahami siapa yang harus bertindak berikutnya |
| QR Output | Tampilan/cetakan Lembar Kendali Verifikasi ber-QR | Hasil verifikasi dapat dicetak/diperiksa |

## 2.1 Batas tanggung jawab frontend

Frontend bertanggung jawab untuk:
- Mengumpulkan input pengguna dan melakukan validasi pengalaman pengguna (misalnya required field, format, ukuran file).
- Menampilkan hasil perhitungan pajak dan alokasi pagu yang diberikan backend.
- Menampilkan status workflow dan menyediakan aksi yang sesuai dengan hak akses/status.
- Menampilkan dokumen, checklist, riwayat revisi, notifikasi, dan hasil verifikasi.
- Mengirim data melalui form Inertia atau endpoint sesuai kontrak yang disepakati.

Frontend **tidak boleh menjadi sumber kebenaran** untuk:
- Perhitungan final pajak.
- Penguncian pagu dan carry-over RAK.
- Pencegahan transaksi melebihi pagu.
- Penentuan final lolos/tidak lolos verifikasi.
- Hashing SHA-256 dan deteksi duplikasi.
- Otorisasi pengguna dan transisi status yang sah.
- Pembuatan QR token resmi atau watermark PDF final.

Hal-hal tersebut harus diputuskan backend. Frontend boleh menampilkan simulasi/preview, tetapi hasil final harus mengikuti respons server.

---

# 3. Rekomendasi urutan pengerjaan

**Jangan langsung meminta Antigravity membuat semua modul sekaligus.** Mulailah dengan fondasi, kontrak UI, data mock, dan satu alur vertikal yang dapat diuji. Setelah itu baru perluas modul.

Urutan prioritas yang disarankan:

1. Pastikan lingkungan pengembangan dan repository berjalan.
2. Sepakati kontrak data frontend-backend dan daftar status workflow.
3. Bangun design system, layout, navigasi, dan komponen dasar.
4. Buat mock data yang mewakili 6 unit, 39 subkegiatan, 132 rekening, RAK 12 bulan, dan kategori B.1–B.6.
5. Bangun dashboard dan tabel anggaran.
6. Bangun Gatekeeper RAK Selector.
7. Bangun form kuitansi dan upload lampiran.
8. Bangun dynamic checklist per kategori.
9. Bangun split-screen verification dan alur revisi.
10. Bangun bundel SPJ, Lembar Kendali, QR, dan penyempurnaan responsif.
11. Integrasikan endpoint backend, uji alur end-to-end, dan rapikan aksesibilitas.

Alasan urutan ini: Gatekeeper dan form kuitansi membutuhkan struktur anggaran; checklist bergantung pada kategori rekening; verification workspace membutuhkan data kuitansi, lampiran, checklist, dan status workflow.

---

# 4. Persiapan sebelum menggunakan Antigravity AI

## 4.1 Persiapan perangkat dan repository

Siapkan:
- Git dan akses repository `sitaliti-app`.
- PHP versi yang kompatibel dengan Laravel 12, Composer.
- Node.js versi LTS dan npm.
- MySQL 8.0.
- Antigravity AI dengan akses ke folder proyek.
- Browser untuk pengujian.
- Dokumen spesifikasi SI-TALITI dan, jika tersedia, file RAK Excel serta Form Pembinaan/Sheet TARKID.

Periksa apakah repository benar-benar kosong atau sudah memiliki file awal. Jangan menimpa konfigurasi yang sudah ada tanpa memeriksa isinya.

## 4.2 Persiapan keputusan produk yang belum dijelaskan lengkap

Dokumen memberi arah fungsional yang kuat, tetapi belum merinci beberapa keputusan berikut. Konfirmasikan kepada tim/backend atau pemilik proses sebelum mengunci implementasi:

- Daftar role lengkap dan matriks izin tiap role (operator, PPTK, BPP, verifikatur, PPK-SKPD, admin, atau role lain yang disepakati).
- Peta halaman yang boleh diakses setiap role.
- Detail field lengkap form kuitansi dan format validasi setiap field.
- Format, ukuran maksimum, jumlah, dan tipe file lampiran.
- Definisi data checklist per kategori, termasuk apakah ada dokumen opsional dan kondisi pemicu.
- Detail revisi minor vs mayor: siapa yang dapat mengembalikan, siapa yang memperbaiki, dan status tujuan.
- Daftar status serta transisi yang diizinkan pada setiap peran.
- Apakah PDF viewer memakai file lokal, URL privat, atau signed URL.
- Format cetak Lembar Kendali, ukuran kertas, identitas instansi, dan isi QR.
- Grafik RAK yang dibutuhkan dan definisi metriknya.
- Mekanisme notifikasi dan indikator SLA 2 x 24 jam.
- Kontrak endpoint, bentuk payload, bentuk error, pagination, dan autentikasi dari backend.

Jangan meminta AI menebak keputusan tersebut. Buat placeholder yang jelas dan catat sebagai pertanyaan terbuka.

## 4.3 Siapkan kontrak data bersama

Dokumen menetapkan dua interface backend yang perlu dipakai frontend:

### BudgetReservationServiceInterface

- `getPopupAllocationData(unitKerjaId, tahun, bulanDipilih)` — data alokasi untuk popup.
- `reserveBudget(anggaranRekeningId, bulan, nominalBruto)` — backend mengunci pagu ketika kuitansi disubmit ke PPTK.
- `releaseBudget(anggaranRekeningId, bulan, nominalBruto)` — backend melepas kuncian sesuai aturan.

### TaxCalculationServiceInterface

- `calculateTax(kategoriChecklist, nilaiBruto, options)` — simulasi rincian pajak dan nilai neto.
- `validateTaxCompliance(nilaiBruto, taxDetails)` — validasi kepatuhan pajak/meterai/NTPN.

Frontend membutuhkan bentuk JSON hasil pemanggilan service tersebut. Nama method PHP bukan otomatis URL API; developer backend perlu menyediakan controller/route atau Inertia props yang menjadi adapter kontrak UI.

### Contoh bentuk data mock (usulan frontend, bukan kontrak resmi)

```json
{
  "unit": {
    "id": "unit-demo-001",
    "kode": "7.01.0.00.0.00.41.0000",
    "nama": "Kecamatan Tarogong Kidul"
  },
  "tahun": 2026,
  "bulanDipilih": 3,
  "rekening": {
    "id": "rekening-demo-001",
    "kode": "5.1.02.01.001.00052",
    "nama": "Belanja Makan dan Minum Rapat",
    "kategoriChecklist": "B2_MAMIN"
  },
  "alokasi": {
    "paguBulan": 25000000,
    "terkunciBulan": 5000000,
    "realisasiBulan": 3000000,
    "carryOverTersedia": 2000000,
    "sisaTersedia": 19000000
  }
}
```

Nilai di atas hanya ilustrasi untuk mengembangkan komponen. Jangan menganggapnya sebagai data RAK resmi atau rumus final carry-over. Minta backend menetapkan definisi dan nama field final.

---

# 5. Rancangan struktur UI dan navigasi

Berikut rancangan awal yang dapat digunakan untuk memulai. Ini adalah usulan frontend dan perlu diselaraskan dengan role serta alur bisnis final.

## 5.1 Navigasi utama

- Dashboard
- Anggaran & RAK
- Kuitansi
  - Daftar Kuitansi
  - Input Kuitansi
  - Detail Kuitansi
- Verifikasi
  - Antrean Verifikasi
  - Workspace Pemeriksaan
- Bundel SPJ
  - Daftar Bundel
  - Detail Bundel
  - Checklist Bagian A
- Lembar Kendali
- Riwayat Aktivitas
- Pengaturan (sesuai hak akses)

Jangan tampilkan seluruh menu kepada semua role sebelum matriks akses disepakati. Navigasi harus mengikuti izin dari backend, bukan sekadar menyembunyikan tombol di browser.

## 5.2 Halaman yang perlu dibuat

| Prioritas | Halaman/komponen | Isi utama |
|---|---|---|
| P0 | App Layout | Sidebar, topbar, breadcrumb, area konten |
| P0 | Dashboard | Ringkasan pagu, realisasi, transaksi, antrean |
| P0 | Tabel anggaran | Unit, subkegiatan, rekening, pagu dan status |
| P1 | Gatekeeper RAK | Pemilihan unit, tahun, bulan, rekening |
| P1 | Form kuitansi | Identitas transaksi, nominal, pajak preview, lampiran |
| P1 | Detail kuitansi | Data transaksi, dokumen, status, timeline |
| P1 | Dynamic checklist | Checklist B.1–B.6 |
| P2 | Verification workspace | PDF viewer 55%, checklist 45%, aksi pemeriksaan |
| P2 | Revisi | Catatan revisi, kategori minor/mayor, unggah perbaikan |
| P3 | Bundel SPJ | Pengelompokan kuitansi dan checklist Bagian A |
| P3 | Lembar Kendali | Preview/cetak dokumen dengan QR |
| P3 | Grafik RAK | Visualisasi alokasi dan realisasi |

## 5.3 Prinsip visual

Dokumen menetapkan tema **Deep Gov Navy** dan penggunaan angka `tabular-nums`. Rekomendasi penerapan:

- Navy gelap untuk navigasi dan elemen identitas utama.
- Latar konten netral agar tabel dan dokumen mudah dibaca.
- Status memakai label teks yang konsisten; jangan hanya mengandalkan warna.
- Nominal rupiah menggunakan format lokal Indonesia dan angka rata kanan.
- Gunakan `font-variant-numeric: tabular-nums` untuk angka tabel.
- Form panjang dibagi ke kelompok yang jelas.
- Tampilkan loading, empty state, error, success, dan disabled state.
- Konfirmasi sebelum tindakan yang mengubah status atau mengirim transaksi.
- Desain desktop-first untuk workspace pemeriksaan, tetapi tetap responsif untuk halaman lain.

Hindari menentukan warna final, logo, tipografi resmi, atau identitas instansi tanpa panduan brand dari pemilik proyek.

---

# 6. Rencana implementasi per sprint

## Sprint 1 — Minggu 1–2: Setup fondasi dan dashboard

**Target dokumen:** template admin, Tailwind tema Deep Gov Navy, layout dashboard, komponen tabel angka `tabular-nums`. Fondasi backend pada sprint ini meliputi migration, seeder unit, dan RAK importer.

### Pekerjaan frontend
1. Audit repository dan lingkungan.
2. Pastikan Laravel 12 + Inertia + Vue 3 terpasang/berjalan sesuai rancangan.
3. Siapkan Tailwind dan token tema.
4. Buat AppLayout, sidebar, topbar, breadcrumb.
5. Buat komponen Button, Input, Select, Modal, Badge, Card, Table, Alert, EmptyState, LoadingState.
6. Buat dashboard menggunakan mock data.
7. Buat tabel unit, subkegiatan, dan rekening dengan pencarian/filter dasar.
8. Siapkan formatter rupiah dan tanggal.
9. Buat data mock terpisah dari komponen UI.

### Definition of Done
- Aplikasi dapat dijalankan lokal.
- Layout konsisten dan responsif.
- Dashboard dan tabel dapat dirender dari mock data.
- Angka rupiah terbaca rapi.
- Tidak ada data bisnis penting yang tersebar sebagai hardcode di banyak komponen.

## Sprint 2 — Minggu 3–4: Gatekeeper, kuitansi, dan checklist

**Target dokumen:** popup RAK Selector, form kuitansi, upload lampiran; backend menyiapkan budget reservation dan kalkulator pajak.

### Pekerjaan frontend
1. Bangun modal Gatekeeper.
2. Alur pilihan: unit → tahun → bulan → subkegiatan/rekening.
3. Tampilkan alokasi 12 bulan dan informasi carry-over dari data backend.
4. Tampilkan indikator pagu tersedia, terpakai, terkunci, dan batas bulan.
5. Bangun form kuitansi atomik.
6. Buat komponen upload dokumen dengan daftar file, progress, remove, dan validasi.
7. Buat preview pajak dari hasil backend; jangan jadikan kalkulasi browser sebagai nilai final.
8. Buat konfigurasi checklist B.1–B.6.
9. Tampilkan dokumen wajib berdasarkan kategori rekening.
10. Integrasikan submit dan tampilkan error validasi server.

### Checklist kategori dari dokumen
- **B.1 ATK/Cetak/Penggandaan:** rincian objek, surat pesanan, BAST bila e-purchasing, nota toko, kuitansi bendahara, bukti transfer IBC, pajak sesuai ketentuan, foto barang. Aturan yang disebut: BAST wajib jika e-purchasing; PPN dan PPh 22 jika nominal > Rp2.000.000; meterai jika > Rp5.000.000.
- **B.2 Makan/Minum Rapat:** rincian objek, surat pesanan, undangan, daftar hadir, nota toko, kuitansi, bukti transfer IBC, pajak daerah/PPh 23, foto kegiatan, foto snack dan nasi box, notulensi. Tampilkan dua slot foto terpisah.
- **B.3 Honorarium/Jasa:** rincian objek, dasar pembayaran (SK Camat/SPT), daftar penerima honor, kuitansi, bukti transfer IBC, bukti potong PPh 21. Aturan tarif dalam dokumen perlu diverifikasi dan disediakan backend sebagai hasil resmi.
- **B.4 Perjalanan Dinas:** rincian objek, dasar SPD, surat tugas, lembar SPD terstempel, kuitansi, rincian rampung, laporan hasil, dokumentasi, bukti transfer. Aturan pagu harian/transport mengikuti SBM/SSH Kabupaten Garut.
- **B.5 Hibah Barang/Sarpras Kelurahan:** rincian objek, surat pesanan, BAST hibah, kuitansi, nota pembelian, pajak, dokumentasi fisik 0% dan 100%.
- **B.6 Kegiatan/Sosialisasi Lainnya:** rincian objek, surat undangan, daftar hadir, laporan hasil kegiatan, kuitansi, dokumentasi kegiatan, surat pesanan.

Catatan: daftar di atas mengikuti isi dokumen. Rincian kondisi wajib/opsional dan aturan pajak perlu difinalisasi oleh pemilik proses/backend; jangan mengubah aturan kebijakan sendiri.

### Definition of Done
- Pengguna dapat memilih konteks anggaran dari mock/API.
- Form kuitansi menampilkan rekening dan kategori terpilih.
- Lampiran dapat dipilih dan ditampilkan sebelum dikirim.
- Checklist berubah sesuai kategori.
- Error backend dapat ditampilkan dekat field atau sebagai notifikasi yang jelas.

## Sprint 3 — Minggu 5–6: Verification Workspace dan loopback

**Target dokumen:** split-screen 55% PDF viewer dan 45% checklist, revisi minor/mayor, indikator SLA 2 x 24 jam; backend menyiapkan audit trail, hash duplikasi, dan API verifikasi.

### Pekerjaan frontend
1. Buat halaman antrean verifikasi dengan filter status.
2. Bangun workspace dua panel.
3. Panel kiri: PDF viewer, navigasi halaman/zoom jika didukung library, informasi nama dokumen.
4. Panel kanan: checklist per dokumen dan status pemeriksaan.
5. Tampilkan detail transaksi dan metadata di area yang tidak mengganggu pembacaan dokumen.
6. Sediakan aksi sesuai role/status: setujui, kembalikan, atau tindakan lain yang ditentukan backend.
7. Buat dialog revisi yang meminta alasan/catatan.
8. Bedakan revisi minor dan mayor sesuai aturan final.
9. Tampilkan timeline status dan riwayat komentar.
10. Tampilkan indikator SLA 2 x 24 jam berdasarkan timestamp server.

### Catatan UX penting
- Rasio 55:45 adalah acuan dokumen; sediakan pengaturan lebar panel atau layout adaptif bila memungkinkan.
- Pada layar kecil, ubah menjadi tab “Dokumen” dan “Checklist” atau susunan vertikal.
- Jangan menyimpulkan dokumen lolos hanya karena semua checkbox dicentang di browser. Backend tetap memvalidasi.
- Tindakan verifikasi harus menampilkan konfirmasi dan hasil server.
- Pertahankan catatan revisi dan status sebelumnya agar proses dapat ditelusuri.

### Definition of Done
- Pemeriksa dapat membuka dokumen dan checklist pada satu workspace.
- Checklist dapat diisi dan dikirim.
- Status dan riwayat diperbarui dari respons backend.
- Revisi minor/mayor memiliki UI dan catatan yang jelas.
- Workspace dapat digunakan pada resolusi desktop dan memiliki fallback mobile.

## Sprint 4 — Minggu 7–8: Bundel, Lembar Kendali, dan pilot

**Target dokumen:** PDF Lembar Kendali Verifikasi ber-QR, grafik RAK, penyempurnaan UI responsif. Backend menyiapkan batching/unbundle dan pencatatan nomor SIPD-RI.

### Pekerjaan frontend
1. Buat daftar bundel SPJ.
2. Buat detail bundel dan daftar kuitansi yang tergabung.
3. Buat tampilan checklist Bagian A untuk 9 dokumen umum:
   - Nota Pencairan Dana (NPD)
   - Lembar Pengiriman SPJ
   - Berita Acara Pemeriksaan Kas
   - Register Penutupan Kas
   - Laporan Pengeluaran Kas
   - SPJ Fungsional
   - Buku Kas Umum (BKU)
   - Buku Pembantu Pajak
   - Rekening Koran
4. Tampilkan status item kuitansi dalam bundel dan kondisi dikeluarkan/unbundle sesuai respons backend.
5. Buat preview/cetak Lembar Kendali Verifikasi.
6. Tampilkan QR dari token/URL resmi yang disediakan backend.
7. Buat grafik RAK dengan definisi metrik yang disepakati.
8. Tambahkan pencatatan/penampilan nomor SIPD-RI sesuai data backend.
9. Uji alur demo dari pemilihan rekening sampai keluaran Lembar Kendali.
10. Perbaiki aksesibilitas, responsivitas, loading, error, dan empty states.

### Definition of Done
- Pengguna dapat melihat bundel dan kuitansi di dalamnya.
- Checklist Bagian A tersedia.
- Lembar Kendali dapat dipreview dan dicetak.
- QR mengarah ke token/URL yang disediakan backend, bukan token buatan frontend.
- Grafik menggunakan data backend.
- Skenario pilot dapat didemokan dari awal sampai akhir.

---

# 7. Arsitektur frontend yang disarankan

Struktur berikut adalah usulan organisasi kode Vue/Inertia, bukan struktur yang diwajibkan secara eksplisit oleh dokumen. Sesuaikan dengan starter kit dan konvensi repository yang dipilih.

```text
resources/
├── js/
│   ├── app.js
│   ├── Pages/
│   │   ├── Dashboard/
│   │   ├── Anggaran/
│   │   ├── Kuitansi/
│   │   ├── Verifikasi/
│   │   ├── Bundel/
│   │   └── LembarKendali/
│   ├── Components/
│   │   ├── UI/
│   │   ├── Forms/
│   │   ├── Tables/
│   │   ├── Upload/
│   │   ├── Budget/
│   │   ├── Checklist/
│   │   └── Verification/
│   ├── Layouts/
│   ├── Composables/
│   ├── Services/
│   ├── Constants/
│   ├── Utils/
│   └── Mocks/
│       ├── units.js
│       ├── budgets.js
│       ├── receipts.js
│       └── checklists.js
└── css/
    └── app.css
```

Prinsip pemisahan:
- **Pages:** komposisi halaman dan penghubung props.
- **Components:** komponen UI yang dapat digunakan ulang.
- **Composables:** state dan perilaku reusable (misalnya filter, upload, workflow).
- **Services:** adapter komunikasi ke route/API.
- **Constants:** label status, opsi kategori, konfigurasi UI.
- **Utils:** format rupiah/tanggal dan helper presentasi.
- **Mocks:** data simulasi untuk pengembangan sebelum API siap.

Hindari satu file Vue raksasa yang berisi seluruh dashboard, form, modal, checklist, dan workflow.

---

# 8. Kontrak state dan perilaku UI yang perlu disiapkan

## 8.1 Status kuitansi

Dokumen menyebut status:
- `DRAFT`
- `MENUNGGU_PPTK`
- `DIKEMBALIKAN_PPTK`
- `MENUNGGU_BPP`
- `DIKEMBALIKAN_BPP`
- `MENUNGGU_VERIFIKATUR`
- `DIKEMBALIKAN_VERIFIKATUR`
- `LOLOS_VERIFIKASI`
- `TERBUNDEL`
- `ARSIP_PERMANEN`

Frontend perlu satu konfigurasi status terpusat untuk label, deskripsi, dan gaya visual. Jangan menyebarkan string status literal di banyak halaman.

## 8.2 Status bundel

Dokumen menyebut:
- `DRAFT`
- `MENUNGGU_PPK`
- `DISETUJUI_PPK`
- `SYNCED_SIPD`

Status kuitansi dan bundel adalah dua domain berbeda. Jangan memakai satu enum UI untuk keduanya.

## 8.3 Aturan state machine

Buat diagram transisi bersama backend sebelum mengimplementasikan tombol aksi. Untuk setiap transisi tentukan:
- status asal;
- status tujuan;
- role yang berwenang;
- prasyarat;
- payload (misalnya catatan revisi);
- respons sukses;
- kemungkinan error;
- apakah lampiran baru diwajibkan.

Jangan mengasumsikan semua status dapat berpindah ke semua status lain.

---

# 9. Strategi penggunaan Antigravity AI

Antigravity sebaiknya diperlakukan sebagai **pair programmer/agent yang mengerjakan tugas kecil dengan batasan eksplisit**, bukan sebagai pengganti keputusan arsitektur dan pemilik proses.

## 9.1 Cara kerja yang direkomendasikan

Untuk setiap pekerjaan:
1. Berikan konteks proyek dan file spesifikasi.
2. Minta AI memeriksa repository sebelum mengubah apa pun.
3. Minta rencana file yang akan dibuat/diubah.
4. Batasi satu tahap atau satu modul per prompt.
5. Minta AI menjelaskan asumsi dan pertanyaan terbuka.
6. Minta implementasi mengikuti stack yang sudah ditetapkan.
7. Minta jalankan lint/build/test yang tersedia.
8. Periksa diff dan jalankan aplikasi sendiri.
9. Commit pekerjaan yang stabil ke feature branch.
10. Catat kontrak API dan pekerjaan yang masih menunggu backend.

## 9.2 Aturan penting untuk agent

- Jangan mengganti Laravel 12, Inertia.js, Vue 3, Tailwind CSS, atau MySQL 8.0 tanpa persetujuan.
- Jangan mengubah file migrasi lama yang sudah di-merge; bila perlu perubahan skema, koordinasikan dan buat migration baru.
- Jangan membuat backend palsu yang diam-diam dianggap final.
- Mock data harus diberi label dan dipisahkan.
- Jangan mengarang endpoint, role, aturan pajak, aturan carry-over, atau transisi status.
- Jangan menaruh business logic ke komponen presentasi.
- Jangan menghapus file/config yang belum dipahami.
- Jangan melakukan commit/push atau operasi destruktif tanpa instruksi eksplisit.
- Setelah setiap tahap, laporkan file yang berubah, perintah yang dijalankan, hasilnya, dan hal yang belum selesai.

---

# 10. Prompt Antigravity AI dari project kosong

Gunakan prompt secara bertahap. **Mulai dari Prompt 0**, tunggu agent selesai menganalisis, lalu lanjutkan ke prompt berikutnya. Jangan mengirim seluruh prompt sekaligus.

## PROMPT 0 — Inisialisasi dan pemahaman proyek

```text
Anda bertindak sebagai Senior Frontend Engineer, UI Architect, dan pair programmer untuk proyek SI-TALITI v2.0.

Saya akan mengembangkan sisi frontend aplikasi SI-TALITI (Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ). Repository saat ini masih kosong atau belum memiliki implementasi aplikasi.

Baca dan jadikan dokumen spesifikasi SI-TALITI v2.0 yang tersedia di workspace sebagai sumber kebutuhan utama. Jangan mengarang aturan bisnis yang tidak tertulis di dokumen.

STACK WAJIB:
- Laravel 12
- Inertia.js
- Vue 3
- Tailwind CSS
- MySQL 8.0
- Git repository: sitaliti-app

PERAN SAYA:
Saya mengerjakan Developer 2 — Frontend & Fullstack UI Lead. Fokus pekerjaan:
1. Design System dan layout tema Deep Gov Navy.
2. Pre-Entry Gatekeeper Modal untuk pemilihan unit, subkegiatan, rekening, dan RAK 12 bulan.
3. Form input kuitansi atomik dan upload dokumen terstruktur.
4. Split-Screen Verification Workspace: PDF viewer 55% dan checklist 45%.
5. Dynamic Checklist Engine kategori B.1 sampai B.6.
6. UI workflow revisi minor/mayor dan status verifikasi.
7. UI/cetakan Lembar Kendali Verifikasi dengan QR Code.
8. Dashboard, tabel anggaran, grafik RAK, dan responsivitas.

PRINSIP ARSITEKTUR:
- SI-TALITI adalah pre-audit gateway, bukan pengganti SIPD-RI.
- Pisahkan kuitansi transaksi atomik dari bundel pengesahan SPJ.
- RAK menggunakan alokasi bulanan dan carry-over sesuai keputusan backend.
- Backend adalah sumber kebenaran untuk pajak, penguncian pagu, otorisasi, status final, hash duplikasi, dan QR token.
- Frontend dikembangkan secara contract-first dengan mock JSON agar tidak menunggu backend.
- Target proyek 8 minggu dalam 4 sprint.

ATURAN KERJA:
1. Sebelum mengubah file, periksa isi workspace dan kondisi repository.
2. Jangan langsung membuat seluruh aplikasi dalam satu langkah.
3. Jangan menghapus atau menimpa konfigurasi tanpa alasan dan persetujuan.
4. Jangan mengganti stack.
5. Jangan mengarang endpoint API, role, aturan pajak, aturan carry-over, atau transisi workflow.
6. Jika informasi belum tersedia, buat daftar pertanyaan/assumption dan gunakan mock yang diberi label DEMO.
7. Pisahkan Pages, Components, Layouts, Composables, Services, Constants, Utils, dan Mocks secara terstruktur.
8. Gunakan komponen reusable dan hindari file Vue yang terlalu besar.
9. Gunakan Bahasa Indonesia untuk label UI.
10. Setelah setiap tahap, jalankan pemeriksaan yang tersedia dan laporkan hasilnya.
11. Jangan commit, push, menghapus database, atau melakukan tindakan destruktif tanpa instruksi saya.

TUGAS PERTAMA:
Jangan menulis kode dulu.

Lakukan audit workspace dan analisis dokumen spesifikasi. Berikan:
A. Kondisi repository dan file yang tersedia.
B. Rekomendasi langkah setup dari nol.
C. Arsitektur frontend yang akan digunakan.
D. Usulan struktur folder.
E. Daftar halaman dan komponen.
F. Daftar data mock yang diperlukan.
G. Daftar kontrak/informasi yang harus diminta dari backend.
H. Risiko teknis dan urutan implementasi.

Tunggu persetujuan saya sebelum menjalankan setup atau membuat/mengubah file.
```

## PROMPT 1 — Setup fondasi aplikasi

```text
Lanjutkan ke tahap setup fondasi SI-TALITI v2.0 berdasarkan hasil audit yang telah disepakati.

Sebelum bekerja, periksa kembali kondisi workspace. Implementasikan fondasi proyek dengan Laravel 12, Inertia.js, Vue 3, dan Tailwind CSS sesuai kondisi repository. Jika repository benar-benar kosong, lakukan setup dengan cara resmi dan kompatibel. Jika ada starter kit atau konfigurasi yang sudah berjalan, pertahankan dan adaptasikan.

Target:
1. Aplikasi dapat dijalankan secara lokal.
2. Inertia dan Vue 3 berfungsi.
3. Tailwind CSS berfungsi.
4. Buat AppLayout dasar.
5. Buat sidebar, topbar, breadcrumb, dan content container.
6. Siapkan tema Deep Gov Navy melalui design tokens/CSS variables atau pendekatan Tailwind yang sesuai versi proyek.
7. Siapkan struktur folder Pages, Components/UI, Layouts, Composables, Services, Constants, Utils, dan Mocks.
8. Buat halaman dashboard sementara.
9. Gunakan Bahasa Indonesia pada UI.
10. Pastikan angka nominal menggunakan tabular numerals.

Batasan:
- Jangan implementasikan logika pajak, penguncian pagu, atau workflow final.
- Jangan mengarang endpoint.
- Jangan memasukkan data RAK seolah-olah data resmi.
- Jangan membuat semua modul sekaligus.

Sebelum selesai:
- Jalankan build/lint/test yang tersedia.
- Perbaiki error yang muncul.
- Laporkan file yang dibuat/diubah, perintah yang dijalankan, hasil pemeriksaan, dan cara menjalankan aplikasi.
```

## PROMPT 2 — Design system dan komponen reusable

```text
Sekarang bangun Design System SI-TALITI di atas fondasi yang sudah berjalan.

Arah visual:
- Tema Deep Government Navy.
- Tampilan aplikasi administrasi pemerintahan yang profesional, bersih, modern, tenang, dan mudah dibaca.
- Prioritaskan keterbacaan tabel, nominal rupiah, status, dan formulir.
- Jangan berlebihan menggunakan gradient, animasi, atau dekorasi.
- Gunakan warna status yang konsisten dan selalu sertakan label teks.

Buat komponen reusable:
- Button (primary, secondary, outline, danger, loading, disabled)
- TextInput
- Textarea
- Select
- SearchInput
- Modal/Dialog
- Card
- Badge/StatusBadge
- DataTable
- Pagination (jika pola backend sudah diketahui, jika belum buat presentational component)
- Alert/Toast
- ConfirmDialog
- FileUpload
- EmptyState
- LoadingState
- ErrorState
- Breadcrumb

Ketentuan:
1. Gunakan Vue 3 Composition API.
2. Komponen harus memiliki props dan emits yang jelas.
3. Validasi props dan aksesibilitas dasar.
4. Jangan mengikat komponen langsung ke endpoint tertentu.
5. Pisahkan tampilan dari business logic.
6. Gunakan formatter rupiah dan tanggal dari Utils.
7. Gunakan `tabular-nums` untuk nilai keuangan.
8. Buat halaman showcase/dev page untuk memeriksa komponen jika sesuai struktur aplikasi.

Jalankan build dan laporkan hasilnya. Jangan lanjut ke modul bisnis sebelum design system stabil.
```

## PROMPT 3 — Mock data dan dashboard

```text
Bangun data mock frontend dan Dashboard SI-TALITI.

Konteks data dari spesifikasi:
- 6 unit organisasi.
- 39 subkegiatan.
- 132 rekening.
- Total pagu RAK 2026 Rp14.252.126.458.
- Unit induk Kecamatan Tarogong Kidul dan lima kelurahan.
- Data resmi nantinya harus berasal dari seeder/backend.

Buat mock data terpisah dari komponen. Beri penanda yang jelas bahwa data adalah DEMO, bukan data transaksi resmi. Jangan membuat 132 rekening fiktif lalu mengklaimnya sebagai data riil. Jika file RAK resmi belum tersedia, gunakan beberapa record ilustrasi untuk menguji UI dan sediakan struktur agar dapat diganti dengan data backend.

Dashboard menampilkan, bila datanya tersedia:
- Ringkasan pagu.
- Realisasi.
- Sisa anggaran.
- Jumlah kuitansi berdasarkan status.
- Antrean verifikasi.
- Ringkasan unit/subkegiatan.
- Grafik RAK bulanan.

Buat juga tabel anggaran dengan pencarian, filter unit, filter subkegiatan, dan tampilan nominal rata kanan.

Jangan menghitung angka keuangan final sendiri bila rumusnya belum disepakati backend. Untuk mock, gunakan nilai fixture yang eksplisit dan konsisten.

Pastikan loading, empty state, error state, dan tampilan responsif tersedia. Jalankan build.
```

## PROMPT 4 — Gatekeeper RAK Selector

```text
Implementasikan modul Pre-Entry Gatekeeper / RAK Selector sesuai spesifikasi SI-TALITI.

Tujuan:
Sebelum pengguna membuat kuitansi, pengguna memilih konteks anggaran yang sah:
1. Unit kerja.
2. Tahun anggaran.
3. Bulan anggaran.
4. Subkegiatan.
5. Rekening belanja.

UI harus menampilkan:
- Kode dan nama unit.
- Kode dan nama subkegiatan.
- Kode dan nama rekening.
- Kategori checklist rekening.
- Alokasi RAK 12 bulan.
- Pagu bulan terpilih.
- Pagu terkunci/realisasi jika disediakan backend.
- Carry-over yang tersedia jika disediakan backend.
- Sisa yang dapat digunakan berdasarkan hasil backend.
- Indikator jika bulan/rekening tidak dapat dipilih.

Gunakan data mock terlebih dahulu melalui adapter/service yang mudah diganti ke data backend.

PENTING:
- Jangan membuat rumus carry-over sendiri.
- Jangan mengizinkan frontend menjadi penentu akhir apakah pagu cukup.
- Jangan mengasumsikan pagu bulan mendatang boleh digunakan.
- Kontrak backend yang tercantum di spesifikasi adalah getPopupAllocationData, reserveBudget, dan releaseBudget. Minta bentuk payload JSON/route aktual bila belum tersedia.
- Tampilkan pesan error dari backend dengan jelas.

Buat komponen modal yang dapat digunakan ulang, state loading/error/empty, dan alur kembali/mengubah pilihan. Setelah selesai, uji skenario data tersedia, data kosong, loading, dan pagu tidak mencukupi.
```

## PROMPT 5 — Form kuitansi dan upload dokumen

```text
Implementasikan Form Input Kuitansi Atomik SI-TALITI berdasarkan spesifikasi.

Form perlu memuat field transaksi yang memang tercantum pada spesifikasi database:
- Unit kerja dan rekening terpilih.
- Bulan anggaran.
- Nomor bukti toko.
- Tanggal transaksi.
- Uraian belanja.
- Nama penerima.
- NPWP penerima bila relevan.
- Metode pembayaran (NON_TUNAI_IBC atau TUNAI_UP).
- Nilai bruto.
- Rincian pajak dan nilai neto dari hasil backend.

Tambahkan upload lampiran terstruktur sesuai kategori checklist B.1–B.6. Gunakan konfigurasi checklist terpusat agar field dokumen tidak tersebar di banyak file.

UX:
- Kelompokkan form menjadi bagian yang mudah dipahami.
- Tampilkan rekening/kategori yang dipilih dari Gatekeeper.
- Tampilkan preview pajak dari backend.
- Tampilkan daftar file terpilih, tipe/ukuran, progress bila tersedia, dan kemampuan menghapus sebelum submit.
- Validasi client-side hanya untuk kenyamanan; server tetap menjadi sumber validasi final.
- Tampilkan error validasi server pada field yang sesuai.
- Beri konfirmasi sebelum submit.

Jangan mengarang field bisnis tambahan atau menetapkan aturan pajak baru. Tandai field yang membutuhkan keputusan pemilik proses. Gunakan mock service sampai endpoint backend disepakati.
```

## PROMPT 6 — Dynamic Checklist B.1–B.6

```text
Bangun Dynamic Checklist Engine untuk kategori belanja SI-TALITI B.1 sampai B.6 sesuai dokumen spesifikasi.

Kategori:
B.1 ATK/Cetak/Penggandaan
B.2 Makan/Minum Rapat
B.3 Honorarium/Jasa
B.4 Perjalanan Dinas
B.5 Hibah Barang/Sarpras Kelurahan
B.6 Kegiatan/Sosialisasi Lainnya

Buat konfigurasi checklist terpusat. Setiap item minimal memiliki:
- key unik;
- label dokumen;
- keterangan;
- required/optional (hanya jika ketentuannya telah dipastikan);
- kondisi pemicu jika ada;
- status file;
- status pemeriksaan jika konteksnya workspace verifikasi.

Perhatikan ketentuan khusus yang disebut dokumen:
- B.1 BAST wajib jika e-purchasing; PPN dan PPh 22 untuk nominal di atas Rp2.000.000; meterai di atas Rp5.000.000.
- B.2 dua slot foto terpisah: kegiatan dan snack/nasi box.
- B.3 bukti potong PPh 21 dan dasar pembayaran.
- B.4 dokumen SPD/SPT, rincian rampung, laporan hasil, dokumentasi.
- B.5 BAST hibah dan dokumentasi fisik 0% serta 100%.
- B.6 undangan, daftar hadir, laporan hasil, dokumentasi, dan surat pesanan.

Jangan menyimpulkan aturan pajak baru. Aturan yang membutuhkan parameter atau keputusan backend harus ditandai sebagai konfigurasi dari server.

Checklist harus dapat dipakai ulang pada form kuitansi dan workspace verifikasi dengan mode yang berbeda (unggah vs pemeriksaan). Tambahkan unit test untuk pemetaan kategori bila test setup tersedia.
```

## PROMPT 7 — Split-Screen Verification Workspace

```text
Implementasikan Verification Workspace SI-TALITI sesuai spesifikasi.

Layout desktop:
- Panel kiri 55% untuk PDF/document viewer.
- Panel kanan 45% untuk checklist B.1–B.6.
- Tampilkan identitas kuitansi, rekening, unit, nominal, dan status secara ringkas.
- Tampilkan daftar dokumen dan status pemeriksaan.
- Sediakan navigasi yang jelas antar dokumen.

Fitur:
1. Antrean verifikasi dengan filter status.
2. Viewer dokumen yang menerima URL/file dari backend.
3. Checklist pemeriksaan per dokumen.
4. Catatan pemeriksa.
5. Timeline status/riwayat.
6. Dialog aksi persetujuan atau pengembalian sesuai role dan status yang dikirim backend.
7. Pilihan revisi minor/mayor hanya mengikuti aturan workflow yang disepakati.
8. Indikator SLA 2 x 24 jam berdasarkan timestamp server.

Responsive:
- Pada layar kecil, gunakan tab Dokumen dan Checklist atau layout vertikal.
- Jangan memaksakan split-screen sempit di mobile.

PENTING:
- Jangan membuat transisi status sendiri.
- Jangan menampilkan tombol aksi jika izin/status tidak mengizinkan.
- Jangan menganggap checklist lokal sebagai keputusan final.
- Semua keputusan final harus berasal dari backend.
- Jika endpoint belum tersedia, gunakan mock adapter dan beri label DEMO.

Uji state loading, dokumen gagal dimuat, checklist kosong, server error, dan submit berhasil.
```

## PROMPT 8 — Bundel SPJ dan Bagian A

```text
Implementasikan UI Bundel SPJ SI-TALITI dengan tetap memisahkan bundel dari kuitansi atomik.

Jenis bundel yang tercantum:
- SPP_GU
- SPP_TU
- SPP_LS
- LPJ_BPP

Status bundel:
- DRAFT
- MENUNGGU_PPK
- DISETUJUI_PPK
- SYNCED_SIPD

Buat:
1. Daftar bundel dengan filter periode, unit, jenis, dan status.
2. Detail bundel.
3. Daftar kuitansi yang menjadi item bundel.
4. Ringkasan total bruto, pajak, dan neto dari backend.
5. Tampilan status item aktif/dikeluarkan.
6. Checklist Bagian A untuk sembilan dokumen:
   NPD, Lembar Pengiriman SPJ, Berita Acara Pemeriksaan Kas, Register Penutupan Kas, Laporan Pengeluaran Kas, SPJ Fungsional, BKU, Buku Pembantu Pajak, dan Rekening Koran.
7. Tampilan aksi bundling/unbundling hanya sesuai izin dan endpoint backend.

Jangan mengimplementasikan aturan batching atau validasi settlement di frontend. Jangan menyamakan status bundel dengan status kuitansi. Gunakan mock data bila endpoint belum siap.
```

## PROMPT 9 — Lembar Kendali, QR, grafik, dan finalisasi

```text
Selesaikan modul frontend SI-TALITI untuk target pilot demo.

Implementasikan:
1. Preview Lembar Kendali Verifikasi.
2. Layout cetak yang rapi dan formal.
3. QR Code berdasarkan token atau URL verifikasi yang diberikan backend.
4. Grafik RAK berdasarkan data agregat dari backend.
5. Tampilan nomor SIPD-RI jika tersedia.
6. Responsivitas dan aksesibilitas seluruh modul.
7. Empty/loading/error/success state yang konsisten.
8. Perbaikan navigasi dan konsistensi visual.

Jangan membuat token QR sendiri dan jangan menganggap QR sebagai bukti persetujuan jika backend belum memberikan status resmi. Jangan mengarang angka grafik.

Lakukan pemeriksaan menyeluruh:
- Jalankan build, lint, dan test yang tersedia.
- Periksa console error.
- Periksa route/halaman yang dibuat.
- Periksa seluruh form dan state.
- Periksa tampilan desktop dan mobile.
- Pastikan mock data ditandai jelas dan tidak tercampur dengan data produksi.
- Buat laporan fitur selesai, fitur belum selesai, endpoint yang masih dibutuhkan, risiko, dan skenario demo pilot.

Jangan commit atau push sebelum saya memeriksa hasilnya.
```

---

# 11. Checklist pekerjaan pertama yang harus dilakukan

Gunakan checklist ini sebelum mulai coding:

- [ ] Baca dan pahami dokumen spesifikasi SI-TALITI.
- [ ] Pastikan repository dan branch kerja.
- [ ] Pastikan Laravel 12, Inertia, Vue 3, dan Tailwind dapat berjalan.
- [ ] Konfirmasi starter kit dan pola autentikasi.
- [ ] Minta matriks role dan izin.
- [ ] Minta contoh payload untuk data RAK popup.
- [ ] Minta kontrak response kalkulasi pajak.
- [ ] Minta daftar endpoint/route dan format error backend.
- [ ] Minta definisi final checklist B.1–B.6.
- [ ] Minta diagram transisi status kuitansi dan bundel.
- [ ] Minta contoh file RAK resmi dan data seeder.
- [ ] Sepakati tema visual dan identitas instansi.
- [ ] Buat design system dan layout.
- [ ] Buat mock data terpisah.
- [ ] Buat dashboard dan tabel anggaran.
- [ ] Lanjutkan ke Gatekeeper dan form kuitansi.

---

# 12. Skenario pengujian frontend minimum

| Skenario | Hasil yang diharapkan |
|---|---|
| Pengguna belum memilih rekening | Form kuitansi belum dapat dimulai |
| Data RAK sedang dimuat | Loading state terlihat |
| Data RAK kosong | Empty state dan pesan yang jelas |
| Bulan mendatang dipilih | UI mengikuti larangan pemakaian pagu bulan mendatang dari backend |
| Pagu tidak mencukupi | Error backend ditampilkan dan submit ditangani dengan aman |
| Kategori rekening B.2 dipilih | Checklist Makan/Minum dan dua slot foto tampil |
| File tidak sesuai validasi | Pengguna mendapat pesan yang jelas |
| Backend mengembalikan error field | Error tampil pada field terkait |
| Dokumen PDF gagal dimuat | Fallback/error state tampil |
| Pengguna tidak berhak memverifikasi | Aksi verifikasi tidak tersedia dan backend tetap memblokir |
| Revisi dikembalikan | Catatan dan status revisi tampil |
| QR belum tersedia | UI tidak membuat QR token palsu |
| Data grafik kosong | Empty state, bukan grafik dengan angka rekaan |

---

# 13. Risiko dan rekomendasi teknis

## Risiko utama

1. **Kontrak backend terlambat atau berubah.**  
   Mitigasi: buat adapter service dan mock JSON sejak awal, lalu dokumentasikan mapping mock ke response backend.

2. **Aturan keuangan ditafsirkan berbeda oleh frontend dan backend.**  
   Mitigasi: frontend hanya menampilkan hasil backend; definisi rumus dan threshold harus disepakati tertulis.

3. **Workflow status belum disepakati.**  
   Mitigasi: minta diagram state machine sebelum membuat tombol aksi final.

4. **Checklist menjadi sulit dipelihara.**  
   Mitigasi: gunakan konfigurasi kategori terpusat dan komponen checklist reusable.

5. **Workspace pemeriksaan terlalu padat.**  
   Mitigasi: uji dengan dokumen panjang, layar laptop umum, serta mode mobile/tablet.

6. **Data mock dianggap data resmi.**  
   Mitigasi: beri label DEMO, pisahkan folder mock, dan gunakan data seeder resmi saat integrasi.

7. **Agent AI mengubah banyak file tanpa kontrol.**  
   Mitigasi: prompt bertahap, tinjau diff, jalankan test, dan commit per modul setelah disetujui.

## Saran paling penting

Jangan mulai dari dashboard yang tampak lengkap tetapi tidak punya kontrak data. Fondasi yang paling menentukan keberhasilan frontend SI-TALITI adalah:

1. **Design system yang konsisten.**
2. **Kontrak JSON yang disepakati dengan backend.**
3. **Konfigurasi checklist B.1–B.6.**
4. **Pemetaan status dan role.**
5. **Gatekeeper RAK yang memakai data backend.**

Setelah lima fondasi tersebut jelas, pengembangan dengan Antigravity akan lebih terarah dan risiko pengerjaan ulang menjadi lebih kecil.

---

# 14. Pertanyaan yang harus dibawa ke tim sebelum integrasi

1. Apa saja role resmi dan izin masing-masing?
2. Apa payload JSON final untuk `getPopupAllocationData`?
3. Bagaimana backend menyajikan simulasi pajak dan hasil validasi pajak?
4. Apakah lampiran diunggah satu per satu atau sekaligus? Bagaimana batas ukuran dan formatnya?
5. Bagaimana definisi final required/optional untuk setiap item checklist?
6. Apa transisi status yang sah untuk PPTK, BPP, verifikatur, dan PPK?
7. Bagaimana revisi minor dibedakan dari revisi mayor secara operasional?
8. Apa sumber timestamp SLA dan bagaimana aturan penghentian/penghitungannya?
9. Bagaimana frontend memperoleh URL dokumen yang aman?
10. Apa isi final Lembar Kendali dan URL/format QR?
11. Metrik apa yang harus ditampilkan pada grafik RAK?
12. Apakah ada design reference, logo, warna resmi, atau format dokumen instansi?

---

**Kesimpulan:** Mulailah dengan audit repository dan setup fondasi, lalu design system serta mock contract. Jangan meminta Antigravity membangun semua modul dalam satu prompt. Kerjakan per sprint dan per modul, selalu periksa perubahan kode, dan pastikan keputusan keuangan serta workflow tetap menjadi kewenangan backend.
