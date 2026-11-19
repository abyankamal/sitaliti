<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
const savedReceiptId = ref(null);
const isValidatingTax = ref(false);
const validationResult = ref(null);
const feedbackBanner = ref(null);

// Form State
const form = ref({
    nomor_bukti: '',
    tanggal_bukti: new Date().toISOString().split('T')[0],
    nama_penerima: '',
    npwp_penerima: '',
    has_npwp: false,
    golongan_asn: 'GOLONGAN_III',
    is_sewa_kendaraan: false,
    is_pkp: true,
    uraian_belanja: '',
    items: [
        { nama_barang: '', volume: 1, satuan: 'Pcs', harga_satuan: 0 },
    ],
});

const categoryCode = computed(() => budgetContext.value?.account?.kategori || 'B.1');

function showFeedback(type, title, message) {
    feedbackBanner.value = { type, title, message };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(async () => {
    const saved = sessionStorage.getItem('sitaliti_budget_context');
    if (saved) {
        budgetContext.value = JSON.parse(saved);
    } else {
        showFeedback(
            'error',
            'Konteks Rekening Belum Dipilih',
            'Silakan pilih Unit Kerja, Sub-Kegiatan, Rekening, dan Bulan Anggaran melalui Dashboard terlebih dahulu.'
        );
        return;
    }

    const catCode = budgetContext.value.account?.kategori || 'B.1';
    checklistItems.value = await receiptService.getChecklistByCategory(catCode);
    form.value.nomor_bukti = `KW-2026-${String(budgetContext.value.month).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`;
});

watch(
    () => form.value.npwp_penerima,
    (val) => {
        form.value.has_npwp = Boolean(val && val.trim().length > 0);
    }
);

// Perhitungan Nilai Belanja
const totalNilaiBelanja = computed(() => {
    return form.value.items.reduce((sum, item) => {
        return sum + (Number(item.volume || 0) * Number(item.harga_satuan || 0));
    }, 0);
});

let taxDebounce = null;
async function refreshTaxPreview() {
    if (!budgetContext.value?.account?.id || totalNilaiBelanja.value <= 0) {
        validationResult.value = null;
        return;
    }

    isValidatingTax.value = true;
    try {
        validationResult.value = await receiptService.validateDraft({
            anggaran_rekening_id: budgetContext.value.account.id,
            bulan_anggaran: budgetContext.value.month,
            kategori_belanja: categoryCode.value,
            has_npwp: form.value.has_npwp,
            npwp_penerima: form.value.npwp_penerima,
            golongan_asn: categoryCode.value === 'B.3' ? form.value.golongan_asn : null,
            is_sewa_kendaraan: categoryCode.value === 'B.4' ? form.value.is_sewa_kendaraan : false,
            is_pkp: categoryCode.value === 'B.5' ? form.value.is_pkp : true,
            items: form.value.items,
        });
    } catch {
        // Biarkan preview tetap aman jika endpoint gagal sementara
    } finally {
        isValidatingTax.value = false;
    }
}

watch(
    () => [
        form.value.items,
        form.value.has_npwp,
        form.value.golongan_asn,
        form.value.is_sewa_kendaraan,
        form.value.is_pkp,
    ],
    () => {
        if (taxDebounce) clearTimeout(taxDebounce);
        taxDebounce = setTimeout(refreshTaxPreview, 300);
    },
    { deep: true }
);

const estimasiPajak = computed(() => {
    const tc = validationResult.value?.tax_calculation;
    if (!tc) {
        return {
            ppn: 0,
            pbjt_mamin: 0,
            pph21: 0,
            pph22: 0,
            pph23: 0,
            bea_meterai: 0,
            totalPajak: 0,
            totalNetto: totalNilaiBelanja.value,
        };
    }

    return {
        ppn: Number(tc.ppn || 0),
        pbjt_mamin: Number(tc.pbjt_mamin || 0),
        pph21: Number(tc.pph21 || 0),
        pph22: Number(tc.pph22 || 0),
        pph23: Number(tc.pph23 || 0),
        bea_meterai: Number(tc.bea_meterai || 0),
        totalPajak: Number(tc.total_pajak || 0),
        totalNetto: Number(tc.nilai_neto ?? totalNilaiBelanja.value),
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
        harga_satuan: 0,
    });
}

function removeItemRow(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

async function handleSubmitDraft() {
    feedbackBanner.value = null;

    if (!budgetContext.value?.account?.id) {
        showFeedback('error', 'Rekening Tidak Valid', 'Silakan pilih rekening anggaran dari Dashboard terlebih dahulu.');
        return;
    }

    if (!form.value.nama_penerima?.trim()) {
        showFeedback('error', 'Data Penerima Kosong', 'Silakan isi Penerima / Nama Toko terlebih dahulu.');
        return;
    }

    const missingMandatory = checklistItems.value
        .filter((doc) => doc.required && !doc.file)
        .map((doc) => doc.name);

    if (missingMandatory.length > 0) {
        showFeedback(
            'error',
            'Dokumen Wajib Belum Lengkap',
            `Harap unggah berkas wajib berikut: ${missingMandatory.join(', ')}.`
        );
        return;
    }

    isSubmitting.value = true;

    try {
        const result = await receiptService.validateDraft({
            anggaran_rekening_id: budgetContext.value.account.id,
            bulan_anggaran: budgetContext.value.month,
            kategori_belanja: categoryCode.value,
            has_npwp: form.value.has_npwp,
            npwp_penerima: form.value.npwp_penerima,
            golongan_asn: categoryCode.value === 'B.3' ? form.value.golongan_asn : null,
            is_sewa_kendaraan: categoryCode.value === 'B.4' ? form.value.is_sewa_kendaraan : false,
            is_pkp: categoryCode.value === 'B.5' ? form.value.is_pkp : true,
            items: form.value.items,
        });
        validationResult.value = result;

        if (result?.is_exceeding_budget) {
            showFeedback('error', 'Melebihi Sisa Pagu RAK', 'Nilai belanja melebihi sisa pagu anggaran kas yang tersedia!');
            isSubmitting.value = false;
            return;
        }

        const formData = new FormData();
        formData.append('anggaran_rekening_id', budgetContext.value.account.id);
        formData.append('bulan_anggaran', budgetContext.value.month || (new Date().getMonth() + 1));
        formData.append('nomor_bukti_toko', form.value.nomor_bukti);
        formData.append('tanggal_transaksi', form.value.tanggal_bukti);
        formData.append('nama_penerima', form.value.nama_penerima);
        formData.append('npwp_penerima', form.value.npwp_penerima || '');
        formData.append('has_npwp', form.value.has_npwp ? '1' : '0');
        if (categoryCode.value === 'B.3') {
            formData.append('golongan_asn', form.value.golongan_asn);
        }
        formData.append('is_sewa_kendaraan', form.value.is_sewa_kendaraan ? '1' : '0');
        formData.append('is_pkp', form.value.is_pkp ? '1' : '0');
        formData.append('uraian_belanja', form.value.uraian_belanja || 'Belanja Kuitansi');

        form.value.items.forEach((item, index) => {
            formData.append(`items[${index}][nama_barang]`, item.nama_barang || 'Barang Belanja');
            formData.append(`items[${index}][volume]`, item.volume || 1);
            formData.append(`items[${index}][satuan]`, item.satuan || 'Pcs');
            formData.append(`items[${index}][harga_satuan]`, item.harga_satuan);
        });

        checklistItems.value.forEach((item) => {
            if (item.file && !item.persisted) {
                formData.append('files[]', item.file);
                formData.append('checklist_codes[]', item.code || 'B.1.4');
                formData.append('nama_dokumen[]', item.name || item.file.name);
            }
        });

        let receiptId = savedReceiptId.value;
        if (!receiptId) {
            const saveRes = await receiptService.storeReceipt(formData);
            receiptId = saveRes.data?.id || saveRes.data?.receipt_id;
            if (!receiptId) {
                showFeedback('error', 'Gagal Menyimpan Draft', 'Respons penyimpanan kuitansi tidak mengembalikan ID.');
                return;
            }
            savedReceiptId.value = receiptId;
        } else {
            await receiptService.updateReceipt(receiptId, formData);
        }

        checklistItems.value.forEach((item) => {
            if (item.file) {
                item.persisted = true;
            }
        });

        await receiptService.submitReceipt(receiptId);
        router.visit(route('receipts.index'));
    } catch (err) {
        const errData = err.response?.data?.error;
        if (errData?.code === 'DUPLICATE_RECEIPT_ATTACHMENT') {
            showFeedback(
                'error',
                'Peringatan Duplikasi Nota Kembar (SHA-256)',
                `${errData.message} Berkas lampiran yang sama persis telah terdaftar pada sistem.`
            );
        } else {
            const errMsg = errData?.message || err.response?.data?.message || err.message || 'Terjadi kesalahan saat menyimpan draft.';
            showFeedback('error', 'Gagal Menyimpan Kuitansi', errMsg);
        }
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

        <div
            v-if="feedbackBanner"
            :class="[
                'mb-4 rounded-xl border p-4 text-xs flex items-start justify-between gap-3',
                feedbackBanner.type === 'error'
                    ? 'bg-rose-50 border-rose-300 text-rose-800'
                    : 'bg-emerald-50 border-emerald-300 text-emerald-800',
            ]"
            role="alert"
        >
            <div>
                <div class="font-bold">{{ feedbackBanner.title }}</div>
                <p class="mt-0.5">{{ feedbackBanner.message }}</p>
            </div>
            <button
                type="button"
                class="text-current opacity-70 hover:opacity-100 font-bold"
                @click="feedbackBanner = null"
            >
                &times;
            </button>
        </div>

        <div v-if="budgetContext" class="space-y-6">
            <!-- Pagu Context Header Card (Kunci dari Gatekeeper) -->
            <div class="bg-gov-navy-950 text-white rounded-xl p-5 shadow-sm border border-gov-navy-800">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold  text-gov-gold-400">
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
                        <label class="block text-xs font-semibold text-gov-navy-900  mb-1.5">
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
                        <thead class="bg-gov-surface-subtle text-[11px]  font-semibold text-slate-600">
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

            <!-- Form Bagian 3: Simulasi Pajak & Potongan (Resmi Backend) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Sisi Kiri (2 Kolom): Opsi Pajak Kontekstual -->
                <div class="lg:col-span-2">
                    <GovCard title="Parameter Pajak Transaksi (PMK 59/2022 & PMK 168/2023)" subtitle="Opsi pajak disesuaikan otomatis menurut kategori rekening belanja">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.has_npwp"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Rekanan Memiliki NPWP</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">Non-NPWP dikenakan penalti tarif 100% lebih tinggi pada PPh 22 / PPh 23.</p>
                            </div>

                            <div v-if="categoryCode === 'B.3'" class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="block font-semibold text-gov-navy-900 mb-1">
                                    Status / Golongan Penerima Honor (PPh 21)
                                </label>
                                <select
                                    v-model="form.golongan_asn"
                                    class="w-full text-xs rounded border-gov-surface-border py-1.5 px-2 focus:border-gov-navy-800"
                                >
                                    <option value="GOLONGAN_I_II">PNS Golongan I / II (0%)</option>
                                    <option value="GOLONGAN_III">PNS Golongan III (5%)</option>
                                    <option value="GOLONGAN_IV">PNS Golongan IV (15%)</option>
                                    <option value="NON_ASN">Non-ASN / Bukan Pegawai (2,5% Efektif)</option>
                                </select>
                            </div>

                            <div v-if="categoryCode === 'B.4'" class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_sewa_kendaraan"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Termasuk Sewa Kendaraan / Jasa Pihak Ketiga</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">SPPD ASN biasa bebas pajak (0%); sewa kendaraan dipotong PPh 23 (2% / 4%).</p>
                            </div>

                            <div v-if="categoryCode === 'B.5'" class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle">
                                <label class="flex items-center gap-2 font-semibold text-gov-navy-900 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_pkp"
                                        class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                    />
                                    <span>Rekanan Jasa PKP (Kena PPN &gt; Rp2 Juta)</span>
                                </label>
                                <p class="text-[11px] text-slate-500 mt-1 pl-5">Jasa pemeliharaan dipotong PPh 23 (2% / 4%) serta PPN 11% jika &gt; Rp2.000.000 &amp; PKP.</p>
                            </div>

                            <div v-if="categoryCode === 'B.2'" class="p-3 rounded-lg border border-emerald-200 bg-emerald-50/70">
                                <div class="font-semibold text-emerald-900">PBJT Makan/Minum 10% (UU HKPD)</div>
                                <p class="text-[11px] text-emerald-800 mt-1">Belanja mamin otomatis dikenakan PBJT Pemda Garut 10% dan mutlak bebas PPN pusat.</p>
                            </div>

                            <div v-if="categoryCode === 'B.1' || categoryCode === 'B.6'" class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                                <div class="font-semibold text-gov-navy-900">Ambang Batas PMK 59/2022</div>
                                <p class="text-[11px] text-slate-600 mt-1">Transaksi &le; Rp2.000.000 bebas pungut PPN &amp; PPh 22; di atas Rp2.000.000 dipungut PPN 11% &amp; PPh 22.</p>
                            </div>
                        </div>
                    </GovCard>
                </div>

                <!-- Sisi Kanan (1 Kolom): Rekapitulasi Kasir -->
                <div>
                    <div class="bg-gov-navy-900 text-white rounded-xl p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between border-b border-gov-navy-800 pb-2">
                            <h4 class="text-xs font-bold  text-gov-gold-400">
                                Ringkasan Pembayaran &amp; Kas
                            </h4>
                            <span v-if="isValidatingTax" class="text-[10px] text-slate-300">Menghitung...</span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-300">Nilai Bruto Kuitansi:</span>
                                <span class="font-semibold tabular-nums font-mono">{{ formatRupiah(totalNilaiBelanja) }}</span>
                            </div>

                            <div v-if="estimasiPajak.ppn > 0" class="flex justify-between text-slate-300">
                                <span>PPN 11%:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.ppn) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pbjt_mamin > 0" class="flex justify-between text-slate-300">
                                <span>PBJT Mamin 10%:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pbjt_mamin) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph21 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 21:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph21) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph22 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 22:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph22) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph23 > 0" class="flex justify-between text-rose-300">
                                <span>Potongan PPh 23:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph23) }}</span>
                            </div>

                            <div v-if="estimasiPajak.bea_meterai > 0" class="flex justify-between text-amber-300">
                                <span>Bea Meterai (&gt; Rp5 Juta):</span>
                                <span class="tabular-nums font-mono">{{ formatRupiah(estimasiPajak.bea_meterai) }}</span>
                            </div>

                            <div class="pt-3 border-t border-gov-navy-800">
                                <div class="text-[11px] text-slate-400">Total Yang Dibayarkan Riil (Neto):</div>
                                <div class="text-xl font-semibold text-gov-gold-400 tabular-nums font-mono mt-0.5">
                                    {{ formatRupiah(estimasiPajak.totalNetto) }}
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 italic pt-1 leading-tight">
                            *Simulasi dihitung langsung oleh mesin pajak backend (`/api/v1/receipts/validate-draft`).
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

