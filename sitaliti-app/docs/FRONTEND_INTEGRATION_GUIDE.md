# 📘 SI-TALITI v2.0 — Frontend Integration & API Access Guide

> **Panduan Integrasi Backend untuk Frontend Engineer (Developer 2)**  
> **Aplikasi:** SI-TALITI v2.0 (Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ)  
> **Arsitektur:** Monorepo Laravel 12 + Inertia.js + Vue 3 (Vite + Tailwind CSS)  
> **Status Backend:** ✅ **100% Live, Tested & Verified (73 Tests, 268 Assertions)**

---

## 1. Arsitektur & Standar Komunikasi API

Backend SI-TALITI v2.0 menyediakan RESTful JSON API terstandarisasi di bawah prefix `/api/v1/` dengan mengadopsi prinsip *Contract-First* dan *Consistent Error & Success Semantics*.

### 1.1 Base URL & Headers
- **Base URL:** `/api/v1` (relatif terhadap host aplikasi)
- **Default Headers:**
  ```http
  Accept: application/json
  Content-Type: application/json
  X-Requested-With: XMLHttpRequest
  X-CSRF-TOKEN: <meta name="csrf-token" content="...">
  ```
  *(Catatan: Karena frontend dan backend berada dalam monorepo Laravel yang sama, Axios yang dikonfigurasi di `resources/js/bootstrap.js` secara otomatis menyertakan header `X-CSRF-TOKEN` dan cookie session).*

### 1.2 Format Respon Standar (`ApiResponseTrait`)

