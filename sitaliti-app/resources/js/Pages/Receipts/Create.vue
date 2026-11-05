<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovInput from '@/Components/UI/GovInput.vue';
import GovCurrencyInput from '@/Components/UI/GovCurrencyInput.vue';
import DynamicChecklistTable from '@/Modules/Checklist/DynamicChecklistTable.vue';
import { formatRupiah } from '@/Utils/currency';
import { receiptService } from '@/Services/receiptService';

const budgetContext = ref(null);
const checklistItems = ref([]);
const isSubmitting = ref(false);
const validationResult = ref(null);

// Form State
const form = ref({
    nomor_bukti: '',
    tanggal_bukti: new Date().toISOString().split('T')[0],
    nama_penerima: '',
    npwp_penerima: '',
    uraian_belanja: '',
    items: [
        { nama_barang: '', volume: 1, satuan: 'Pcs', harga_satuan: 0 }
    ],
    tax_settings: {
        apply_ppn: false,
        pph21_rate: 0,
        apply_pph22: false,
        apply_pph23: false,
    }
});

onMounted(async () => {
    // Ambil konteks anggaran dari sessionStorage yang dipilih via Gatekeeper
    const saved = sessionStorage.getItem('sitaliti_budget_context');
    if (saved) {
        budgetContext.value = JSON.parse(saved);
    } else {
        // Fallback default jika diakses langsung tanpa lewat Gatekeeper
        budgetContext.value = {
            unit: { name: 'Sekretariat Kecamatan Tarogong Kidul', code: '4.01.0.00.0.00.01.0000' },
            subActivity: { name: 'Penyediaan Barang Cetakan dan Penggandaan', code: '4.01.01.2.01.0006' },
            account: { name: 'Belanja Alat/Bahan untuk Kegiatan Kantor - Kertas dan Cover', code: '5.1.02.01.01.0024', kategori: 'B.1' },
            month: 6,
            monthName: 'Juni',
            rakContext: {
                sisa_dapat_digunakan: 64500000,
                total_tersedia: 76500000,
                terkunci_komitmen: 12000000,
            }
        };
    }

    // Load checklist dokumen sesuai kategori belanja
    const catCode = budgetContext.value.account?.kategori || 'B.1';
    checklistItems.value = await receiptService.getChecklistByCategory(catCode);

    // Auto-generate nomor bukti draft
    form.value.nomor_bukti = `KW-2026-${String(budgetContext.value.month).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`;
});

// Perhitungan Nilai Belanja
const totalNilaiBelanja = computed(() => {
    return form.value.items.reduce((sum, item) => {
        return sum + (Number(item.volume || 0) * Number(item.harga_satuan || 0));
    }, 0);
});

// Simulasi Pajak Frontend (Source of Truth tetap validasi backend saat submit)
const estimasiPajak = computed(() => {
    const total = totalNilaiBelanja.value;
    const ppn = form.value.tax_settings.apply_ppn ? Math.round(total * 0.11) : 0;
    const pph21 = form.value.tax_settings.pph21_rate ? Math.round(total * (Number(form.value.tax_settings.pph21_rate) / 100)) : 0;
    const pph22 = form.value.tax_settings.apply_pph22 ? Math.round(total * 0.015) : 0;
    const pph23 = form.value.tax_settings.apply_pph23 ? Math.round(total * 0.02) : 0;
    const totalPajak = ppn + pph21 + pph22 + pph23;

    return {
        ppn,
        pph21,
        pph22,
        pph23,
        totalPajak,
        totalNetto: total - (pph21 + pph22 + pph23) // Pembayaran bersih setelah dipotong PPh
    };
});

const isExceedingBudget = computed(() => {
    const sisa = budgetContext.value?.rakContext?.sisa_dapat_digunakan || 0;
    return totalNilaiBelanja.value > sisa;
});

function addItemRow() {
    form.value.items.push({
        nama_barang: '',
        volume: 1,
        satuan: 'Pcs',
        harga_satuan: 0
    });
}

