# ADR-006: Server-Side Offline PDF Generation and Pure SVG QR Code Verification

## Status
Accepted

## Date
2026-11-08

## Context
Lembar Kendali Verifikasi Kuitansi adalah dokumen hukum penatausahaan keuangan daerah di Pemerintah Kabupaten Garut yang wajib dicetak sebagai lampiran fisik pengajuan SPP/SPM.

Terdapat dua tantangan utama dalam implementasi dokumen ini:
1. **Tata Letak Cetak Presisi:** Dokumen harus mematuhi format dinas resmi Kecamatan Tarogong Kidul, termasuk kop surat resmi, tabel data transaksi, matriks kelengkapan 35 checklist verifikasi, dan tanda tangan digital/verifikator. Pustaka PDF berbasis browser (`window.print`) sering kali mengalami inkonsistensi rendering antar peramban atau layout bergeser saat dicetak.
2. **Ketergantungan Jaringan QR Code:** Verifikasi keaslian dokumen menggunakan kode QR yang dapat dipindai. Penggunaan layanan API pihak ketiga (misalnya `api.qrserver.com` atau Google Charts API) memperkenalkan risiko kegagalan rendering saat server intranet kantor kecamatan terputus dari internet (*air-gapped* atau jaringan pemda lambat), serta berpotensi membocorkan URL internal.

## Decision
1. Menggunakan **`barryvdh/laravel-dompdf` (DomPDF)** untuk menghasilkan berkas PDF secara server-side dengan tata letak berbasis **CSS 2.1 Table Layout** yang kompatibel penuh dengan mesin rendering DomPDF.
2. Menggunakan **`bacon/bacon-qr-code`** untuk men-generate kode QR secara offline murni dalam format **vektor SVG** tanpa panggilan jaringan eksternal. SVG disisipkan langsung ke dalam template Blade sebagai raw markup inline.
3. Mengonfigurasi `config/dompdf.php` dengan opsi ketat `enable_remote => false` untuk mencegah serangan SSRF (*Server-Side Request Forgery*).

## Consequences
- Lembar Kendali PDF dapat diunduh dan dicetak secara konsisten 100% pada semua sistem operasi dan printer dinas.
- Keamanan dan kemandirian sistem terjamin karena QR Code dibuat langsung dari memori server tanpa internet.
- Template Blade CSS 2.1 table-based layout terbebas dari glitch page-break atau flexbox yang tidak didukung oleh DomPDF.