#### A. Respon Sukses (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Operasi berhasil dijalankan",
  "data": { ... }
}
```

#### B. Respon Error Terstruktur (HTTP 400 / 422 / 404 / 500)
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

## 2. Katalog Endpoint API Lengkap

### 2.1 Modul Master Data & Anggaran RAK (`budgetService.js`)

#### `GET /api/v1/budget/summary`
Mengambil rekapitulasi dashboard eksekutif: total pagu murni/perubahan se-Kecamatan dan Kelurahan (Rp 14,25 Miliar), total pagu terkunci, total realisasi definitif, dan persentase serapan.

- **Response `data`**:
  ```json
  {
    "total_pagu": 14252126458.00,
    "total_terkunci": 5000000.00,
    "total_realisasi": 125000000.00,
    "sisa_pagu": 14122126458.00,
    "persentase_realisasi": 0.88
  }
  ```

#### `GET /api/v1/budget/units`
Mengambil daftar 6 unit kerja resmi di wilayah Kecamatan Tarogong Kidul.

- **Response `data`**:
  ```json
  [
    { "id": 1, "code": "7.01.0.00.0.00.41.0000", "name": "Kecamatan Tarogong Kidul", "type": "KECAMATAN" },
    { "id": 2, "code": "7.01.0.00.0.00.41.0001", "name": "Kelurahan Sukagalih", "type": "KELURAHAN" },
    { "id": 3, "code": "7.01.0.00.0.00.41.0002", "name": "Kelurahan Tarogong", "type": "KELURAHAN" },
    { "id": 4, "code": "7.01.0.00.0.00.41.0003", "name": "Kelurahan Pataruman", "type": "KELURAHAN" },
    { "id": 5, "code": "7.01.0.00.0.00.41.0004", "name": "Kelurahan Haurpanggung", "type": "KELURAHAN" },
    { "id": 6, "code": "7.01.0.00.0.00.41.0005", "name": "Kelurahan Jayawaras", "type": "KELURAHAN" }
  ]
  ```

#### `GET /api/v1/budget/sub-activities?unit_id={unitId}`
Mengambil daftar subkegiatan dan rekening belanja leaf SIPD-RI milik unit kerja tertentu.

- **Response `data`**:
  ```json
  [
    {
      "id": 12,
      "unit_id": 1,
      "code": "7.01.01.2.01.0001",
      "name": "Penyusunan Rencana Operasional Perangkat Daerah",
      "kode_rekening": "5.1.02.01.01.0024",
      "nama_rekening": "Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak",
      "pagu": 25000000.00,
      "category": "B.1"
    }
  ]
  ```

#### `GET /api/v1/budget/rak-context/{rekeningId}?month={month}`
Mengambil kuota RAK bulanan, pagu terkunci, realisasi, dan **akumulasi carry-over** dari bulan $1 \dots n-1$ untuk modal pop-up validasi batas anggaran.

- **Response `data`**:
  ```json
  {
    "rekening_id": "12",
    "selected_month": 6,
    "active_system_month": 11,
    "is_future_month": false,
    "pagu_murni_bulan": 2000000.00,
    "carry_over_lalu": 4500000.00,
    "total_tersedia": 6500000.00,
    "terkunci_komitmen": 500000.00,
    "sisa_dapat_digunakan": 6000000.00,
    "status_lock": "OPEN"
  }
  ```

---

### 2.2 Modul Kuitansi & Simulasi Pajak (`receiptService.js`)

#### `POST /api/v1/receipts/validate-draft`
Simulasi instan validasi kepatuhan pajak (UU HPP, PMK 59/2022, UU HKPD 1/2022, PP 80/2010) tanpa menyimpan ke database.

- **Payload:**
  ```json
  {
    "anggaran_rekening_id": 12,
    "bulan_anggaran": 6,
    "kategori_belanja": "B.1",
    "has_npwp": true,
    "items": [
      { "nama_barang": "Kertas Folio F4", "volume": 20, "satuan": "Rim", "harga_satuan": 55500 }
    ]
  }
  ```
- **Response `data`**:
  ```json
  {
    "total_belanja": 1110000,
    "is_exceeding_budget": false,
    "sisa_pagu_tersedia": 6000000,
    "tax_calculation": {
      "dpp": 1110000,
      "ppn": 0,
      "pph22": 0,
      "total_pajak": 0,
      "nilai_neto": 1110000,
      "bea_meterai": 0,
      "catatan_regulasi": "Belanja ATK <= Rp 2.000.000 bebas pungut PPN dan PPh 22 sesuai PMK 59/2022."
    }
  }
  ```

#### `POST /api/v1/receipts`
Menyimpan draft kuitansi baru beserta berkas fisik dengan **verifikasi hash SHA-256 instan**. Menggunakan format `multipart/form-data`.

- **Headers:** `Content-Type: multipart/form-data`
- **Form Fields:**
  - `anggaran_rekening_id` (integer, wajib)
  - `bulan_anggaran` (integer 1..12, wajib)
  - `nomor_kuitansi` (string opsional, sistem otomatis men-generate jika kosong)
  - `nomor_bukti_toko` (string opsional, no nota toko/faktur)
  - `tanggal_transaksi` (date YYYY-MM-DD, wajib)
  - `uraian` (string, wajib)
  - `nama_penerima` (string, nama vendor/rekanan)
  - `npwp_penerima` (string opsional)
  - `metode_pembayaran` (`NON_TUNAI_IBC` atau `TUNAI_UP`)
  - `items[0][nama_barang]`, `items[0][volume]`, `items[0][satuan]`, `items[0][harga_satuan]`
  - `files[]` (file PDF/JPG/PNG lampiran)
- **Response Error Khusus (Deteksi Nota Kembar):**
  ```json
  {
    "success": false,
    "error": {
      "code": "DUPLICATE_RECEIPT_ATTACHMENT",
      "message": "Berkas nota ini telah digunakan sebelumnya pada rekening belanja yang sama. Sistem menolak duplikasi berkas fisik.",
      "details": { "file_name": "nota_pembelian.pdf" }
    }
  }
  ```

#### `POST /api/v1/receipts/{id}/submit`
Mengajukan kuitansi draft ke PPTK. Mengunci alokasi pagu RAK secara atomic (`lockForUpdate`) dan mengubah status menjadi `MENUNGGU_PPTK`.

---

### 2.3 Modul Workspace Verifikasi & SLA (`verificationService.js`)

#### `GET /api/v1/verification/queue`
Mengambil antrean kuitansi yang perlu diperiksa oleh tim verifikator.
- **Query Params:** `?status=MENUNGGU_VERIFIKASI&unit_id=1`
- **Response `data`**: Array kuitansi lengkap dengan `sla_deadline`, `attachments_count`, `nominal_neto`, dll.

#### `GET /api/v1/verification/{id}`
Memuat data lengkap split-screen workspace (metadata kuitansi, rincian barang, pajak, dokumen lampiran, dan **matriks checklist dinamis B.1–B.6**).

#### `POST /api/v1/verification/{id}/action`
Memproses keputusan verifikasi kuitansi.
- **Payload:**
  ```json
  {
    "action": "VERIFIED", // "VERIFIED" | "REVISION_MINOR" | "REVISION_MAJOR"
    "notes": "Bukti dukung fisik dan administrasi lengkap.",
    "checklist_results": [
      { "item_id": 1, "status": "SESUAI", "notes": "Faktur valid" },
      { "item_id": 2, "status": "SESUAI", "notes": "Cap basah ada" }
    ]
  }
  ```
- **Efek State Machine:**
  - `VERIFIED`: Status $\to$ `LOLOS_VERIFIKASI`, menghasilkan `qr_token` UUID untuk Lembar Kendali.
  - `REVISION_MINOR`: Status $\to$ `REVISI_MINOR`, otomatis menetapkan `tenggat_sla` 2 hari kerja efektif (melompati hari Sabtu & Minggu).
  - `REVISION_MAJOR`: Status $\to$ `REVISI_MAYOR`, otomatis memulihkan kuota pagu terkunci ke RAK (`releaseBudget()`).

---

### 2.4 Modul Two-Tier Settlement & SPP (`bundleService.js`)

#### `GET /api/v1/bundles`
Mengambil daftar seluruh bundel pengesahan SPJ.
- **Response Structure:** Sesuai 100% dengan kebutuhan tampilan `Bundles/Index.vue`.

#### `POST /api/v1/bundles`
Membuat bundel pengajuan SPP baru (Tier 2).
- **Payload:**
  ```json
  {
    "kuitansi_ids": [14, 15],
    "jenis_bundel": "SPP_GU",
    "tahun_anggaran": 2026,
    "bulan_periode": 6
  }
  ```
- **Proteksi Anti-Klaim Ganda:** Menolak jika ada kuitansi yang telah aktif di bundel lain atau kuitansi yang belum berstatus `LOLOS_VERIFIKASI`.

#### `POST /api/v1/bundles/{id}/unbundle/{kuitansiId}`
Mengeluarkan kuitansi bermasalah dari bundel tanpa merusak bundel SPP utama. Status kuitansi dipulihkan ke `LOLOS_VERIFIKASI` agar bisa diperbaiki dan diajukan ulang.

#### `POST /api/v1/bundles/{id}/sp2d`
Mencatat nomor SP2D SIPD-RI definitif.
- **Payload:**
  ```json
  {
    "nomor_sp2d": "0451/SP2D-GU/4.01.01/2026",
    "nomor_spp": "001/SPP-GU/4.01.01/2026",
    "tanggal_sp2d": "2026-06-15"
  }
  ```
- **Efek:** Bundel berubah menjadi `SYNCED_SIPD`, kuitansi menjadi `SELESAI_SP2D`, dan pagu terkunci dieksekusi menjadi realisasi definitif via `settleBudget()`.

---

### 2.5 Modul Verifikasi Publik QR Code & Lembar Kendali

#### `GET /verify/{qr_token}`
Endpoint otentikasi publik yang dibuka saat QR Code pada Lembar Kendali fisik dipindai oleh auditor atau masyarakat:
- **Akses:** Publik (tanpa login).
- **Perlindungan Data Pribadi (UU PDP):** Identitas rekanan otomatis disamarkan (misal: `CV S**** G****`, NPWP `01.***.***.*-***.***`, NIP `1985************08`).
- **Respon:** Menghasilkan halaman antarmuka web Inertia `VerifyReceipt.vue` jika dibuka lewat browser mobile, atau JSON jika menyertakan header `Accept: application/json`.

---

## 3. Cara Mengalihkan Service Frontend dari Mock ke Live API

Berikut adalah kode adapter siap pakai untuk memperbarui file service di `resources/js/Services/`:

### A. Update `resources/js/Services/receiptService.js`
```javascript
import axios from 'axios';

