# 📘 SI-TALITI v2.0 — Frontend Integration & API Access Guide

> **Panduan Integrasi Backend & Frontend untuk Seluruh Tim Pengembang**  
> **Aplikasi:** SI-TALITI v2.0 (Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ)  
> **Arsitektur:** Monorepo Laravel 12 + Inertia.js + Vue 3 (Vite + Tailwind CSS)  
> **Status Backend:** ✅ **100% Live, Tested & Verified (98 Tests, 497 Assertions, Pint Clean)**

---

## 1. Arsitektur Komunikasi & Kontrak API

Backend SI-TALITI v2.0 menyajikan RESTful JSON API di bawah prefix `/api/v1/` dengan mengadopsi standar respon terstruktur, proteksi CSRF otomatis monorepo, serta kontrol akses berbasis peran (RBAC).

### 1.1 Base URL & Headers
- **Base URL:** `/api/v1` (relatif terhadap host aplikasi)
- **Headers Otomatis:** Dikelola oleh Axios di [`resources/js/bootstrap.js`](file:///Users/muhammadabyankamal/proyek/sitaliti/sitaliti-app/resources/js/bootstrap.js) yang menyertakan session cookie dan token CSRF:
  ```http
  Accept: application/json
  Content-Type: application/json
  X-Requested-With: XMLHttpRequest
  X-CSRF-TOKEN: <meta name="csrf-token" content="...">
  ```

### 1.2 Format Respon Standar (`ApiResponseTrait`)

#### A. Respon Sukses (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Operasi berhasil dijalankan",
  "data": { ... }
}
```

#### B. Respon Error Terstruktur (HTTP 400 / 422 / 403 / 404 / 500)
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE_IDENTIFIER",
    "message": "Pesan deskriptif dalam Bahasa Indonesia yang ramah pengguna",
    "details": null
  }
}
```

---

## 2. Matriks Otorisasi Peran (RBAC 5 Peran)

| Role | Kode Pengguna | Wewenang Utama | Pembatasan & Penolakan (HTTP 403) |
| :--- | :--- | :--- | :--- |
| **Operator Seksi / Kelurahan** | `OPERATOR` / `STAFF_SEKSI` | Membuat draf, mengunggah nota, submit kuitansi ke PPTK | Dilarang memverifikasi, dilarang membundel SPP, dilarang approval PPTK |
| **Pejabat Pelaksana Teknis Kegiatan** | `PPTK` | Menyetujui / menolak kuitansi antrean unit kerjanya | Dilarang membuat kuitansi baru, dilarang verifikasi akhir bendahara |
| **Verifikator Kecamatan** | `VERIFIKATOR` | Memeriksa berkas split-screen 35 checklist, memutuskan lolos/revisi | Dilarang membuat draf kuitansi, dilarang mencatat realisasi SP2D |
| **Bendahara Pengeluaran Pembantu** | `BENDAHARA` / `BPP` | Membundel kuitansi lolos verifikasi ke berkas pengajuan SPP | Dilarang mengubah hasil checklist verifikasi teknis |
| **Camat / Kuasa Pengguna Anggaran** | `CAMAT` / `KPA` | Mencatat nomor SP2D SIPD-RI dan pengesahan pencairan anggaran | Dilarang membuat draf kuitansi level operasional |

---

## 3. Katalog Endpoint API Lengkap

### 3.1 Modul Dasbor & Anggaran Kas RAK (`budgetService.js`)

#### `GET /api/v1/budget/summary`
Mengambil rekapitulasi data riil: pagu murni, pagu terkunci, realisasi definitif, persentase serapan, alokasi 12 bulan, statistik 6 unit kerja, ringkasan antrean kuitansi, dan aktivitas terbaru.
- **Contoh Response `data`:**
  ```json
  {
    "total_pagu": 14252126458.00,
    "total_terkunci": 12500000.00,
    "total_realisasi": 245000000.00,
    "sisa_pagu": 13994626458.00,
    "persentase_realisasi": 1.72,
    "queue_counts": {
      "menunggu_pptk": 4,
      "menunggu_verifikasi": 7,
      "revisi_minor": 2,
      "lolos_verifikasi": 15
    },
    "monthly_allocation": [
      { "bulan": 1, "nama_bulan": "Januari", "alokasi": 1187677204.00, "realisasi": 120000000.00, "terkunci": 0 },
      ...
    ],
    "unit_breakdown": [
      { "id": 1, "nama_unit": "Kecamatan Tarogong Kidul", "pagu": 8500000000.00, "realisasi": 180000000.00, "terkunci": 10000000.00 },
      ...
    ],
    "recent_activities": [
      { "id": 102, "nomor_kuitansi": "KUI/2026/03/0012", "action": "VERIFIED", "catatan": "Berkas lengkap dan sesuai regulasi", "created_at": "2026-03-08T10:15:00Z" }
    ]
  }
  ```

#### `GET /api/v1/budget/units`
Mengambil 6 unit kerja aktif di Kecamatan Tarogong Kidul.

#### `GET /api/v1/budget/sub-activities?unit_id={unitId}`
Mengambil daftar rekening belanja leaf SIPD-RI milik unit kerja tertentu beserta kategori belanja standar (B.1 s.d. B.6).

#### `GET /api/v1/budget/rak-context/{rekeningId}?month={month}`
Mengambil simulasi alokasi kas bulanan, sisa pagu murni, pagu terkunci, dan akumulasi sisa kas bulan lalu (*accumulative carry-over*).

---

### 3.2 Modul Transaksi & Kuitansi (`receiptService.js`)

