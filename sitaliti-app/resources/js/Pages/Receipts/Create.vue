<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Trash2, Plus } from 'lucide-vue-next';
import { useToast } from '@/Composables/useToast';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovInput from '@/Components/UI/GovInput.vue';
import GovCurrencyInput from '@/Components/UI/GovCurrencyInput.vue';
import DynamicChecklistTable from '@/Modules/Checklist/DynamicChecklistTable.vue';
import RAKSelectorModal from '@/Modules/Gatekeeper/RAKSelectorModal.vue';
import SppdParticipantsSection from '@/Components/SppdParticipantsSection.vue';
import { formatRupiah } from '@/Utils/currency';
import { receiptService } from '@/Services/receiptService';

const budgetContext = ref(null);
const showRakSelector = ref(false);
const checklistItems = ref([]);
const isSubmitting = ref(false);
const savedReceiptId = ref(null);
const isValidatingTax = ref(false);
const validationResult = ref(null);

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

const categoryCode = computed(() => {
    const raw = String(budgetContext.value?.account?.kategori || 'B.1').toUpperCase();
    if (raw.startsWith('B1')) return 'B.1';
    if (raw.startsWith('B2')) return 'B.2';
    if (raw.startsWith('B3')) return 'B.3';
    if (raw.startsWith('B4')) return 'B.4';
    if (raw.startsWith('B5')) return 'B.5';
    if (raw.startsWith('B6')) return 'B.6';
    return budgetContext.value?.account?.kategori || 'B.1';
});

const isSppdCategory = computed(() => {
    if (categoryCode.value === 'B.4') return true;
    const kode = String(budgetContext.value?.account?.kode_rekening || '');
    return kode.startsWith('5.1.02.04');
});

const sppdPeserta = ref([]);
const sppdGroupPhoto = ref(null);
const sppdRemoveGroupPhoto = ref(false);
const sppdHasConflict = ref(false);

const toast = useToast();

async function applyBudgetContext(context) {
    budgetContext.value = context;
    showRakSelector.value = false;
    sessionStorage.setItem('sitaliti_budget_context', JSON.stringify(context));

    const catCode = context.account?.kategori || 'B.1';
    checklistItems.value = await receiptService.getChecklistByCategory(catCode);
    form.value.nomor_bukti = `KW-2026-${String(context.month).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`;
}

onMounted(async () => {
    const saved = sessionStorage.getItem('sitaliti_budget_context');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            if (parsed?.account?.id) {
                await applyBudgetContext(parsed);
                return;
            }
        } catch {
            sessionStorage.removeItem('sitaliti_budget_context');
        }
    }

    showRakSelector.value = true;
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
            tanggal_transaksi: form.value.tanggal_transaksi,
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