export const receiptService = {
    // Validasi simulasi draft via live Tax Calculation Engine
    async validateDraft(payload) {
        const response = await axios.post('/api/v1/receipts/validate-draft', {
            anggaran_rekening_id: payload.anggaran_rekening_id,
            bulan_anggaran: payload.bulan_anggaran,
            kategori_belanja: payload.kategori_belanja || 'B.1',
            has_npwp: payload.has_npwp ?? true,
            items: payload.items,
        });
        return response.data.data;
    },

    // Simpan draft kuitansi & berkas fisik ber-hash SHA-256
    async saveReceipt(formData) {
        const response = await axios.post('/api/v1/receipts', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },

    // Ajukan kuitansi ke PPTK (Kunci pagu RAK)
    async submitToPptk(kuitansiId) {
        const response = await axios.post(`/api/v1/receipts/${kuitansiId}/submit`);
        return response.data;
    }
};
```

### B. Update `resources/js/Services/verificationService.js`
```javascript
import axios from 'axios';

export const verificationService = {
    async getQueue(filters = {}) {
        const response = await axios.get('/api/v1/verification/queue', { params: filters });
        return response.data.data;
    },

    async getDetail(receiptId) {
        const response = await axios.get(`/api/v1/verification/${receiptId}`);
        return response.data.data;
    },

    async submitAction(receiptId, actionData) {
        const response = await axios.post(`/api/v1/verification/${receiptId}/action`, {
            action: actionData.action,
            notes: actionData.notes,
            checklist_results: actionData.checklist_results || [],
        });
        return response.data.data;
    }
};
```

### C. Update `resources/js/Services/budgetService.js`
```javascript
import axios from 'axios';

export const budgetService = {
    async getDashboardSummary() {
        const response = await axios.get('/api/v1/budget/summary');
        return response.data.data;
    },

    async getUnits() {
        const response = await axios.get('/api/v1/budget/units');
        return response.data.data;
    },

    async getSubActivitiesByUnit(unitId) {
        const response = await axios.get('/api/v1/budget/sub-activities', { params: { unit_id: unitId } });
        return response.data.data;
    },

    async getRakContext(rekeningId, selectedMonth) {
        const response = await axios.get(`/api/v1/budget/rak-context/${rekeningId}`, {
            params: { month: selectedMonth }
        });
        return response.data.data;
    }
};
```

---

## 4. Penanganan Error Rekomendasi di Frontend

```javascript
try {
    await receiptService.saveReceipt(formData);
    // Tampilkan notifikasi sukses toast
} catch (error) {
    if (error.response?.data?.error?.code === 'DUPLICATE_RECEIPT_ATTACHMENT') {
        // Tampilkan modal peringatan nota kembar SHA-256
        alert(`Peringatan Integritas: ${error.response.data.error.message}`);
    } else if (error.response?.data?.error?.code === 'PAGU_OVERLIMIT') {
        // Tampilkan dialog kuota pagu tidak mencukupi
        alert(`Pagu Melampaui Batas: ${error.response.data.error.message}`);
    } else {
        alert(error.response?.data?.error?.message || 'Terjadi kesalahan sistem.');
    }
}
```

