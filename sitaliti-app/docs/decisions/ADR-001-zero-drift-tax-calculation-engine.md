# ADR-001: Zero-Drift DPP & Tax Calculation Formula

## Status
Accepted

## Date
2026-11-05

## Context
Dalam tata kelola penatausahaan keuangan daerah di Indonesia, kuitansi transaksi belanja sering kali disajikan oleh rekanan/toko dalam bentuk nominal bruto (sudah termasuk PPN 11%). Formula matematika sederhana $\text{DPP} = \text{Bruto} / 1.11$ dan $\text{PPN} = \text{DPP} \times 0.11$ pada banyak implementasi perangkat lunak sering menghasilkan selisih pembulatan (*rounding drift*) sebesar Rp 1. 

Sebagai contoh, untuk nilai belanja bruto Rp 2.220.000:
- $\text{DPP} = 2.220.000 / 1.11 = 2.000.000$
- Jika PPN dihitung dari $\text{round}(2.000.000 \times 0.11) = 220.000$
- Namun pada angka ganjil seperti Rp 100.000:
  - $\text{DPP} = \text{round}(100.000 / 1.11) = 90.090$
  - $\text{PPN} = \text{round}(90.090 \times 0.11) = 9.910$
  - $\text{Total} = 90.090 + 9.910 = 100.000$ (Seimbang)
  - Namun jika PPN dihitung terpisah tanpa mengikat kembali ke Bruto, penjumlahan $\text{DPP} + \text{PPN}$ dapat menjadi Rp 99.999 atau Rp 100.001, yang menjadi objek temuan auditor BPK RI.

Selain itu, PMK 59/2022 mengatur bahwa transaksi belanja barang/ATK $\le \text{Rp } 2.000.000$ dibebaskan dari pemungutan PPN dan PPh 22, dan UU HKPD No. 1/2022 Pasal 58 menetapkan bahwa makanan dan minuman restoran/katering hanya dikenakan PBJT Daerah 10% dan mutlak dibebaskan dari PPN Pusat 11%.

## Decision
Menerapkan formula deterministik *Zero-Rounding Drift*:
1. $\text{DPP} = \text{round}(\text{Nominal Bruto} / 1.11)$
2. $\text{PPN} = \text{Nominal Bruto} - \text{DPP}$
3. Seluruh potongan pajak diikat dalam invarian ketat:
   $$\text{Nominal Neto} = \text{Nominal Bruto} - \sum \text{Pajak Dipungut}$$
4. Menerapkan *Tax Rules Engine* terisolasi pada `App\Services\TaxCalculationService` yang mengimplementasikan `TaxCalculationServiceInterface`, dengan pengujian unit otomatis sebelum data disimpan ke basis data.

## Consequences
- Mutlak menjamin tidak ada selisih Rp 1 antara Bruto dengan penjumlahan DPP dan PPN.
- Memenuhi regulasi perundang-undangan (PMK 59/2022, UU HKPD 1/2022, PP 80/2010) secara otomatis tanpa bergantung pada ketelitian manual operator.
- Keseimbangan matematika ditegakkan ganda melalui *Database Check Constraint* di tabel `kuitansi`:
  `CHECK (nominal_neto = nominal_bruto - total_pajak)`.