function removeItemRow(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

async function handleSubmitDraft() {
    isSubmitting.value = true;
    validationResult.value = null;

    try {
        const payload = {
            ...form.value,
            budget_context: budgetContext.value,
            checklist: checklistItems.value,
        };

        // Kirim ke backend validation layer
        const result = await receiptService.validateDraft(payload);
        validationResult.value = result;

        if (result.success) {
            const saveRes = await receiptService.saveReceipt(payload);
            alert(`Berhasil! ${saveRes.message} (Nomor Bukti: ${saveRes.receipt_id})`);
            router.visit(route('dashboard'));
        }
    } catch (err) {
        alert('Terjadi kesalahan saat menyimpan draft.');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="Input Kuitansi Belanja Baru" />

    <GovAppLayout>
        <!-- Top Page Header -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('dashboard')" class="text-xs text-gov-navy-700 hover:text-gov-navy-900 font-medium">
                            ← Kembali ke Dashboard
                        </Link>
                    </div>
                    <h1 class="text-lg font-bold text-gov-navy-950 mt-1 flex items-center gap-2">
                        <span>Form Pertanggungjawaban Kuitansi Belanja</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-gov-navy-100 text-gov-navy-800 font-mono">
                            Pre-Audit Entry
                        </span>
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <GovButton variant="secondary" size="sm" @click="router.visit(route('dashboard'))">
                        Batal
                    </GovButton>
                    <GovButton
                        variant="gold"
                        size="sm"
                        :loading="isSubmitting"
                        :disabled="isExceedingBudget || totalNilaiBelanja === 0"
                        @click="handleSubmitDraft"
                    >
                        Simpan & Ajukan Verifikasi
                    </GovButton>
                </div>
            </div>
        </template>

        <div v-if="budgetContext" class="space-y-6">
            <!-- Pagu Context Header Card (Kunci dari Gatekeeper) -->
            <div class="bg-gov-navy-950 text-white rounded-xl p-5 shadow-sm border border-gov-navy-800">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gov-gold-400">
                                Konteks Anggaran Terkunci (Gatekeeper)
                            </span>
                            <span class="text-[10px] bg-gov-navy-800 px-2 py-0.5 rounded border border-slate-700 font-mono">
                                Alokasi Bln {{ budgetContext.month }} - {{ budgetContext.monthName }} 2026
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-100">
                            {{ budgetContext.unit?.name }}
                        </h3>
                        <p class="text-xs text-slate-300">
                            Subkegiatan: <span class="font-medium text-slate-200">{{ budgetContext.subActivity?.name }}</span>
                        </p>
                        <p class="text-xs text-slate-400 font-mono">
                            Rekening: <strong class="text-gov-gold-300">[{{ budgetContext.account?.kategori }}] {{ budgetContext.account?.code }}</strong> - {{ budgetContext.account?.name }}
                        </p>
                    </div>

                    <div class="bg-gov-navy-900/80 p-3.5 rounded-lg border border-gov-navy-800 md:text-right shrink-0">
                        <span class="text-[11px] text-slate-400 block">Sisa Plafon Pagu Bebas</span>
                        <div class="text-xl font-bold text-emerald-400 tabular-nums">
                            {{ formatRupiah(budgetContext.rakContext?.sisa_dapat_digunakan) }}
                        </div>
                        <span class="text-[10px] text-slate-400">Termasuk Carry-Over bulan lalu</span>
                    </div>
                </div>
            </div>

            <!-- Error Banner jika transaksi melebihi pagu -->
            <div v-if="isExceedingBudget" class="p-4 bg-rose-50 border border-rose-300 rounded-xl text-rose-800 text-xs flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <strong>Pagu RAK Tidak Mencukupi:</strong> Total belanja ({{ formatRupiah(totalNilaiBelanja) }}) melampaui sisa pagu bulan berjalan ({{ formatRupiah(budgetContext.rakContext?.sisa_dapat_digunakan) }}). Silakan kurangi volume barang atau sesuaikan alokasi.
                </div>
            </div>

            <!-- Form Bagian 1: Metadata Transaksi Kuitansi -->
            <GovCard title="Data Umum Kuitansi & Transaksi" subtitle="Informasi tanda bukti pembayaran">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <GovInput
                        v-model="form.nomor_bukti"
                        label="Nomor Bukti Kuitansi"
                        placeholder="Contoh: KW-2026-06-0001"
                        :required="true"
                    />

                    <GovInput
                        v-model="form.tanggal_bukti"
                        type="date"
                        label="Tanggal Kuitansi"
                        :required="true"
                    />

                    <GovInput
                        v-model="form.nama_penerima"
                        label="Penerima / Nama Toko / Penyedia"
                        placeholder="Nama CV/PT/Toko Penyedia"
                        :required="true"
                    />

                    <GovInput
                        v-model="form.npwp_penerima"
                        label="NPWP / NIK Penerima"
                        placeholder="00.000.000.0-000.000"
                        help="Wajib untuk penerima wajib pajak"
                    />

                    <div class="md:col-span-2 lg:col-span-4">
                        <label class="block text-xs font-semibold text-gov-navy-900 uppercase tracking-wider mb-1.5">
                            Uraian Belanja Kuitansi <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="form.uraian_belanja"
                            rows="2"
                            placeholder="Jelaskan peruntukan pengeluaran belanja secara lengkap dan akuntabel..."
                            class="block w-full rounded-lg border-gov-surface-border px-3.5 py-2 text-sm text-gov-navy-900 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800"
                        ></textarea>
                    </div>
                </div>
            </GovCard>

            <!-- Form Bagian 2: Rincian Barang / Jasa (Dynamic Rows) -->
            <GovCard
                title="Rincian Barang & Jasa Belanja"
                subtitle="Daftar item volume, satuan, dan harga per unit"
                :no-padding="true"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gov-surface-border text-left">
                        <thead class="bg-gov-surface-subtle text-[11px] uppercase tracking-wider font-semibold text-slate-600">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4">Nama Barang / Uraian Rincian</th>
                                <th class="py-3 px-4 w-28 text-right">Volume</th>
                                <th class="py-3 px-4 w-28">Satuan</th>
                                <th class="py-3 px-4 w-44 text-right">Harga Satuan</th>
                                <th class="py-3 px-4 w-44 text-right">Jumlah Total</th>
                                <th class="py-3 px-4 w-16 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gov-surface-border text-xs">
                            <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-slate-50">
                                <td class="py-2 px-4 text-center text-slate-400 font-medium">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-2 px-4">
                                    <input
                                        v-model="item.nama_barang"
                                        type="text"
                                        placeholder="Contoh: Kertas HVS A4 80gr"
                                        class="w-full text-xs rounded border-gov-surface-border py-1.5 px-2.5 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right">
                                    <input
                                        v-model.number="item.volume"
                                        type="number"
                                        min="1"
                                        class="w-full text-xs text-right rounded border-gov-surface-border py-1.5 px-2.5 tabular-nums focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800"
                                    />
                                </td>
                                <td class="py-2 px-4">
                                    <input
                                        v-model="item.satuan"
                                        type="text"
                                        placeholder="Rim/Kotak"
                                        class="w-full text-xs rounded border-gov-surface-border py-1.5 px-2.5 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right">
                                    <GovCurrencyInput
                                        v-model="item.harga_satuan"
                                        placeholder="0"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right font-bold text-gov-navy-950 tabular-nums">
                                    {{ formatRupiah(Number(item.volume || 0) * Number(item.harga_satuan || 0)) }}
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <button
                                        type="button"
                                        @click="removeItemRow(idx)"
                                        :disabled="form.items.length === 1"
                                        class="text-slate-400 hover:text-rose-600 disabled:opacity-30 disabled:hover:text-slate-400"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template #footer>
                    <div class="flex items-center justify-between">
                        <GovButton variant="secondary" size="sm" @click="addItemRow">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Tambah Baris Barang
                        </GovButton>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold uppercase text-slate-500">Subtotal Belanja:</span>
                            <span class="text-base font-bold text-gov-navy-950 tabular-nums">
                                {{ formatRupiah(totalNilaiBelanja) }}
                            </span>
                        </div>
                    </div>
                </template>
            </GovCard>

            <!-- Form Bagian 3: Simulasi Pajak & Potongan (Preview) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Sisi Kiri (2 Kolom): Opsi Pajak -->
                <div class="lg:col-span-2">
                    <GovCard title="Simulasi Pengenaan Pajak (PPh & PPN)" subtitle="Penyesuaian kewajiban perpajakan">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.tax_settings.apply_ppn"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Pungut PPN (11%)</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">Untuk transaksi belanja barang di atas Rp2.000.000 atau penyedia PKP</p>
                            </div>

                            <div class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.tax_settings.apply_pph22"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Potong PPh Pasal 22 (1,5%)</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">Pengadaan barang pemerintah di atas Rp2.000.000</p>
                            </div>

                            <div class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.tax_settings.apply_pph23"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Potong PPh Pasal 23 (2%)</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">Jasa teknik, manajemen, pemeliharaan, dan katering</p>
                            </div>

                            <div class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="block font-semibold text-gov-navy-900 mb-1">
                                    Potong PPh Pasal 21 (Honorarium)
                                </label>
                                <select
                                    v-model="form.tax_settings.pph21_rate"
                                    class="w-full text-xs rounded border-gov-surface-border py-1 px-2 focus:border-gov-navy-800"
                                >
                                    <option :value="0">0% (Bukan Objek PPh 21 / PNS Gol I & II)</option>
                                    <option :value="5">5% (PNS Golongan III / NPWP)</option>
                                    <option :value="15">15% (PNS Golongan IV / Pejabat Negara)</option>
                                </select>
                            </div>
                        </div>
                    </GovCard>
                </div>

                <!-- Sisi Kanan (1 Kolom): Rekapitulasi Kasir -->
                <div>
                    <div class="bg-gov-navy-900 text-white rounded-xl p-5 shadow-sm space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gov-gold-400 border-b border-gov-navy-800 pb-2">
                            Ringkasan Pembayaran & Kas
                        </h4>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-300">Nilai Bruto Kuitansi:</span>
                                <span class="font-semibold tabular-nums">{{ formatRupiah(totalNilaiBelanja) }}</span>
                            </div>

                            <div v-if="estimasiPajak.ppn > 0" class="flex justify-between text-slate-300">
                                <span>PPN 11%:</span>
                                <span class="tabular-nums">+ {{ formatRupiah(estimasiPajak.ppn) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph21 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 21:</span>
                                <span class="tabular-nums">- {{ formatRupiah(estimasiPajak.pph21) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph22 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 22:</span>
                                <span class="tabular-nums">- {{ formatRupiah(estimasiPajak.pph22) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph23 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 23:</span>
                                <span class="tabular-nums">- {{ formatRupiah(estimasiPajak.pph23) }}</span>
                            </div>

                            <div class="pt-3 border-t border-gov-navy-800">
                                <div class="text-[11px] text-slate-400">Total Yang Dibayarkan Riil (Netto):</div>
                                <div class="text-xl font-black text-gov-gold-400 tabular-nums mt-0.5">
                                    {{ formatRupiah(estimasiPajak.totalNetto) }}
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 italic pt-1 leading-tight">
                            *Catatan: Simulasi kalkulasi ini merupakan preview frontend. Validasi resmi potongan pajak final dilakukan oleh sistem backend saat pengesahan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Bagian 4: Dynamic Checklist Dokumen B.1 - B.6 -->
            <DynamicChecklistTable
                :category-code="budgetContext.account?.kategori || 'B.1'"
                :category-name="budgetContext.account?.name"
                v-model:items="checklistItems"
            />
        </div>
    </GovAppLayout>
</template>

