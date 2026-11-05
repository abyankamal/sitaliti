# Capability Map: SI-TALITI v2.0 (Backend & Settlement Engine)

Dokumen ini memetakan seluruh kapabilitas sistem yang dapat diuji secara independen (*independently testable modules*), dependensi antar-modul, serta urutan pembangunannya (*build order*).

---

## 1. Modul Kapabilitas

| Module ID | Tanggung Jawab (Responsibility) | Dependensi | Konsumen Utama |
|---|---|---|---|
| `mod-budget-rak` | Master unit kerja (6 unit), hierarki 39 subkegiatan, 132 rekening leaf, streaming parser Excel RAK (`openspout`), dan penyedia alokasi 12 bulan. | — | `RAKSelectorModal.vue`, `Dashboard.vue` |
| `mod-tax-engine` | Master regulasi pajak (`tax_rules`), perhitungan DPP, PPN 11%, PBJT Mamin 10% (mutually exclusive), PPh 21/22/23, Bea Meterai Rp10.000 flat, dan audit pemotongan pajak. | — | `Create.vue`, `Workspace.vue` |
| `mod-budget-reservation` | Atomic pessimistic locking (`lockForUpdate`), pencegahan defisit kumulatif masa depan (bulan $m \dots 12$), reservasi pagu saat diajukan ke PPTK, dan pelepasan pagu saat penolakan. | `mod-budget-rak` | `receiptService.js`, `ReceiptController` |
| `mod-receipt-core` | Transaksi kuitansi atomik (Tier 1), rincian belanja barang, upload berkas privat, dan deteksi duplikasi nota seketika (*synchronous SHA-256 fail-fast*). | `mod-budget-reservation`, `mod-tax-engine` | `Create.vue`, `receiptService.js` |
| `mod-verification-workspace` | Master checklist fisik (B.1–B.6), persistensi hasil verifikasi kuitansi, streaming aman PDF viewer (55%), state machine revisi minor/mayor, audit trail Spatie, dan pelacak SLA 2 hari kerja. | `mod-receipt-core` | `Workspace.vue`, `RevisionDrawer.vue` |
| `mod-settlement-bundling` | Pengelompokan kuitansi lolos verifikasi ke bundel Tier 2 (SPP-GU, TU, LS, LPJ-BPP), MySQL active virtual column unique constraint anti-klaim ganda, dan sinkronisasi SIPD-RI. | `mod-verification-workspace` | `Bundles/Index.vue` |
| `mod-control-sheet-qr` | Generator Lembar Kendali ber-QR token unik (UUID/CSPRNG) dan portal publik `/verify/{qr_token}` dengan penyensoran data pribadi (*vendor PII redaction*). | `mod-verification-workspace` | `ControlSheet/Show.vue`, Publik/Auditor |

---

## 2. Urutan Pembangunan (Build Order)

```
[mod-budget-rak] ─────────────┬───────────► [mod-budget-reservation] ──┐
                              │                                        │
[mod-tax-engine] ─────────────┼────────────────────────────────────────┼──► [mod-receipt-core] ──► [mod-verification-workspace] ──┬──► [mod-settlement-bundling]
                              │                                                                                                    │
                              └────────────────────────────────────────────────────────────────────────────────────────────────────┴──► [mod-control-sheet-qr]
```

**Tahapan Eksekusi:**
1. **Fondasi Anggaran & Pajak**: `mod-budget-rak` & `mod-tax-engine`
2. **Mesin Penguncian Pagu**: `mod-budget-reservation`
3. **Transaksi Kuitansi Atomik**: `mod-receipt-core`
4. **Workspace Verifikasi & Loopback**: `mod-verification-workspace`
5. **Settlement & Output Resmi**: `mod-settlement-bundling` & `mod-control-sheet-qr`

