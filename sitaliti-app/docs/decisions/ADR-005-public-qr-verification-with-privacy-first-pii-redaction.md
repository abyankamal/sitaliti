# ADR-005: Public QR Verification with Privacy-First PII Redaction

## Status
Accepted

## Date
2026-11-07

## Context
Setiap Lembar Kendali Verifikasi fisik yang sah memuat QR Code unik (`/verify/{qr_token}`). Auditor, inspektorat, atau masyarakat dapat memindai QR Code tersebut untuk membuktikan keaslian dokumen tanpa harus memiliki akun login sistem (*unauthenticated access*).

Namun, dokumen kuitansi memuat Data Pribadi (Personally Identifiable Information / PII) rekanan seperti Nomor Pokok Wajib Pajak (NPWP), nama pemilik usaha perorangan, dan Nomor Induk Pegawai (NIP) ASN yang tunduk pada Undang-Undang Perlindungan Data Pribadi (UU PDP). Menampilkan data sensitif secara gamblang ke publik melanggar regulasi privasi.

## Decision
Menerapkan endpoint verifikasi publik adaptif dengan penyensoran data otomatis:
1. Endpoint `GET /verify/{qr_token}` dibuka bebas autentikasi.
2. Respons menghasilkan antarmuka web Inertia (`VerifyReceipt.vue`) jika diakses via peramban seluler, atau JSON jika menyertakan header `Accept: application/json`.
3. Seluruh atribut sensitif diproses melalui filter redaksi:
   - **NPWP**: Mempertahankan 2 karakter pertama, menyamarkan angka selebihnya (`01.***.***.*-***.***`).
   - **Nama Rekanan**: Mempertahankan badan usaha (`CV`, `PT`), menyamarkan kata nama (`CV S**** G****`).
   - **NIP Pegawai**: Mempertahankan 4 digit awal dan 2 digit akhir (`1985************08`).
4. Informasi yang dipublikasikan secara transparan dibatasi pada: Status keabsahan (*Lolos Verifikasi*, *Tergabung di SPP*, *Terbit SP2D*), nama unit kerja, subkegiatan, uraian belanja, nilai nominal bruto/pajak/neto, dan stempel digital tim verifikator.

## Consequences
- Akses audit fisik sangat mudah dan cepat bagi auditor lapangan melalui kamera smartphone.
- Mencegah kebocoran data identitas dan informasi perpajakan rekanan.
- Memenuhi amanat keterbukaan informasi publik keuangan daerah sekaligus kepatuhan penuh terhadap UU PDP.