async function handleSubmitDraft(andSubmit = true) {
    if (!budgetContext.value?.account?.id) {
        toast.error('Rekening Tidak Valid', 'Silakan pilih rekening anggaran dari Dashboard terlebih dahulu.');
        return;
    }

    if (!form.value.nama_penerima?.trim()) {
        toast.error('Data Penerima Kosong', 'Silakan isi Penerima / Nama Toko terlebih dahulu.');
        return;
    }

    if (andSubmit) {
        const missingMandatory = checklistItems.value
            .filter((doc) => doc.required && !doc.file)
            .map((doc) => doc.name);

        if (missingMandatory.length > 0) {
            toast.error(
                'Dokumen Wajib Belum Lengkap',
                `Harap unggah berkas wajib berikut: ${missingMandatory.join(', ')}.`
            );
            return;
        }
    }

    isSubmitting.value = true;

    try {
        const result = await receiptService.validateDraft({
            anggaran_rekening_id: budgetContext.value.account.id,
            bulan_anggaran: budgetContext.value.month,
            tanggal_transaksi: form.value.tanggal_transaksi,
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
            toast.error('Melebihi Sisa Pagu RAK', 'Nilai belanja melebihi sisa pagu anggaran kas yang tersedia!');
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

        if (isSppdCategory.value) {
            formData.append('sppd_peserta_synced', '1');
            formData.append('sppd_foto_remove', sppdRemoveGroupPhoto.value ? '1' : '0');
            if (sppdGroupPhoto.value) {
                formData.append('sppd_foto', sppdGroupPhoto.value);
            }
            sppdPeserta.value.forEach((peserta, idx) => {
                if (!peserta.pegawai_id) return;
                formData.append(`sppd_peserta[${idx}][pegawai_id]`, peserta.pegawai_id);
                formData.append(`sppd_peserta[${idx}][tanggal_berangkat]`, peserta.tanggal_berangkat || form.value.tanggal_bukti);
                formData.append(`sppd_peserta[${idx}][tanggal_pulang]`, peserta.tanggal_pulang || peserta.tanggal_berangkat || form.value.tanggal_bukti);
                if (peserta.tujuan) {
                    formData.append(`sppd_peserta[${idx}][tujuan]`, peserta.tujuan);
                }
                formData.append(`sppd_peserta[${idx}][remove_foto]`, peserta.remove_foto ? '1' : '0');
                if (peserta.foto) {
                    formData.append(`sppd_peserta[${idx}][foto]`, peserta.foto);
                }
            });
        }

        let receiptId = savedReceiptId.value;
        if (!receiptId) {
            const saveRes = await receiptService.storeReceipt(formData);
            receiptId = saveRes.data?.id || saveRes.data?.receipt_id;
            if (!receiptId) {
                toast.error('Gagal Menyimpan Draft', 'Respons penyimpanan kuitansi tidak mengembalikan ID.');
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

        if (andSubmit) {
            await receiptService.submitReceipt(receiptId);
        }
        router.visit(route('receipts.index'));
    } catch (err) {
        const errData = err.response?.data?.error;
        const details = errData?.details || err.response?.data?.errors;
        if (errData?.code === 'DUPLICATE_RECEIPT_ATTACHMENT') {
            toast.error(
                'Peringatan Duplikasi Nota Kembar (SHA-256)',
                `${errData.message} Berkas lampiran yang sama persis telah terdaftar pada sistem.`
            );
        } else if (details && typeof details === 'object') {
            const detailText = Object.values(details).flat().join(' ');
            toast.error(errData?.message || 'Validasi Gagal', detailText || errData?.message);
        } else {
            const errMsg = errData?.message || err.response?.data?.message || err.message || 'Terjadi kesalahan saat menyimpan draft.';
            toast.error('Gagal Menyimpan Kuitansi', errMsg);
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
                        <Link :href="route('dashboard')" class="text-xs text-accent hover:text-accent-hover font-medium">
                            ← Kembali ke Dashboard
                        </Link>
                    </div>
                    <h1 class="text-lg font-semibold text-ink mt-1 flex items-center gap-2">
                        <span>Form Pertanggungjawaban Kuitansi Belanja</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-accent-soft text-accent font-mono">
                            Pre-Audit Entry
                        </span>
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <GovButton variant="secondary" size="sm" @click="router.visit(route('dashboard'))">
                        Batal
                    </GovButton>
                    <GovButton
                        variant="secondary"
                        size="sm"
                        :loading="isSubmitting"
                        :disabled="isExceedingBudget || totalNilaiBelanja === 0"
                        @click="handleSubmitDraft(false)"
                    >
                        Simpan Draft
                    </GovButton>
                    <GovButton
                        variant="gold"
                        size="sm"
                        :loading="isSubmitting"
                        :disabled="isExceedingBudget || totalNilaiBelanja === 0"
                        @click="handleSubmitDraft(true)"
                    >
                        {{ $page.props.auth?.user?.can_auto_finalize_receipt ? 'Simpan & Finalisasi' : 'Simpan & Ajukan Verifikasi' }}
                    </GovButton>
                </div>
            </div>
        </template>

        <div v-if="budgetContext" class="space-y-6">
            <!-- Pagu Context Header Card (Kunci dari Gatekeeper) -->
            <div class="bg-surface text-ink rounded-xl p-5 shadow-subtle border border-hairline">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold text-ink">
                                {{ budgetContext.unit?.name }}
                            </h3>
                            <span class="text-xs bg-accent-soft text-accent px-2 py-0.5 rounded border border-accent-border font-mono">
                                Alokasi Bln {{ budgetContext.month }} - {{ budgetContext.monthName }} 2026
                            </span>
                        </div>
                        <p class="text-xs text-ink-secondary">
                            Subkegiatan: <span class="font-medium text-ink">{{ budgetContext.subActivity?.name }}</span>
                        </p>
                        <p class="text-xs text-ink-muted font-mono">
                            Rekening: <strong class="text-accent">[{{ budgetContext.account?.kategori }}] {{ budgetContext.account?.code }}</strong> - {{ budgetContext.account?.name }}
                        </p>
                    </div>

                    <div class="bg-raised p-3.5 rounded-lg border border-hairline md:text-right shrink-0">
                        <span class="text-xs text-ink-muted block">Sisa Plafon Pagu Bebas</span>
                        <div class="text-xl font-semibold text-semantic-success font-mono tabular-nums">
                            {{ formatRupiah(budgetContext.rakContext?.sisa_dapat_digunakan) }}
                        </div>
                        <span class="text-xs text-ink-muted">Termasuk Carry-Over bulan lalu</span>
                    </div>
                </div>
            </div>

            <!-- Error Banner jika transaksi melebihi pagu -->
            <div v-if="isExceedingBudget" class="p-4 bg-rose-50 border border-rose-300 rounded-xl text-rose-800 text-xs flex items-center gap-3">
                <AlertTriangle class="w-5 h-5 text-semantic-danger shrink-0" :stroke-width="1.5" />
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
                        <label class="block text-xs font-semibold text-ink mb-1.5">
                            Uraian Belanja Kuitansi <span class="text-semantic-danger">*</span>
                        </label>
                        <textarea
                            v-model="form.uraian_belanja"
                            rows="2"
                            placeholder="Jelaskan peruntukan pengeluaran belanja secara lengkap dan akuntabel..."
                            class="block w-full rounded-lg border-hairline px-3.5 py-2 text-sm text-ink focus:border-accent focus:ring-1 focus:ring-accent"
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
                    <table class="min-w-full divide-y divide-hairline text-left">
                        <thead class="bg-raised text-xs font-semibold text-ink-secondary">
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
                        <tbody class="divide-y divide-hairline text-xs">
                            <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-raised/60">
                                <td class="py-2 px-4 text-center text-ink-muted font-medium">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-2 px-4">
                                    <input
                                        v-model="item.nama_barang"
                                        type="text"
                                        placeholder="Contoh: Kertas HVS A4 80gr"
                                        class="w-full text-xs rounded border-hairline py-1.5 px-2.5 focus:border-accent focus:ring-1 focus:ring-accent"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right">
                                    <input
                                        v-model.number="item.volume"
                                        type="number"
                                        min="1"
                                        class="w-full text-xs text-right rounded border-hairline py-1.5 px-2.5 font-mono tabular-nums focus:border-accent focus:ring-1 focus:ring-accent"
                                    />
                                </td>
                                <td class="py-2 px-4">
                                    <input
                                        v-model="item.satuan"
                                        type="text"
                                        placeholder="Rim/Kotak"
                                        class="w-full text-xs rounded border-hairline py-1.5 px-2.5 focus:border-accent focus:ring-1 focus:ring-accent"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right">
                                    <GovCurrencyInput
                                        v-model="item.harga_satuan"
                                        placeholder="0"
                                    />
                                </td>
                                <td class="py-2 px-4 text-right font-semibold text-ink font-mono tabular-nums">
                                    {{ formatRupiah(Number(item.volume || 0) * Number(item.harga_satuan || 0)) }}
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <button
                                        type="button"
                                        @click="removeItemRow(idx)"
                                        :disabled="form.items.length === 1"
                                        class="text-ink-muted hover:text-semantic-danger disabled:opacity-30 disabled:hover:text-ink-muted"
                                    >
                                        <Trash2 class="w-4 h-4" :stroke-width="1.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template #footer>
                    <div class="flex items-center justify-between">
                        <GovButton variant="secondary" size="sm" @click="addItemRow">
                            <Plus class="w-3.5 h-3.5" :stroke-width="1.5" />
                            Tambah Baris Barang
                        </GovButton>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-ink-muted">Subtotal Belanja:</span>
                            <span class="text-base font-semibold text-ink font-mono tabular-nums">
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
                            <div class="p-3 rounded-lg border border-hairline bg-raised">
                                <label class="flex items-center gap-2 font-semibold text-ink cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.has_npwp"
                                        class="rounded text-accent focus:ring-accent"
                                    />
                                    <span>Rekanan Memiliki NPWP</span>
                                </label>
                                <p class="text-xs text-ink-muted mt-1 pl-5">Non-NPWP dikenakan penalti tarif 100% lebih tinggi pada PPh 22 / PPh 23.</p>
                            </div>

                            <div v-if="categoryCode === 'B.3'" class="p-3 rounded-lg border border-hairline bg-raised">
                                <label class="block font-semibold text-ink mb-1">
                                    Status / Golongan Penerima Honor (PPh 21)
                                </label>
                                <select
                                    v-model="form.golongan_asn"
                                    class="w-full text-xs rounded border-hairline py-1.5 px-2 focus:border-accent"
                                >
                                    <option value="GOLONGAN_I_II">PNS Golongan I / II (0%)</option>
                                    <option value="GOLONGAN_III">PNS Golongan III (5%)</option>
                                    <option value="GOLONGAN_IV">PNS Golongan IV (15%)</option>
                                    <option value="NON_ASN">Non-ASN / Bukan Pegawai (2,5% Efektif)</option>
                                </select>
                            </div>

                            <div v-if="categoryCode === 'B.4'" class="p-3 rounded-lg border border-hairline bg-raised">
                                <label class="flex items-center gap-2 font-semibold text-ink cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_sewa_kendaraan"
                                        class="rounded text-accent focus:ring-accent"
                                    />
                                    <span>Termasuk Sewa Kendaraan / Jasa Pihak Ketiga</span>
                                </label>
                                <p class="text-xs text-ink-muted mt-1 pl-5">SPPD ASN biasa bebas pajak (0%); sewa kendaraan dipotong PPh 23 (2% / 4%).</p>
                            </div>

                            <div v-if="categoryCode === 'B.5'" class="p-3 rounded-lg border border-hairline bg-raised">
                                <label class="flex items-center gap-2 font-semibold text-ink cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_pkp"
                                        class="rounded text-accent focus:ring-accent"
                                    />
                                    <span>Rekanan Jasa PKP (Kena PPN &gt; Rp2 Juta)</span>
                                </label>
                                <p class="text-xs text-ink-muted mt-1 pl-5">Jasa pemeliharaan dipotong PPh 23 (2% / 4%) serta PPN 11% jika &gt; Rp2.000.000 &amp; PKP.</p>
                            </div>

                            <div v-if="categoryCode === 'B.2'" class="p-3 rounded-lg border border-emerald-200 bg-emerald-50/70">
                                <div class="font-semibold text-emerald-900">PBJT Makan/Minum 10% (UU HKPD)</div>
                                <p class="text-xs text-emerald-800 mt-1">Belanja mamin otomatis dikenakan PBJT Pemda Garut 10% dan mutlak bebas PPN pusat.</p>
                            </div>

                            <div v-if="categoryCode === 'B.1' || categoryCode === 'B.6'" class="p-3 rounded-lg border border-hairline bg-raised">
                                <div class="font-semibold text-ink">Ambang Batas PMK 59/2022</div>
                                <p class="text-xs text-ink-secondary mt-1">Transaksi &le; Rp2.000.000 bebas pungut PPN &amp; PPh 22; di atas Rp2.000.000 dipungut PPN 11% &amp; PPh 22.</p>
                            </div>

                            <div
                                v-if="(validationResult?.sbm_warnings || []).length > 0"
                                role="status"
                                class="p-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 space-y-1"
                            >
                                <div class="font-semibold text-xs flex items-center gap-1.5">
                                    <span>Peringatan SBM/SSH (Non-Blocking)</span>
                                </div>
                                <ul class="list-disc pl-4 space-y-0.5 text-xs">
                                    <li v-for="(warn, wIdx) in validationResult.sbm_warnings" :key="wIdx">
                                        {{ warn.message }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </GovCard>
                </div>

                <!-- Sisi Kanan (1 Kolom): Rekapitulasi Kasir -->
                <div>
                    <div class="bg-surface text-ink rounded-xl p-5 border border-hairline shadow-subtle space-y-3">
                        <div class="flex items-center justify-between border-b border-hairline pb-2">
                            <h4 class="text-xs font-semibold text-ink">
                                Ringkasan Pembayaran &amp; Kas
                            </h4>
                            <span v-if="isValidatingTax" class="text-xs text-ink-muted">Menghitung...</span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-secondary">Nilai Bruto Kuitansi:</span>
                                <span class="font-semibold tabular-nums font-mono text-ink">{{ formatRupiah(totalNilaiBelanja) }}</span>
                            </div>

                            <div v-if="estimasiPajak.ppn > 0" class="flex justify-between text-ink-secondary">
                                <span>PPN 11%:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.ppn) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pbjt_mamin > 0" class="flex justify-between text-ink-secondary">
                                <span>PBJT Mamin 10%:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pbjt_mamin) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph21 > 0" class="flex justify-between text-semantic-danger">
                                <span>Potongan PPh 21:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph21) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph22 > 0" class="flex justify-between text-semantic-danger">
                                <span>Potongan PPh 22:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph22) }}</span>
                            </div>

                            <div v-if="estimasiPajak.pph23 > 0" class="flex justify-between text-semantic-danger">
                                <span>Potongan PPh 23:</span>
                                <span class="tabular-nums font-mono">- {{ formatRupiah(estimasiPajak.pph23) }}</span>
                            </div>

                            <div v-if="estimasiPajak.bea_meterai > 0" class="flex justify-between text-semantic-warning">
                                <span>Bea Meterai (&gt; Rp5 Juta):</span>
                                <span class="tabular-nums font-mono">{{ formatRupiah(estimasiPajak.bea_meterai) }}</span>
                            </div>

                            <div class="pt-3 border-t border-hairline">
                                <div class="text-xs text-ink-muted">Total Yang Dibayarkan Riil (Neto):</div>
                                <div class="text-xl font-semibold text-accent tabular-nums font-mono mt-0.5">
                                    {{ formatRupiah(estimasiPajak.totalNetto) }}
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-ink-muted pt-1 leading-tight">
                            *Simulasi dihitung langsung oleh mesin pajak backend (<span class="font-mono">/api/v1/receipts/validate-draft</span>).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Bagian 3b: Pelaksana & Foto Keberangkatan SPPD (Khusus B.4) -->
            <SppdParticipantsSection
                v-if="isSppdCategory"
                v-model="sppdPeserta"
                v-model:group-photo="sppdGroupPhoto"
                v-model:remove-group-photo="sppdRemoveGroupPhoto"
                :default-date="form.tanggal_bukti"
                @conflict-change="sppdHasConflict = $event"
            />

            <!-- Form Bagian 4: Dynamic Checklist Dokumen B.1 - B.6 -->
            <DynamicChecklistTable
                :category-code="budgetContext.account?.kategori || 'B.1'"
                :category-name="budgetContext.account?.name"
                v-model:items="checklistItems"
            />
        </div>

        <div v-else class="bg-surface border border-hairline rounded-xl p-8 text-center space-y-3">
            <h3 class="text-base font-bold text-ink">Konteks Rekening &amp; Bulan Anggaran Belum Dipilih</h3>
            <p class="text-sm text-ink-secondary max-w-md mx-auto">
                Pilih Unit Kerja, Sub-Kegiatan, Rekening Belanja, dan Bulan Anggaran terlebih dahulu untuk memulai input kuitansi.
            </p>
            <div>
                <GovButton variant="primary" @click="showRakSelector = true">
                    Pilih Rekening &amp; Bulan Anggaran
                </GovButton>
            </div>
        </div>

        <RAKSelectorModal
            :show="showRakSelector"
            @close="showRakSelector = false"
            @select="applyBudgetContext"
        />
    </GovAppLayout>
</template>

