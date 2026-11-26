# DESIGN.md — Panduan Desain Antarmuka SI-TALITI v1.0
## Standar Desain Sistem, Audit Visual, dan Pedoman Anti-AI-Slop untuk Frontend Lead (Developer 2)

> **Tujuan Dokumen**: Memberikan panduan desain resmi agar antarmuka SI-TALITI v2.0 memiliki wibawa institusional (*institutional authority*), kejelasan fungsional tinggi (*high-density financial clarity*), dan bebas dari pola klise template AI generik (*anti-slop*).

---

## 1. Audit Desain Awal (Evaluasi Komponen Developer 2)

Rekan Anda telah meletakkan fondasi fungsional yang sangat baik (tema warna *Deep Navy*, struktur navigasi rapi, dan pemisahan modul yang jelas). Namun, setelah diaudit menggunakan kaidah visual modern dan filter *Anti-Slop*, ditemukan beberapa pola generik yang perlu ditingkatkan:

### ⚠️ Temuan Audit Visual

| Aspek | Kondisi Saat Ini (Telltale AI-Slop) | Dampak pada Pengguna | Solusi Perbaikan (Standardisasi) |
|---|---|---|---|
| **Tipografi** | Menggunakan font bawaan `Inter` di `tailwind.config.js`. Ukuran teks arbitrer (`text-[10px]`, `text-[11px]`) tersebar di banyak tempat. | Terasa seperti template SaaS komersial biasa, bukan aplikasi tata kelola keuangan daerah yang resmi. | Gunakan **Plus Jakarta Sans** (standar de-facto civic-tech & instansi pemerintah Indonesia), dipadukan dengan **JetBrains Mono** untuk angka keuangan. |
| **Kelebihan Aksen (Rainbow Cards)** | Di `Dashboard.vue`, 4 kartu KPI memiliki 4 warna berbeda: Navy, Hijau (Emerald), Kuning/Cokelat (Amber), dan Biru (Sky). | Terlalu ramai dan menghilangkan fokus hierarki data belanja daerah yang bernilai belasan miliar rupiah. | Batasi aksen. Gunakan warna netral/navy sebagai *grounding*, aksen warna hanya berfungsi sebagai indikator status (bukan dekorasi latar). |
| **Animasi Berlebihan (Ping/Pulse Cliché)** | Indikator status menggunakan animasi `<span class="animate-ping">` dan `animate-pulse` pada titik status statis. | Mengalihkan fokus pemeriksa SPJ, memberi kesan sistem "sedang panik" atau simulasi mainan. | Hapus animasi ping berulang. Gunakan indikator dot solid dengan kontras jelas dan label teks yang lugas. |
| **Banner Hero Promosional** | Di atas KPI dashboard terdapat banner gradien `bg-gradient-to-r from-gov-navy-900 to-gov-navy-800` berisi teks edukasi. | Terlihat seperti banner promosi website *marketing*, bukan dashboard operasional harian bendahara. | Ganti dengan ringkasan status operasional ringkas atau hilangkan jika tidak memiliki aksi langsung. |
| **Interaksi Primitif (`window.alert`)** | Pada `Create.vue` dan `Workspace.vue`, validasi dan konfirmasi menggunakan `alert()` dan `confirm()` bawaan browser. | Memutus pengalaman pengguna, terlihat belum selesai, dan tidak ramah aksesibilitas. | Gunakan modal dialog konfirmasi internal (*In-App Modal*) dan *toast notification* non-intrusif. |
| **Kerapatan Informasi (Density)** | Padding kartu terlalu longgar (`p-5`, `p-6`) untuk antarmuka yang harus memeriksa ratusan transaksi dan checklist. | Verifikator dan operator harus sering melakukan scrolling berulang saat mencocokkan data fisik. | Terapkan *compact data density*: tabel dengan *row height* seimbang, font angka terbaca jelas, dan informasi sampingan kompak. |

---

## 2. Fondasi Visual (Design Tokens)

### 2.1 Palet Warna Resmi (Deep Civic Navy & Paper)

Palet warna SI-TALITI dirancang mencerminkan wibawa institusi pemerintah (*State Audit & Public Finance*), ketenangan visual, dan keterbacaan tinggi di berbagai monitor dinas:

```
canvas:         #F7F8FA   /* Latar belakang aplikasi (warm neutral) */
surface:        #FFFFFF   /* Latar belakang kartu, tabel, dan drawer */
raised:         #F1F3F6   /* Permukaan sekunder (header tabel, hover halus) */
hairline:       #E4E7EC   /* Garis batas kartu & pembatas kolom tabel */
ink:            #0F172A   /* Teks judul dan angka utama */
ink-secondary:  #475569   /* Label pendukung dan keterangan */
ink-muted:      #64748B   /* Teks mikro / metadata */
accent:         #1E3A5F   /* Aksen institusional primer (tombol utama, fokus) */
accent-hover:   #152A45   /* Hover state aksen primer */
accent-soft:    #EFF4FA   /* Latar lembut untuk state aktif */
accent-border:  #C7D6EA   /* Border lembut untuk state aktif */
```

#### Aturan Aksen Status Fungsional (Functional State Colors — `semantic.*`):
- **Lolos Verifikasi / Tersedia (`semantic.success`)**: `#047857` (Badge: `#ECFDF5`, Border: `#A7F3D0`).
- **Revisi Minor / Terkunci Komitmen (`semantic.warning`)**: `#B45309` (Badge: `#FFFBEB`, Border: `#FDE68A`).
- **Revisi Mayor / Ditolak / Overlimit (`semantic.danger`)**: `#B91C1C` (Badge: `#FEF2F2`, Border: `#FECACA`).
- **Informasi / Pagu Terkunci (`semantic.info`)**: `#1D4ED8` (Badge: `#EFF6FF`, Border: `#BFDBFE`).

> 🛑 **Anti-Slop Rule**: DILARANG menambahkan gradien ungu-biru, neon orbs, atau kartu dengan warna-warni pelangi. Warna di luar palet netral hanya boleh muncul jika membawa arti status belanja.

---

### 2.2 Tipografi & Angka Finansial

1. **Font UI Utama**: `Plus Jakarta Sans`, sans-serif.
   - Bersih, formal, dan memiliki keterbacaan sangat tinggi pada monitor resolusi rendah kantor kecamatan.
2. **Font Data Finansial & Kode Rekening**: `JetBrains Mono` atau `font-mono`.
   - **Wajib menggunakan `tabular-nums`**: Seluruh angka mata uang, nomor bukti, tanggal, dan persentase wajib menggunakan angka *tabular* agar tanda koma dan digit ribuan sejajar sempurna vertikal.
3. **Skala Tipografi**:
   - Judul Halaman: `text-xl font-semibold tracking-tight text-ink`
   - Subjudul / Seksi: `text-sm font-semibold text-ink`
   - Body & Label Form: `text-xs font-medium text-ink-secondary`
   - Angka Keuangan Utama: `text-xl font-bold font-mono tracking-tight text-ink tabular-nums`
   - Angka Keuangan Tabel: `text-xs font-semibold font-mono tabular-nums text-right`
   - Keterangan Mikro: `text-xs text-ink-muted` *(jangan lebih kecil dari 12px)*.

---

## 3. Standar Komponen & Layout Spesifik

### 3.1 Kartu Ringkasan (KPI Cards)
- **Tampilan**: Putih bersih (`bg-surface`), border tipis `border-hairline`, bayangan halus `shadow-subtle`, tanpa gradien mencolok.
- **Hierarki**: Label seksi di atas $\rightarrow$ Nilai angka font mono besar di tengah $\rightarrow$ Konteks penjelasan di bawah.
- **Rasio**: Sejajar vertikal. Tombol aksi berada di bawah dengan garis baseline rata.

### 3.2 Modal Gatekeeper RAK Selector (`RAKSelectorModal.vue`)
- **Tujuan**: Memastikan pengguna sadar alokasi pagu sebelum mengisi kuitansi.
- **Pola Desain**:
  - Grid 12 bulan harus memberi pembeda jelas:
    - Bulan lewat yang masih punya carry-over: Label hijau netral (*Ada Sisa*).
    - Bulan berjalan: Border kontras `border-accent` dengan tag *Aktif*.
    - Bulan depan: Abu-abu terarsir (*LOCKED_FUTURE*) dengan ikon gembok kecil dan kursor `not-allowed`.
  - Panel rincian di samping kanan menampilkan formula:
    $$\text{Pagu Murni} + \text{Sisa Carry-Over} - \text{Terkunci} = \text{Sisa Dapat Digunakan}$$

