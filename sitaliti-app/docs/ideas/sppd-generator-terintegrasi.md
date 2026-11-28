# SPPD Generator Terintegrasi (Pra-Kuitansi & Bundel PDF)

## Problem Statement
How might we mengubah proses pembuatan 5 dokumen SPPD yang selama ini manual dan rawan salah copy-paste di Excel menjadi satu alur terintegrasi di SI-TALITI—mulai dari terbitnya Surat Tugas sebelum berangkat hingga bundel PDF siap tanda tangan basah dan SPJ Kuitansi—tanpa menambah beban input ganda bagi staf Kecamatan maupun Kelurahan?

## Recommended Direction
Mengadopsi **Siklus 2-Tahap Terintegrasi (Pra-Kuitansi → Kuitansi SPJ)**:
1. **Tahap 1 (Pra-Berangkat):** Staf/admin menginput penugasan SPPD (maksud tugas, tujuan, alat angkut, tanggal, pejabat pemberi perintah, dan peserta ASN maupun Non-ASN). Sistem menjalankan pengecekan bentrok jadwal via `SppdScheduleService` lalu menghasilkan PDF **Surat Tugas**, **Lembar SPD Depan**, dan **Lembar SPD Belakang** siap tanda tangan basah.
2. **Tahap 2 (Pasca-Berangkat / SPJ):** Sepulang dinas, staf melengkapi **Laporan Hasil Perjalanan Dinas** dan mengaitkan/mengonversi penugasan tersebut menjadi **Kuitansi SPJ** (menarik tarif SBM dan kode rekening `5.1.02.04.*`). Sistem menghasilkan PDF **Kwitansi + Rincian Biaya + Perhitungan SPD Rampung** dan **Laporan Hasil Perjalanan Dinas**, atau mengunduh **1 Bundel PDF Komplit (5 dokumen)** sekaligus.

Pendekatan ini menghindari input ganda antara administrasi keberangkatan dan bendahara pengeluaran, sekaligus tidak memblokir penerbitan Surat Tugas ketika rincian biaya riil belum difinalkan.

## Key Assumptions to Validate
- [ ] **Kop & Pejabat per Unit Kerja:** Setiap `UnitKerja` (Kecamatan & 5 Kelurahan) memiliki konfigurasi kop surat, alamat, telepon/email, pejabat penandatangan (Camat/Lurah), dan Bendahara yang konsisten untuk dicetak di PDF.
- [ ] **Ukuran Kertas Resmi:** Layout 5 dokumen PDF menggunakan ukuran **F4 / Folio (215 × 330 mm)** (atau dapat dipilih F4/A4) agar blok TTD pada Lembar SPD Belakang (`BLKG OKE`) muat dalam 1 halaman tanpa terpotong.
- [ ] **Penanganan Non-ASN:** Peserta Non-ASN tidak memiliki NIP dan Pangkat/Golongan, sehingga pada dokumen SPD Depan, Surat Tugas, dan Kwitansi harus tampil `-` (tanpa memblokir validasi unik NIP di tabel `pegawai`).
- [ ] **Mode Rombongan:** Untuk penugasan >1 orang, sistem mendukung 1 Surat Tugas kolektif dengan opsi cetak SPD per individu (masing-masing pegawai mendapat Lembar SPD Depan/Belakang/Kwitansi sendiri) atau 1 SPD utama + daftar pengikut.

## MVP Scope
- **Entitas Penugasan SPPD** (`sppd_assignments` + `sppd_peserta`) yang dapat dibuat sebelum Kuitansi ada (`kuitansi_id` nullable), mendukung peserta **ASN (`pegawai_id`)** dan **Non-ASN**.
- **Deteksi Kop & Penandatangan Otomatis** berdasarkan `UnitKerja` (Kecamatan vs Kelurahan) dan jabatan pelaksana (Camat, Lurah, Kasi/Kasubbag, Staf, Non-ASN).
- **Cek Bentrok Jadwal Real-Time** menggunakan `SppdScheduleService` sebelum dokumen dicetak.
- **Generator PDF Siap TTD Basah** via `barryvdh/laravel-dompdf` untuk 5 dokumen:
  1. Surat Tugas (individu atau kolektif s.d. 5+ orang)
  2. Lembar SPD Depan (10 butir standar + pengikut + varian Non-ASN)
  3. Lembar SPD Belakang (Blok I–VI tiba/berangkat)
  4. Kwitansi & Rincian Biaya Perjalanan Dinas + Perhitungan SPD Rampung
  5. Laporan Hasil Perjalanan Dinas (`hasil SPPD`)
- **Unduh Satuan per Tahap** (Pra-Berangkat / Pasca-Berangkat) maupun **Bundel PDF Komplit**.

## Not Doing (and Why)
- **Tanda Tangan Elektronik (TTE/BSrE) & QR di dalam PDF SPPD:** Klien membutuhkan dokumen siap tanda tangan basah fisik pada MVP ini.
- **Auto-Numbering Surat Kaku:** Penomoran surat (`/Tarkid/SPPD/IX/2026` atau `821/...-Kel/2024`) tetap bisa diisi manual agar tidak bentrok dengan buku agenda fisik kantor.
- **Perhitungan Biaya Kompleks Luar Daerah Multi-Hari (Tiket Pesawat/Hotel Riil):** Fokus MVP pada pola Kecamatan/Kelurahan (Uang Makan Harian, Uang Transpor Lokal/Harian, atau item rincian biaya fleksibel) sesuai `SPPD kumplit kec.xlsx`.

## Open Questions
1. Untuk ukuran kertas cetak PDF, apakah seluruh Kecamatan & Kelurahan memakai **F4 (215 × 330 mm)** atau A4?
2. Untuk perjalanan dinas rombongan (misal 5 orang), apakah masing-masing pegawai wajib mendapat lembar SPD Depan, SPD Belakang, dan Kwitansi sendiri, atau cukup 1 SPD + kolom Pengikut?
3. Apakah peserta Non-ASN perlu disimpan di master `pegawai` (sebagai pegawai Non-ASN) agar tidak mengetik ulang setiap kali bertugas, atau cukup input ad-hoc di form SPPD?
