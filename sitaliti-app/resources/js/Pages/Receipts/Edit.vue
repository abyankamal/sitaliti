<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import axios from 'axios';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovInput from '@/Components/UI/GovInput.vue';
import GovCurrencyInput from '@/Components/UI/GovCurrencyInput.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import DynamicChecklistTable from '@/Modules/Checklist/DynamicChecklistTable.vue';
import SppdParticipantsSection from '@/Components/SppdParticipantsSection.vue';
import { formatRupiah } from '@/Utils/currency';
import { getStatusMeta } from '@/Constants/workflowStatus';
import { receiptService } from '@/Services/receiptService';

const props = defineProps({
    id: {
        type: [Number, String],
        required: true,
    },
});

const isLoading = ref(true);
const isSaving = ref(false);
const isValidatingTax = ref(false);
const receipt = ref(null);
const checklistItems = ref([]);
const existingAttachments = ref([]);
const validationResult = ref(null);

const form = ref({
    bulan_anggaran: 1,
    nomor_bukti_toko: '',
    tanggal_transaksi: '',
    nama_penerima: '',
    npwp_penerima: '',
    has_npwp: false,
    golongan_asn: 'GOLONGAN_III',
    is_sewa_kendaraan: false,
    is_pkp: true,
    metode_pembayaran: 'NON_TUNAI_IBC',
    uraian_belanja: '',
    items: [],
});

const isMinorRevision = computed(() => receipt.value?.status === 'REVISI_MINOR');
const categoryCode = computed(() => receipt.value?.rekening?.kategori_belanja || 'B.1');
const normalizedCategory = computed(() => {
    const raw = categoryCode.value;
    if (raw.startsWith('B1')) return 'B.1';
    if (raw.startsWith('B2')) return 'B.2';
    if (raw.startsWith('B3')) return 'B.3';
    if (raw.startsWith('B4')) return 'B.4';
    if (raw.startsWith('B5')) return 'B.5';
    if (raw.startsWith('B6')) return 'B.6';
    return raw;
});

const isSppdCategory = computed(() => {
    if (normalizedCategory.value === 'B.4') return true;
    return String(receipt.value?.rekening?.kode_rekening || '').startsWith('5.1.02.04');
});

const sppdPeserta = ref([]);
const sppdGroupPhoto = ref(null);
const sppdRemoveGroupPhoto = ref(false);
const sppdHasConflict = ref(false);

const statusMeta = computed(() => getStatusMeta(receipt.value?.status || 'DRAFT'));

const revisionNote = computed(() => {
    if (!receipt.value) return null;
    if (receipt.value.catatan_revisi) return receipt.value.catatan_revisi;
    const history = Array.isArray(receipt.value.riwayat) ? receipt.value.riwayat : [];
    const latestWithNote = [...history].reverse().find((h) => h.catatan);
    return latestWithNote?.catatan || null;
});

const bulanOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const toast = useToast();
const { confirm } = useConfirm();