### 3.3 Dynamic Checklist Table (`DynamicChecklistTable.vue`)
- **Tampilan**: Mengadopsi struktur lembar verifikasi fisik BPK/Inspektorat (Sheet TARKID).
- **Elemen per Baris**:
  1. Nomor Butir (misal `B1.1`, `B1.2`).
  2. Nama Dokumen Wajib / Pendukung.
  3. Indikator Tag (`Wajib` = Badge Merah Halus, `Opsional` = Badge Abu-abu).
  4. Status Berkas (Belum Diunggah $\rightarrow$ Terunggah $\rightarrow$ Valid $\rightarrow$ Catatan Perbaikan).
  5. Aksi Upload / Preview PDF instan.
- **Upload Dropzone**: Kotak drag-and-drop kompak dengan petunjuk jelas: *"Format PDF / JPG, maks. 10MB"*. Tampilkan nama file dan ukuran aktual setelah berhasil.

### 3.4 Split-Screen Workspace (`Workspace.vue`)
- **Rasio Layar**:
  - **Sisi Kiri (55%)**: PDF Viewer dengan kontrol zoom (Fit Width, 100%, Zoom In/Out), rotasi, dan navigasi multi-halaman.
  - **Sisi Kanan (45%)**: Dokumen Checklist Interaktif & Drawer Catatan Temuan.
- **Tombol Keputusan**:
  - `[Lolos Verifikasi]`: Tombol Navy/Hijau tegas di sudut kanan bawah. Terkunci (*disabled*) otomatis jika ada dokumen wajib yang belum tercentang.
  - `[Kembalikan / Revisi]`: Tombol sekunder yang membuka slide-over drawer (*RevisionDrawer.vue*) untuk memasukkan butir temuan revisi minor atau penolakan mayor.
  - **Larangan**: Hapus seluruh pemanggilan `window.alert()`. Gunakan toast status atau banner feedback di dalam layout.

### 3.5 Lembar Kendali Verifikasi (`ControlSheet/Show.vue`)
- **Tampilan Layar**: Terlihat seperti kertas dokumen resmi dinas di atas latar belakang abu-abu arsip.
- **Format Cetak (`@media print`)**:
  - Sembunyikan seluruh tombol navigasi, header sistem, dan tombol cetak (`print:hidden`).
  - Hapus background abu-abu (`print:bg-white`).
  - Tampilkan Kop Resmi Pemkab Garut dan Kecamatan Tarogong Kidul lengkap.
  - Cetak QR Code di pojok kanan bawah dengan teks pengesahan digital: *"Dokumen ini telah diverifikasi akuntabilitasnya melalui sistem SI-TALITI v2.0."*

---

## 4. Panduan Interaksi & Mikro-Animasi (Tactile Feedback)

1. **Tombol (*GovButton*)**:
   - Efek Hover: Perubahan warna satu tingkat lebih gelap (`hover:bg-accent-hover`), transisi `duration-150 ease-unslop`.
   - Efek Klik: Umpan balik fisik taktil menggunakan `active:scale-[0.98]` atau `active:translate-y-px`.
   - Efek Fokus: Ring fokus kontras untuk navigasi keyboard aksesibel (`focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1`).
2. **Animasi Transisi**:
   - Modal & Slide-over Drawer: Masuk dari kanan secara halus dengan kurva *ease-out* (`transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1)`).
   - Dilarang menggunakan animasi berputar atau melayang tanpa henti di halaman operasional.

---

## 5. Checklist Verifikasi Desain untuk Developer 2

Sebelum mengajukan Pull Request (*PR*) terkait antarmuka, pastikan komponen memenuhi checklist berikut:

- [ ] **Bebas Alert Browser**: Tidak ada penggunaan `alert()`, `confirm()`, atau `prompt()`. Semua validasi muncul inline atau via modal internal.
- [ ] **Tabular Angka**: Seluruh nominal rupiah, volume, harga satuan, dan persentase menggunakan class `tabular-nums` dan font mono yang rapi.
- [ ] **Bebas Animasi Ping/Pulse**: Tidak ada elemen yang berkedip terus-menerus tanpa henti di luar proses loading aktif.
- [ ] **Aksen Terkendali**: Tidak ada kartu yang menggunakan warna pelangi. Warna hanya digunakan untuk membedakan status kuitansi (*DRAFT*, *MENUNGGU*, *LOLOS*, *REVISI*).
- [ ] **Kerapatan Seimbang**: Tabel daftar dokumen dan kuitansi memiliki padding kompak sehingga nyaman dibaca dalam sesi kerja panjang.
- [ ] **Dukungan Cetak Kertas**: Halaman Lembar Kendali (`/control-sheet/{id}`) rapi dan presisi saat dibuka jendela cetak browser (Ctrl+P / Cmd+P).