#### `POST /api/v1/receipts/validate-draft`
Simulasi perhitungan pajak *zero-drift* dan validasi ketersediaan pagu tanpa menyimpan transaksi.
- **Payload:**
  ```json
  {
    "anggaran_rekening_id": 12,
    "bulan_anggaran": 3,
    "nominal_bruto": 2220000,
    "kategori_belanja": "B.1",
    "is_ppn": true,
    "npwp_penerima": "01.234.567.8-443.000",
    "golongan_pns": null
  }
  ```
- **Response `data`:**
  ```json
  {
    "dpp": 2000000,
    "ppn": 220000,
    "pph22": 30000,
    "pph23": 0,
    "pph21": 0,
    "pbjt": 0,
    "bea_meterai": 0,
    "total_pajak": 250000,
    "nominal_neto": 1970000,
    "sisa_pagu_tersedia": 22780000
  }
  ```

#### `POST /api/v1/receipts`
Menyimpan draf kuitansi baru beserta rincian item, pajak, dan berkas lampiran.
- **Multipart Form-Data:**
  - `nomor_bukti_toko`: `"INV-2026-001"`
  - `anggaran_rekening_id`: `12`
  - `bulan_anggaran`: `3`
  - `nominal_bruto`: `2220000`
  - `file_lampiran[]`: `[File Binary]` *(Sistem otomatis menghitung hash SHA-256 untuk mendeteksi nota kembar)*

#### `POST /api/v1/receipts/{id}/submit`
Mengunci pagu kas bulanan secara atomik dan memindahkan status kuitansi dari `DRAFT` menjadi `MENUNGGU_PPTK`.

#### `GET /api/v1/receipts/{id}/history`
Mengambil kronologi audit trail BPK RI untuk kuitansi tertentu.
- **Response `data`:**
  ```json
  [
    {
      "id": 1,
      "action": "SUBMIT_TO_PPTK",
      "status_lama": "DRAFT",
      "status_baru": "MENUNGGU_PPTK",
      "actor_name": "Operator Seksi Tatapem",
      "actor_role": "STAFF_SEKSI",
      "catatan": "Pengajuan kuitansi pengadaan ATK rapat dinas",
      "ip_address": "127.0.0.1",
      "created_at": "2026-03-05T08:30:00Z"
    }
  ]
  ```

---

### 3.3 Modul Persetujuan PPTK & Verifikasi (`verificationService.js`)

#### `POST /api/v1/pptk/receipts/{id}/approve`
Persetujuan tingkat 1 oleh PPTK. Mengubah status kuitansi dari `MENUNGGU_PPTK` menjadi `MENUNGGU_VERIFIKASI`.

#### `POST /api/v1/pptk/receipts/{id}/reject`
Penolakan oleh PPTK. Melepaskan pagu yang terkunci dan mengembalikan kuitansi ke status `DRAFT` untuk diperbaiki oleh operator.

#### `GET /api/v1/verification/queue`
Mengambil antrean verifikasi berkas (hanya kuitansi yang telah disetujui PPTK berstatus `MENUNGGU_VERIFIKASI` atau `REVISI_MINOR`).

#### `GET /api/v1/verification/{id}`
Mengambil rincian kuitansi split-screen, berkas lampiran fisik, serta 35 item master checklist dinamis berdasarkan kategori belanja rekening.

#### `POST /api/v1/verification/{id}/action`
Memproses keputusan verifikasi oleh tim verifikator kecamatan:
- **Aksi `VERIFIED`:** Kuitansi lolos verifikasi, sistem menghasilkan `qr_token` UUID publik untuk Lembar Kendali.
- **Aksi `REVISION_MINOR`:** Menetapkan batas waktu SLA 2 hari kerja efektif (melompati hari Sabtu dan Minggu).
- **Aksi `REVISION_MAJOR`:** Mengembalikan kuncian pagu anggaran kas ke saldo kas rekening.

---

### 3.4 Modul Unduh Lembar Kendali PDF & Verifikasi QR Publik

#### `GET /api/v1/control-sheet/{id}/pdf`
Mengunduh dokumen fisik Lembar Kendali Verifikasi resmi dalam format PDF berstandar format tata letak dinas Kabupaten Garut dengan QR Code SVG offline murni.

#### `GET /verify/{qr_token}`
Endpoint web publik (bebas autentikasi) untuk memverifikasi keaslian lembar kendali. Data pribadi rekanan (NIK, nomor rekening, alamat) disensor secara otomatis (UU PDP).

---

## 4. Panduan Penanganan Error Standar di Frontend

Gunakan blok `try...catch` dan intersep error terstruktur pada Axios:

```javascript
try {
    await receiptService.saveReceipt(formData);
    // Tampilkan notifikasi sukses toast
} catch (error) {
    const errorData = error.response?.data?.error;
    
    switch (errorData?.code) {
        case 'DUPLICATE_RECEIPT_ATTACHMENT':
            // Peringatan nota kembar SHA-256
            showGovModal({
                title: 'Nota Duplikat Terdeteksi',
                message: errorData.message,
                type: 'danger'
            });
            break;
            
        case 'PAGU_OVERLIMIT':
            // Peringatan pagu kas RAK tidak mencukupi
            showGovToast({
                title: 'Pagu Kas Melampaui Batas',
                message: errorData.message,
                variant: 'warning'
            });
            break;
            
        case 'UNAUTHORIZED_ACTION':
            // Pelanggaran batas wewenang RBAC
            showGovToast({
                title: 'Akses Ditolak',
                message: 'Anda tidak memiliki wewenang untuk melakukan tindakan ini.',
                variant: 'danger'
            });
            break;
            
        default:
            showGovToast({
                title: 'Kesalahan Sistem',
                message: errorData?.message || 'Terjadi gangguan saat menghubungi server.',
                variant: 'danger'
            });
    }
}
```