async function loadReceiptData() {
    isLoading.value = true;
    try {
        const res = await axios.get(`/api/v1/receipts/${props.id}`);
        const data = res.data?.data;
        receipt.value = data;

        sppdPeserta.value = Array.isArray(data.sppd_peserta)
            ? data.sppd_peserta.map((p) => ({
                  pegawai_id: p.pegawai_id,
                  tanggal_berangkat: (p.tanggal_berangkat || '').slice(0, 10),
                  tanggal_pulang: (p.tanggal_pulang || '').slice(0, 10),
                  tujuan: p.tujuan || '',
                  foto: null,
                  foto_url: p.foto_url || null,
                  remove_foto: false,
              }))
            : [];
        sppdGroupPhoto.value = null;
        sppdRemoveGroupPhoto.value = false;

        form.value = {
            bulan_anggaran: Number(data.bulan_anggaran || 1),
            nomor_bukti_toko: data.nomor_bukti_toko || '',
            tanggal_transaksi: (data.tanggal_transaksi || '').slice(0, 10),
            nama_penerima: data.nama_penerima || '',
            npwp_penerima: data.npwp_penerima || '',
            has_npwp: Boolean(data.npwp_penerima && String(data.npwp_penerima).trim().length > 0),
            golongan_asn: ['GOLONGAN_I', 'GOLONGAN_II'].includes(data.golongan_asn)
                ? 'GOLONGAN_I_II'
                : (data.golongan_asn || 'GOLONGAN_III'),
            is_sewa_kendaraan: Boolean(data.is_sewa_kendaraan ?? false),
            is_pkp: Boolean(data.is_pkp ?? true),
            metode_pembayaran: data.metode_pembayaran || 'NON_TUNAI_IBC',
            uraian_belanja: data.uraian || '',
            items: (data.items || []).map((item) => ({
                nama_barang: item.nama_barang,
                volume: Number(item.volume),
                satuan: item.satuan || 'Pcs',
                harga_satuan: Number(item.harga_satuan),
            })),
        };

        if (form.value.items.length === 0) {
            form.value.items.push({ nama_barang: '', volume: 1, satuan: 'Pcs', harga_satuan: 0 });
        }

        existingAttachments.value = data.lampiran || [];
        checklistItems.value = await receiptService.getChecklistByCategory(normalizedCategory.value);
        await refreshTaxPreview();
    } catch (error) {
        toast.error(
            'Gagal Memuat Kuitansi',
            error.response?.data?.message || 'Tidak dapat memuat detail kuitansi untuk diedit.'
        );
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadReceiptData);

watch(
    () => form.value.npwp_penerima,
    (val) => {
        form.value.has_npwp = Boolean(val && val.trim().length > 0);
    }
);

const totalNilaiBelanja = computed(() => {
    return form.value.items.reduce((sum, item) => {
        return sum + Number(item.volume || 0) * Number(item.harga_satuan || 0);
    }, 0);
});

let taxDebounce = null;
async function refreshTaxPreview() {
    if (!receipt.value?.anggaran_rekening_id || totalNilaiBelanja.value <= 0) {
        validationResult.value = null;
        return;
    }

    isValidatingTax.value = true;
    try {
        validationResult.value = await receiptService.validateDraft({
            anggaran_rekening_id: receipt.value.anggaran_rekening_id,
            bulan_anggaran: form.value.bulan_anggaran,
            tanggal_transaksi: form.value.tanggal_transaksi,
            kategori_belanja: normalizedCategory.value,
            has_npwp: form.value.has_npwp,
            npwp_penerima: form.value.npwp_penerima,
            golongan_asn: normalizedCategory.value === 'B.3' ? form.value.golongan_asn : null,
            is_sewa_kendaraan: normalizedCategory.value === 'B.4' ? form.value.is_sewa_kendaraan : false,
            is_pkp: normalizedCategory.value === 'B.5' ? form.value.is_pkp : true,
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
        form.value.bulan_anggaran,
        form.value.items,
        form.value.has_npwp,
        form.value.golongan_asn,
        form.value.is_sewa_kendaraan,
        form.value.is_pkp,
    ],
    () => {
        if (isLoading.value) return;
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
            totalPajak: Number(receipt.value?.total_pajak || 0),
            totalNetto: Number(receipt.value?.nominal_neto ?? totalNilaiBelanja.value),
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

function addItemRow() {
    if (isMinorRevision.value) return;
    form.value.items.push({
        nama_barang: '',
        volume: 1,
        satuan: 'Pcs',
        harga_satuan: 0,
    });
}

function removeItemRow(index) {
    if (isMinorRevision.value) return;
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

function buildUpdateFormData() {
    const formData = new FormData();
    formData.append('anggaran_rekening_id', receipt.value.anggaran_rekening_id);
    formData.append('bulan_anggaran', form.value.bulan_anggaran);
    formData.append('nomor_bukti_toko', form.value.nomor_bukti_toko || '');
    formData.append('tanggal_transaksi', form.value.tanggal_transaksi);
    formData.append('nama_penerima', form.value.nama_penerima);
    formData.append('npwp_penerima', form.value.npwp_penerima || '');
    formData.append('has_npwp', form.value.has_npwp ? '1' : '0');
    if (normalizedCategory.value === 'B.3') {
        formData.append('golongan_asn', form.value.golongan_asn);
    }
    if (normalizedCategory.value === 'B.4') {
        formData.append('is_sewa_kendaraan', form.value.is_sewa_kendaraan ? '1' : '0');
    }
    if (normalizedCategory.value === 'B.5') {
        formData.append('is_pkp', form.value.is_pkp ? '1' : '0');
    }
    formData.append('metode_pembayaran', form.value.metode_pembayaran);
    formData.append('uraian_belanja', form.value.uraian_belanja || '');

    form.value.items.forEach((item, index) => {
        formData.append(`items[${index}][nama_barang]`, item.nama_barang);
        formData.append(`items[${index}][volume]`, item.volume);
        formData.append(`items[${index}][satuan]`, item.satuan || 'Pcs');
        formData.append(`items[${index}][harga_satuan]`, item.harga_satuan);
    });

    checklistItems.value
        .filter((doc) => doc.file)
        .forEach((doc, idx) => {
            formData.append(`files[${idx}]`, doc.file);
            formData.append(`checklist_codes[${idx}]`, doc.code);
            formData.append(`nama_dokumen[${idx}]`, doc.name);
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
            formData.append(`sppd_peserta[${idx}][tanggal_berangkat]`, peserta.tanggal_berangkat || form.value.tanggal_transaksi);
            formData.append(`sppd_peserta[${idx}][tanggal_pulang]`, peserta.tanggal_pulang || peserta.tanggal_berangkat || form.value.tanggal_transaksi);
            if (peserta.tujuan) {
                formData.append(`sppd_peserta[${idx}][tujuan]`, peserta.tujuan);
            }
            formData.append(`sppd_peserta[${idx}][remove_foto]`, peserta.remove_foto ? '1' : '0');
            if (peserta.foto) {
                formData.append(`sppd_peserta[${idx}][foto]`, peserta.foto);
            }
        });
    }

    return formData;
}

function formatApiError(error) {
    const data = error.response?.data;
    const details = data?.error?.details || data?.errors;
    if (details && typeof details === 'object') {
        return Object.values(details).flat().join(' ');
    }
    return data?.error?.message || data?.message || 'Terjadi kesalahan saat menyimpan perubahan kuitansi.';
}

async function handleSaveChanges(andResubmit = false) {
    if (!receipt.value) return;
    if (!form.value.nama_penerima?.trim()) {
        toast.error('Data Penerima Kosong', 'Silakan isi Penerima / Nama Toko terlebih dahulu.');
        return;
    }

    isSaving.value = true;
    try {
        const formData = buildUpdateFormData();
        await receiptService.updateReceipt(receipt.value.id, formData);

        if (andResubmit) {
            if (isMinorRevision.value) {
                await receiptService.resubmitRevision(receipt.value.id);
            } else {
                await receiptService.submitReceipt(receipt.value.id);
            }
            router.visit('/receipts');
            return;
        }

        await loadReceiptData();
        toast.success('Perubahan Disimpan', 'Data dan lampiran kuitansi berhasil diperbarui.');
    } catch (error) {
        toast.error('Gagal Menyimpan Perubahan', formatApiError(error));
    } finally {
        isSaving.value = false;
    }
}

async function openDeleteAttachmentModal(att) {
    if (!att || !receipt.value) return;
    const label = att.nama_dokumen || att.file_name || att.nama_file_asli || 'Lampiran';
    await confirm({
        title: 'Hapus Berkas Lampiran?',
        message: `Anda yakin ingin menghapus lampiran ${label}? Anda dapat mengunggah berkas pengganti setelahnya.`,
        variant: 'danger',
        confirmText: 'Ya, Hapus Lampiran',
        onConfirm: async () => {
            await receiptService.deleteAttachment(receipt.value.id, att.id);
            existingAttachments.value = existingAttachments.value.filter(
                (item) => item.id !== att.id
            );
            toast.success('Lampiran Dihapus', 'Berkas lampiran berhasil dihapus dari kuitansi.');
        },
    });
}
</script>

<template>
    <Head title="Perbaiki / Revisi Kuitansi" />

    <GovAppLayout>
        <div class="max-w-5xl mx-auto pb-16 space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-ink-muted mb-1">
                        <Link href="/receipts" class="hover:text-accent font-medium">Daftar Kuitansi</Link>
                        <span>/</span>
                        <span class="text-ink font-semibold">Edit / Revisi</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-ink tracking-tight">
                            {{ receipt?.nomor_kuitansi || 'Memuat Kuitansi...' }}
                        </h1>
                        <GovBadge v-if="receipt" :variant="statusMeta.variant">
                            {{ statusMeta.label }}
                        </GovBadge>
                    </div>
                </div>

                <Link href="/receipts">
                    <GovButton variant="secondary">Kembali ke Daftar</GovButton>
                </Link>
            </div>

            <!-- Revision Note Banner -->
            <div
                v-if="receipt && (receipt.status === 'REVISI_MINOR' || receipt.status === 'REVISI_MAYOR')"
                class="p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-950 space-y-1.5"
            >
                <p class="text-xs font-bold  text-amber-800">
                    Catatan Revisi Verifikator ({{ receipt.status === 'REVISI_MINOR' ? 'Revisi Minor' : 'Revisi Mayor' }})
                </p>
                <p class="text-sm font-medium">
                    {{ revisionNote || 'Silakan perbaiki berkas atau rincian sesuai arahan Verifikator.' }}
                </p>
                <p v-if="isMinorRevision" class="text-xs text-amber-800 pt-1">
                    Mode <strong>REVISI_MINOR</strong>: Rekening anggaran, bulan anggaran, dan nominal bruto dikunci otomatis karena pagu telah dicadangkan. Anda dapat memperbaiki rincian deskriptif, pajak kontekstual, serta menghapus/mengunggah ulang berkas lampiran.
                </p>
            </div>

            <div v-if="isLoading" class="py-16 text-center text-sm text-ink-muted">
                Memuat detail kuitansi...
            </div>

            <template v-else-if="receipt">
                <!-- Context Card -->
                <GovCard title="Informasi Rekening & Anggaran" :class="isMinorRevision ? 'bg-raised/70' : ''">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-xs font-semibold text-ink-muted block">Unit Kerja</span>
                            <span class="font-semibold text-ink">{{ receipt.unit_kerja?.nama_unit || receipt.rekening?.unit_kerja?.nama_unit || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-ink-muted block">Rekening Belanja</span>
                            <span class="font-mono text-xs text-accent font-semibold">{{ receipt.rekening?.kode_rekening }}</span>
                            <p class="text-xs text-ink-secondary font-medium">{{ receipt.rekening?.nama_rekening }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-ink-muted block mb-1">
                                Bulan Anggaran
                                <span v-if="isMinorRevision" class="text-amber-700">(Terkunci)</span>
                            </label>
                            <select
                                v-model.number="form.bulan_anggaran"
                                :disabled="isMinorRevision"
                                class="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm disabled:bg-raised disabled:text-ink-muted"
                            >
                                <option v-for="b in bulanOptions" :key="b.value" :value="b.value">
                                    {{ b.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </GovCard>

                <!-- Form Detail Kuitansi -->
                <GovCard title="Detail Transaksi & Penerima">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <GovInput
                            v-model="form.nomor_bukti_toko"
                            label="Nomor Bukti / Faktur Toko"
                            placeholder="Contoh: INV/2026/001"
                        />
                        <GovInput
                            v-model="form.tanggal_transaksi"
                            label="Tanggal Transaksi"
                            type="date"
                            required
                        />
                        <GovInput
                            v-model="form.nama_penerima"
                            label="Penerima / Nama Toko / Penyedia"
                            required
                        />
                        <GovInput
                            v-model="form.npwp_penerima"
                            label="NPWP Penerima (Opsional)"
                            placeholder="Kosongkan jika Non-NPWP"
                        />
                        <div class="md:col-span-2">
                            <GovInput
                                v-model="form.uraian_belanja"
                                label="Uraian Belanja"
                                placeholder="Keterangan ringkas transaksi belanja"
                            />
                        </div>
                    </div>

                    <!-- Parameter Pajak Kontekstual -->
                    <div class="mt-4 pt-4 border-t border-hairline grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <label class="flex items-center gap-2 text-ink-secondary">
                            <input v-model="form.has_npwp" type="checkbox" class="rounded border-hairline text-accent focus:ring-accent" />
                            <span>Penerima Memiliki NPWP</span>
                        </label>

                        <div v-if="normalizedCategory === 'B.3'">
                            <label class="block text-xs font-semibold text-ink-secondary mb-1">Golongan ASN / Penerima Honor</label>
                            <select v-model="form.golongan_asn" class="w-full rounded-lg border border-hairline bg-surface px-3 py-1.5 text-sm">
                                <option value="GOLONGAN_I_II">PNS Golongan I / II (0%)</option>
                                <option value="GOLONGAN_III">Golongan III (5%)</option>
                                <option value="GOLONGAN_IV">Golongan IV (15%)</option>
                                <option value="NON_ASN">Non-ASN / Eksternal (2.5%)</option>
                            </select>
                        </div>

                        <label v-if="normalizedCategory === 'B.4'" class="flex items-center gap-2 text-ink-secondary">
                            <input v-model="form.is_sewa_kendaraan" type="checkbox" class="rounded border-hairline text-accent focus:ring-accent" />
                            <span>Transaksi Sewa Kendaraan (PPh 23 2%)</span>
                        </label>

                        <label v-if="normalizedCategory === 'B.5'" class="flex items-center gap-2 text-ink-secondary">
                            <input v-model="form.is_pkp" type="checkbox" class="rounded border-hairline text-accent focus:ring-accent" />
                            <span>Penyedia Pengusaha Kena Pajak (PKP)</span>
                        </label>
                    </div>
                </GovCard>

                <!-- Pelaksana & Foto Keberangkatan SPPD (Khusus B.4) -->
                <SppdParticipantsSection
                    v-if="isSppdCategory"
                    v-model="sppdPeserta"
                    v-model:group-photo="sppdGroupPhoto"
                    v-model:remove-group-photo="sppdRemoveGroupPhoto"
                    :existing-group-photo-url="receipt?.sppd_foto_url || null"
                    :exclude-kuitansi-id="receipt?.id || null"
                    :default-date="form.tanggal_transaksi"
                    @conflict-change="sppdHasConflict = $event"
                />

                <!-- Rincian Item & Simulasi Pajak -->
                <GovCard title="Rincian Item Belanja & Simulasi Pajak">
                    <div class="space-y-3">
                        <div
                            v-for="(item, idx) in form.items"
                            :key="idx"
                            class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end bg-raised p-3 rounded-lg border border-hairline"
                        >
                            <div class="md:col-span-4">
                                <GovInput v-model="item.nama_barang" label="Nama Barang / Jasa" required />
                            </div>
                            <div class="md:col-span-2">
                                <GovInput
                                    v-model.number="item.volume"
                                    label="Volume"
                                    type="number"
                                    step="0.01"
                                    :disabled="isMinorRevision"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <GovInput v-model="item.satuan" label="Satuan" />
                            </div>
                            <div class="md:col-span-3">
                                <GovCurrencyInput
                                    v-model="item.harga_satuan"
                                    label="Harga Satuan"
                                    :disabled="isMinorRevision"
                                />
                            </div>
                            <div class="md:col-span-1 flex justify-end">
                                <button
                                    v-if="!isMinorRevision && form.items.length > 1"
                                    type="button"
                                    class="text-xs font-semibold text-semantic-danger hover:text-red-800 px-2 py-2"
                                    @click="removeItemRow(idx)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="(validationResult?.sbm_warnings || []).length > 0"
                            role="status"
                            class="p-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs space-y-1"
                        >
                            <div class="font-semibold">Peringatan SBM/SSH (Non-Blocking)</div>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <li v-for="(warn, wIdx) in validationResult.sbm_warnings" :key="wIdx">
                                    {{ warn.message }}
                                </li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <GovButton v-if="!isMinorRevision" type="button" variant="secondary" size="sm" @click="addItemRow">
                                + Tambah Baris Item
                            </GovButton>
                            <span v-else class="text-xs text-amber-700 font-medium">
                                Nominal Bruto terkunci: <span class="font-mono tabular-nums">{{ formatRupiah(receipt.nominal_bruto) }}</span>
                            </span>

                            <div class="text-right text-sm space-y-1">
                                <div>
                                    <span class="text-ink-muted">Total Bruto:</span>
                                    <span class="font-semibold text-ink font-mono tabular-nums ml-2">{{ formatRupiah(totalNilaiBelanja) }}</span>
                                </div>
                                <div class="text-xs text-ink-secondary">
                                    Potongan Pajak ({{ isValidatingTax ? 'menghitung...' : 'backend' }}):
                                    <span class="font-semibold text-semantic-warning font-mono tabular-nums ml-1">{{ formatRupiah(estimasiPajak.totalPajak) }}</span>
                                    | Neto Dibayar:
                                    <span class="font-semibold text-semantic-success font-mono tabular-nums ml-1">{{ formatRupiah(estimasiPajak.totalNetto) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </GovCard>

                <!-- Lampiran Eksisting -->
                <GovCard title="Berkas Lampiran Tersimpan">
                    <div v-if="existingAttachments.length === 0" class="text-sm text-ink-muted italic">
                        Belum ada lampiran tersimpan pada kuitansi ini.
                    </div>
                    <ul v-else class="divide-y divide-hairline">
                        <li
                            v-for="att in existingAttachments"
                            :key="att.id"
                            class="py-3 flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p class="text-sm font-semibold text-ink">
                                    {{ att.nama_dokumen || att.file_name || att.nama_file_asli }}
                                </p>
                                <p class="text-xs text-ink-muted">
                                    Kode: <span class="font-mono">{{ att.checklist_code || '-' }}</span> • {{ att.file_name || att.nama_file_asli }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="`/receipts/${receipt.id}/attachments/${att.id}/preview`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs font-semibold text-accent hover:underline px-2 py-1"
                                >
                                    Pratinjau
                                </a>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-semantic-danger hover:underline px-2 py-1"
                                    @click="openDeleteAttachmentModal(att)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </li>
                    </ul>
                </GovCard>

                <!-- Unggah Berkas Pengganti / Tambahan per Checklist -->
                <GovCard title="Unggah Berkas Pengganti / Tambahan (Checklist)">
                    <DynamicChecklistTable
                        v-model:items="checklistItems"
                        :category-code="normalizedCategory"
                        :category-name="receipt?.rekening?.nama_rekening || ''"
                    />
                </GovCard>

                <!-- Action Bar -->
                <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                    <GovButton variant="secondary" :disabled="isSaving" @click="handleSaveChanges(false)">
                        {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </GovButton>
                    <GovButton variant="primary" :disabled="isSaving" @click="handleSaveChanges(true)">
                        {{
                            isSaving
                                ? 'Memproses...'
                                : isMinorRevision
                                  ? 'Simpan & Kirim Ulang ke Verifikator'
                                  : $page.props.auth?.user?.can_auto_finalize_receipt
                                    ? 'Simpan & Finalisasi'
                                    : 'Simpan & Ajukan Verifikasi'
                        }}
                    </GovButton>
                </div>
            </template>
        </div>
    </GovAppLayout>
</template>
