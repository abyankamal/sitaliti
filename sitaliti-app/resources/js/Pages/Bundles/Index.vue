<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import axios from 'axios';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { settlementService } from '@/Services/settlementService';
import { receiptService } from '@/Services/receiptService';
import { formatRupiah } from '@/Utils/currency';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const { confirm } = useConfirm();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const userRole = computed(() => currentUser.value?.role || '');
const isKelurahanScoped = computed(() => !!currentUser.value?.is_kelurahan_scoped);
const isKelurahanBpp = computed(() => !!currentUser.value?.is_kelurahan_bpp || (userRole.value === 'BPP' && isKelurahanScoped.value));
const isLurah = computed(() => userRole.value === 'LURAH');
const isKpa = computed(() => userRole.value === 'KPA');
const isReadOnly = computed(() => Boolean(currentUser.value?.is_read_only) || isLurah.value || isKpa.value);
const isVerifikator = computed(() => userRole.value === 'VERIFIKATOR');
const isBp = computed(() => userRole.value === 'BP');
const isBpp = computed(() => userRole.value === 'BPP' && isKelurahanScoped.value);

const canCreateBundle = computed(() => {
    if (isLurah.value || isKpa.value) return false;
    return isBp.value || isBpp.value;
});

const canApprovePpk = computed(() => isVerifikator.value);

const canRecordSp2d = computed(() => {
    if (isKelurahanScoped.value || isKpa.value) return false;
    return isBp.value;
});

const canUnbundle = computed(() => {
    if (isLurah.value || isKpa.value) return false;
    return isBp.value || isBpp.value;
});

const bundles = ref([]);
const loading = ref(true);
const showCreateModal = ref(false);
const verifiedReceipts = ref([]);
const loadingReceipts = ref(false);
const isSubmitting = ref(false);
const feedbackBanner = ref(null);

// State Modal Pencatatan SP2D
const showSp2dModal = ref(false);
const selectedBundleForSp2d = ref(null);
const sp2dForm = ref({
    nomor_sp2d: '',
    nomor_spp: '',
    tanggal_sp2d: new Date().toISOString().split('T')[0],
});
const isSubmittingSp2d = ref(false);

// State Modal Input Kode Billing & NTPN
const showTaxModal = ref(false);
const selectedReceiptForTax = ref(null);
const taxFormRows = ref([]);
const isSavingTax = ref(false);

function hasTaxableRows(kw) {
    return (kw?.pajak || []).some((p) => Number(p.nominal) > 0 && p.kode_pajak !== 'BEA_METERAI');
}

function getTaxSettlementBadge(kw) {
    const taxable = (kw?.pajak || []).filter((p) => Number(p.nominal) > 0 && p.kode_pajak !== 'BEA_METERAI');
    if (taxable.length === 0) {
        return { label: 'Non-Pajak / Meterai', class: 'bg-raised text-ink-secondary' };
    }
    const allSettled = taxable.every((p) => Boolean(p.ntpn && String(p.ntpn).trim() !== ''));
    return allSettled
        ? { label: 'NTPN Terverifikasi', class: 'bg-emerald-100 text-emerald-800' }
        : { label: 'Belum Setor NTPN', class: 'bg-amber-100 text-amber-800' };
}

function openTaxModal(kw) {
    selectedReceiptForTax.value = kw;
    taxFormRows.value = (kw?.pajak || [])
        .filter((p) => Number(p.nominal) > 0 && p.kode_pajak !== 'BEA_METERAI')
        .map((p) => ({
            kuitansi_pajak_id: p.id,
            kode_pajak: p.kode_pajak,
            nama_pajak: p.nama_pajak,
            nominal: p.nominal,
            kode_billing: p.kode_billing || '',
            ntpn: p.ntpn || '',
            tanggal_setor: p.tanggal_setor || new Date().toISOString().slice(0, 10),
            bukti_setor_path: p.bukti_setor_path || null,
            file: null,
        }));
    showTaxModal.value = true;
}

async function submitTaxSettlement() {
    if (!selectedReceiptForTax.value) return;
    const validRows = taxFormRows.value.filter((r) => r.ntpn.trim() !== '');
    if (validRows.length === 0) {
        toast.error('NTPN Wajib Diisi', 'Isi minimal satu nomor NTPN sebelum menyimpan.');
        return;
    }

    isSavingTax.value = true;
    try {
        const fd = new FormData();
        validRows.forEach((row, idx) => {
            fd.append(`settlements[${idx}][kuitansi_pajak_id]`, row.kuitansi_pajak_id);
            fd.append(`settlements[${idx}][kode_billing]`, row.kode_billing.trim());
            fd.append(`settlements[${idx}][ntpn]`, row.ntpn.trim());
            if (row.tanggal_setor) {
                fd.append(`settlements[${idx}][tanggal_setor]`, row.tanggal_setor);
            }
            if (row.file) {
                fd.append(`settlements[${idx}][bukti_setor]`, row.file);
            }
        });

        await receiptService.saveTaxSettlement(selectedReceiptForTax.value.kuitansi_id || selectedReceiptForTax.value.id, fd);
        toast.success('NTPN Tersimpan', 'Data Kode Billing & NTPN berhasil diperbarui.');
        showTaxModal.value = false;
        await loadBundles();
    } catch (err) {
        toast.error('Gagal Menyimpan NTPN', err.response?.data?.error?.message || err.response?.data?.message || 'Terjadi kesalahan saat menyimpan data setoran pajak.');
    } finally {
        isSavingTax.value = false;
    }
}

const newBundleForm = ref({
    jenis_bundel: 'SPP_GU',
    tahun_anggaran: new Date().getFullYear(),
    bulan_periode: new Date().getMonth() + 1,
    nomor_spp_sipd: '',
    selected_kuitansi_ids: [],
});

function formatApiError(err, fallback) {
    const data = err.response?.data;
    if (data?.errors) {
        return Object.values(data.errors).flat().join(' ');
    }
    return data?.error?.message || data?.message || err.message || fallback;
}

onMounted(async () => {
    await loadBundles();
});

async function loadBundles() {
    loading.value = true;
    try {
        bundles.value = await settlementService.getBundles();
    } catch (err) {
        toast.error('Gagal Memuat Bundel', formatApiError(err, 'Tidak dapat memuat daftar bundel dari server.'));
    } finally {
        loading.value = false;
    }
}

async function openCreateModal() {
    if (isKelurahanBpp.value) {
        newBundleForm.value.jenis_bundel = 'LPJ_BPP';
    } else {
        newBundleForm.value.jenis_bundel = 'SPP_GU';
    }
    showCreateModal.value = true;
    loadingReceipts.value = true;
    try {
        const res = await axios.get('/api/v1/receipts', {
            params: { status: 'LOLOS_VERIFIKASI', per_page: 100 },
        });
        const payload = res.data?.data;
        const rows = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        verifiedReceipts.value = rows.filter((item) => item.status === 'LOLOS_VERIFIKASI');
    } catch (err) {
        verifiedReceipts.value = [];
        toast.error('Gagal Memuat Kuitansi', formatApiError(err, 'Tidak dapat memuat kuitansi lolos verifikasi.'));
    } finally {
        loadingReceipts.value = false;
    }
}

async function handleCreateBundle() {
    if (newBundleForm.value.selected_kuitansi_ids.length === 0) {
        toast.error('Kuitansi Belum Dipilih', 'Pilih minimal satu kuitansi lolos verifikasi untuk dibundel.');
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await settlementService.createBundle({
            jenis_bundel: newBundleForm.value.jenis_bundel,
            tahun_anggaran: newBundleForm.value.tahun_anggaran,
            bulan_periode: newBundleForm.value.bulan_periode,
            nomor_spp_sipd: newBundleForm.value.nomor_spp_sipd,
            kuitansi_ids: newBundleForm.value.selected_kuitansi_ids,
        });
        showCreateModal.value = false;
        newBundleForm.value.selected_kuitansi_ids = [];
        await loadBundles();
        toast.success('Bundel Dibuat', res.message || 'Bundel berhasil dibuat.');
    } catch (err) {
        toast.error('Gagal Membuat Bundel', formatApiError(err, 'Gagal membuat bundel SPP.'));
    } finally {
        isSubmitting.value = false;
    }
}

function openSp2dModal(bundle) {
    selectedBundleForSp2d.value = bundle;
    sp2dForm.value = {
        nomor_sp2d: '',
        nomor_spp: bundle.nomor_spp || bundle.nomor_spp_sipd || '',
        tanggal_sp2d: new Date().toISOString().split('T')[0],
    };
    showSp2dModal.value = true;
}

async function handleRecordSp2d() {
    if (!sp2dForm.value.nomor_sp2d?.trim()) {
        toast.error('Nomor SP2D Wajib', 'Nomor SP2D SIPD-RI wajib diisi.');
        return;
    }

    await confirm({
        title: 'Konfirmasi Pembukuan SP2D',
        message: `Catat SP2D ${sp2dForm.value.nomor_sp2d}? Tindakan ini akan memindahkan anggaran terkunci menjadi realisasi belanja definitif di sistem.`,
        variant: 'primary',
        confirmText: 'Ya, Catat SP2D',
        onConfirm: async () => {
            const res = await settlementService.recordSp2d(selectedBundleForSp2d.value.id, sp2dForm.value);
            showSp2dModal.value = false;
            toast.success('SP2D Dicatat', res.message || 'Nomor SP2D SIPD-RI berhasil dicatat.');
            await loadBundles();
        },
    });
}

async function openSubmitPpkModal(bundle) {
    await confirm({
        title: 'Ajukan Bundel ke PPK',
        message: `Ajukan bundel ${bundle.nomor_spp || bundle.nomor_bundel} ke PPK untuk pengesahan sebelum pencatatan SP2D?`,
        variant: 'primary',
        confirmText: 'Ajukan ke PPK',
        onConfirm: async () => {
            const res = await settlementService.submitPpk(bundle.id);
            toast.success('Diajukan ke PPK', res.message || 'Bundel berhasil diajukan ke PPK.');
            await loadBundles();
        },
    });
}

async function openApprovePpkModal(bundle) {
    await confirm({
        title: 'Sahkan Bundel (PPK)',
        message: `Sahkan bundel ${bundle.nomor_spp || bundle.nomor_bundel} sebagai PPK? Setelah disahkan, Bendahara dapat mencatat SP2D SIPD-RI.`,
        variant: 'primary',
        confirmText: 'Sahkan Bundel',
        onConfirm: async () => {
            const res = await settlementService.approvePpk(bundle.id);
            toast.success('Disahkan PPK', res.message || 'Bundel berhasil disahkan PPK.');
            await loadBundles();
        },
    });
}

async function handleUnbundle(bundle, kw) {
    const nomor = kw.nomor_kuitansi || kw.id || kw.kuitansi_id;
    const kuitansiId = kw.kuitansi_id || kw.id;

    await confirm({
        title: `Keluarkan Kuitansi ${nomor}`,
        message: `Masukkan alasan mengeluarkan kuitansi ${nomor} dari bundel ini. Status kuitansi akan kembali menjadi Lolos Verifikasi.`,
        variant: 'danger',
        confirmText: 'Keluarkan Kuitansi',
        requireReason: true,
        reasonLabel: 'Alasan Pengeluaran Kuitansi',
        reasonPlaceholder: 'Tuliskan alasan pengeluaran kuitansi dari bundel...',
        minReasonLength: 3,
        onConfirm: async (reason) => {
            const res = await settlementService.unbundleReceipt(bundle.id, kuitansiId, reason);
            toast.success('Kuitansi Dikeluarkan', res.message || 'Kuitansi berhasil dikeluarkan dari bundel.');
            await loadBundles();
        },
    });
}

const BUNDLE_STATUS_VARIANTS = {
    'DRAFT': 'neutral',
    'MENUNGGU_PPK': 'warning',
    'DISETUJUI_PPK': 'success',
    'SYNCED_SIPD': 'primary',
};

const BUNDLE_STATUS_LABELS = {
    'DRAFT': 'Konsep Bundel',
    'MENUNGGU_PPK': 'Menunggu Pengesahan PPK',
    'DISETUJUI_PPK': 'Disetujui PPK',
    'SYNCED_SIPD': 'Terverifikasi SIPD-RI',
};
</script>

<template>
    <Head :title="isKelurahanScoped ? 'Bundel LPJ BPP Kelurahan' : 'Bundel SPJ & Pengesahan SPP'" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-bold text-ink flex items-center gap-2">
                        <span>{{ isKelurahanScoped ? 'Pengelompokan Bundel LPJ BPP' : 'Pengelompokan Bundel SPJ (Two-Tier Settlement)' }}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-md font-semibold bg-accent-soft text-ink border border-accent-border">
                            {{ isKelurahanScoped ? 'Tier 2 Pertanggungjawaban BPP' : 'Tier 2 Pengesahan' }}
                        </span>
                    </h1>
                    <p class="text-xs text-ink-muted mt-1">
                        {{ isKelurahanScoped
                            ? 'Penyatuan kuitansi-kuitansi yang telah Lolos Verifikasi menjadi berkas bundel Laporan Pertanggungjawaban (LPJ-BPP) Kelurahan'
                            : 'Penyatuan kuitansi-kuitansi yang telah Lolos Verifikasi menjadi berkas bundel pengesahan SPP-GU / TU / LS' }}
                    </p>
                </div>
                <GovButton v-if="canCreateBundle" variant="primary" size="sm" @click="openCreateModal">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ isKelurahanBpp ? 'Buat Bundel LPJ-BPP Baru' : 'Buat Bundel SPP Baru' }}
                </GovButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Feedback Banner In-App -->
            <div
                v-if="feedbackBanner"
                :class="[
                    'p-4 rounded-xl border flex items-start justify-between gap-4',
                    feedbackBanner.type === 'error'
                        ? 'bg-rose-50 border-rose-200 text-rose-900'
                        : 'bg-emerald-50 border-emerald-200 text-emerald-900',
                ]"
            >
                <div class="text-xs">
                    <p class="font-bold">{{ feedbackBanner.title }}</p>
                    <p class="mt-0.5 leading-relaxed">{{ feedbackBanner.message }}</p>
                </div>
                <button
                    type="button"
                    class="text-xs font-semibold underline shrink-0"
                    @click="feedbackBanner = null"
                >
                    Tutup
                </button>
            </div>

            <!-- Informasi Two-Tier Model / LPJ-BPP -->
            <div class="p-4 bg-sky-50 border border-sky-200 rounded-xl text-sky-900 text-xs flex items-start gap-3">
                <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div v-if="isKelurahanScoped">
                    <strong>Prinsip Pertanggungjawaban LPJ-BPP:</strong> Kuitansi transaksi atomik (Tier 1) yang telah terbit Lembar Kendali Verifikasinya dihimpun menjadi berkas LPJ-BPP Kelurahan untuk disampaikan dan disahkan oleh Bendahara Pengeluaran SKPD Kecamatan Tarogong Kidul.
                </div>
                <div v-else>
                    <strong>Prinsip Two-Tier Settlement Model:</strong> Kuitansi transaksi atomik (Tier 1) yang telah terbit Lembar Kendali Verifikasinya dihimpun bersama 9 Dokumen Wajib Bagian A (Tier 2: NPD, Pengantar SPJ, BAP Kas, Register Penutupan Kas, Lap. Pengeluaran, SPJ Fungsional, BKU, BP Pajak, Rekening Koran) sebelum diajukan sinkronisasi ke SIPD-RI.
                </div>
            </div>

            <!-- List Bundel Cards -->
            <div v-for="bundle in bundles" :key="bundle.id" class="bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden">
                <div class="bg-raised p-5 border-b border-hairline flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-accent text-white font-mono">
                                {{ bundle.jenis || bundle.jenis_bundel }}
                            </span>
                            <h3 class="text-sm font-bold text-ink">{{ bundle.nomor_spp || bundle.nomor_bundel }}</h3>
                            <GovBadge :variant="BUNDLE_STATUS_VARIANTS[bundle.status || bundle.status_bundel]">
                                {{ BUNDLE_STATUS_LABELS[bundle.status || bundle.status_bundel] }}
                            </GovBadge>
                        </div>
                        <p class="text-xs text-ink-muted mt-1">
                            Unit: {{ bundle.unit || 'Kecamatan Tarogong Kidul' }} • Periode: {{ bundle.periode || '-' }}
                        </p>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-ink-muted">Total Nominal Bundel</div>
                        <div class="text-lg font-semibold text-ink tabular-nums">
                            {{ formatRupiah(bundle.total_nominal || bundle.total_bruto) }}
                        </div>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sisi Kiri: 9 Dokumen Bagian A -->
                    <div>
                        <h4 class="text-xs font-bold text-ink  mb-2.5">
                            Status 9 Dokumen Wajib Pengesahan SPJ (Bagian A)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="(doc, idx) in (bundle.checklist_bagian_a || [])"
                                :key="doc.id || doc.name || idx"
                                class="p-2 rounded border border-hairline bg-raised flex items-center justify-between"
                            >
                                <span class="truncate pr-2 text-ink-secondary text-xs">{{ doc.name }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded text-xs font-bold font-mono"
                                    :class="doc.checked ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ doc.checked ? 'LENGKAP' : 'BELUM' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Kuitansi-Kuitansi yang Tergabung -->
                    <div>
                        <h4 class="text-xs font-bold text-ink  mb-2.5 flex items-center justify-between">
                            <span>Daftar Kuitansi Tergabung (Tier 1)</span>
                            <span class="text-ink-muted text-xs">{{ (bundle.kuitansi_list || bundle.items || []).length }} Bukti Kuitansi</span>
                        </h4>
                        <div class="space-y-2">
                            <div
                                v-for="kw in (bundle.kuitansi_list || bundle.items || [])"
                                :key="kw.id"
                                class="p-2.5 rounded-lg border border-hairline flex items-center justify-between text-xs hover:border-hairline transition-colors"
                            >
                                <div class="flex-1 min-w-0 pr-2">
                                    <div class="font-bold text-ink flex items-center gap-1.5 flex-wrap">
                                        <span>{{ kw.id || kw.nomor_kuitansi }}</span>
                                        <span class="text-xs bg-raised text-ink-secondary px-1.5 py-0.5 rounded font-mono">
                                            {{ kw.kategori || 'B.1' }}
                                        </span>
                                        <span
                                            :class="['text-xs px-1.5 py-0.5 rounded font-semibold', getTaxSettlementBadge(kw).class]"
                                        >
                                            {{ getTaxSettlementBadge(kw).label }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-ink-muted truncate max-w-xs">{{ kw.uraian || kw.uraian_belanja }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        v-if="hasTaxableRows(kw) && bundle.status !== 'SYNCED_SIPD' && !isReadOnly"
                                        type="button"
                                        @click="openTaxModal(kw)"
                                        class="px-2 py-1 rounded text-xs font-semibold border border-accent-border bg-accent-soft text-accent hover:bg-accent-soft transition-colors"
                                    >
                                        Input NTPN
                                    </button>
                                    <div class="text-right">
                                        <div class="font-bold text-ink tabular-nums">{{ formatRupiah(kw.nominal || kw.nominal_bruto) }}</div>
                                        <span class="text-xs text-emerald-700 font-medium">Lolos Verifikasi</span>
                                    </div>
                                    <button
                                        v-if="canUnbundle && (bundle.status || bundle.status_bundel) === 'DRAFT'"
                                        type="button"
                                        @click="handleUnbundle(bundle, kw)"
                                        title="Keluarkan dari bundel ini"
                                        class="p-1 rounded text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 bg-raised border-t border-hairline flex items-center justify-between text-xs flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-ink-muted text-xs">
                            Status:
                            <strong v-if="bundle.status === 'SYNCED_SIPD'" class="text-emerald-700">
                                Anggaran Telah Cair Definitif (SP2D: {{ bundle.nomor_sipd || bundle.nomor_sp2d_sipd }})
                            </strong>
                            <strong v-else class="text-ink-secondary">
                                {{ isKelurahanScoped ? 'Siap Pengesahan oleh Bendahara Pengeluaran Kecamatan' : 'Siap Pengesahan & Pencatatan SP2D SIPD-RI' }}
                            </strong>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <template v-if="(bundle.status || bundle.status_bundel) === 'DRAFT'">
                            <GovButton
                                v-if="canCreateBundle"
                                variant="primary"
                                size="sm"
                                @click="openSubmitPpkModal(bundle)"
                            >
                                Ajukan ke PPK
                            </GovButton>
                        </template>
                        <template v-else-if="(bundle.status || bundle.status_bundel) === 'MENUNGGU_PPK'">
                            <GovButton
                                v-if="canApprovePpk"
                                variant="primary"
                                size="sm"
                                @click="openApprovePpkModal(bundle)"
                            >
                                Sahkan Bundel (PPK)
                            </GovButton>
                            <span v-else class="text-xs text-amber-700 font-medium italic">
                                Menunggu pengesahan PPK (Verifikator)
                            </span>
                        </template>
                        <template v-else-if="(bundle.status || bundle.status_bundel) === 'DISETUJUI_PPK'">
                            <GovButton
                                v-if="canRecordSp2d"
                                variant="success"
                                size="sm"
                                @click="openSp2dModal(bundle)"
                            >
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Catat SP2D SIPD-RI
                            </GovButton>
                            <span v-else-if="isKelurahanScoped" class="text-xs text-ink-muted italic">
                                Pencatatan SP2D dilakukan oleh BP Kecamatan
                            </span>
                            <span v-else-if="isLurah" class="text-xs text-ink-muted italic">
                                Pengawasan Lurah (Read-Only)
                            </span>
                        </template>
                        <span
                            v-else-if="(bundle.status || bundle.status_bundel) === 'SYNCED_SIPD'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200"
                        >
                            Realisasi Definitif Selesai
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Buat Bundel SPP / LPJ-BPP Baru -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-ink/40" @click="showCreateModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-xl bg-surface shadow-floating border border-hairline overflow-hidden">
                    <div class="bg-surface text-ink border-b border-hairline px-6 py-4 flex items-center justify-between">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            {{ isKelurahanBpp ? 'Penyatuan Bundel LPJ-BPP Baru (Kelurahan)' : 'Penyatuan Bundel SPP Baru (Two-Tier Model)' }}
                        </h3>
                        <button type="button" @click="showCreateModal = false" class="text-ink-muted hover:text-ink p-1 rounded" aria-label="Tutup"><X class="w-4 h-4" :stroke-width="1.5" /></button>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-ink mb-1">
                                    {{ isKelurahanBpp ? 'Jenis Pertanggungjawaban *' : 'Jenis Pengajuan SPP *' }}
                                </label>
                                <select v-model="newBundleForm.jenis_bundel" class="w-full text-xs rounded-lg border-hairline py-2 px-3" :disabled="isKelurahanBpp">
                                    <option v-if="isKelurahanBpp" value="LPJ_BPP">LPJ-BPP (Laporan Pertanggungjawaban BPP Kelurahan)</option>
                                    <template v-else>
                                        <option value="SPP_GU">SPP-GU (Ganti Uang Persediaan)</option>
                                        <option value="SPP_TU">SPP-TU (Tambah Uang)</option>
                                        <option value="SPP_LS">SPP-LS (Langsung)</option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-ink mb-1">
                                    {{ isKelurahanBpp ? 'Nomor Referensi LPJ-BPP' : 'Nomor Referensi SPP SIPD-RI' }}
                                </label>
                                <input
                                    v-model="newBundleForm.nomor_spp_sipd"
                                    type="text"
                                    :placeholder="isKelurahanBpp ? 'Contoh: 001/LPJ-BPP/PATARUMAN/2026' : 'Contoh: 0012/SPP-GU/TARKID/2026'"
                                    class="w-full text-xs rounded-lg border-hairline py-2 px-3"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-ink mb-1.5 flex items-center justify-between">
                                <span>Pilih Kuitansi Lolos Verifikasi (Tier 1) *</span>
                                <span class="text-ink-muted font-normal">Hanya kuitansi yang sah bertanda Lolos</span>
                            </label>
                            
                            <div v-if="loadingReceipts" class="p-6 text-center text-ink-muted">
                                Memuat kuitansi lolos verifikasi...
                            </div>
                            <div v-else-if="verifiedReceipts.length === 0" class="p-6 text-center text-ink-muted bg-raised rounded-lg border border-hairline">
                                Belum ada kuitansi baru berstatus LOLOS_VERIFIKASI untuk dibundel.
                            </div>
                            <div v-else class="max-h-60 overflow-y-auto scrollbar-thin space-y-2 border border-hairline rounded-lg p-2">
                                <label
                                    v-for="rec in verifiedReceipts"
                                    :key="rec.kuitansi_id || rec.id"
                                    class="flex items-center justify-between p-2.5 rounded bg-raised hover:bg-raised cursor-pointer border border-hairline"
                                >
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :value="rec.kuitansi_id || rec.id"
                                            v-model="newBundleForm.selected_kuitansi_ids"
                                            class="rounded text-ink focus:ring-accent"
                                        />
                                        <div>
                                            <div class="font-bold text-ink">{{ rec.nomor_kuitansi || rec.id }}</div>
                                            <div class="text-xs text-ink-muted">{{ rec.uraian_belanja || rec.uraian }}</div>
                                        </div>
                                    </div>
                                    <div class="font-bold text-ink tabular-nums">
                                        {{ formatRupiah(rec.nominal_bruto || rec.nominal) }}
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-raised px-6 py-4 border-t border-hairline flex justify-end gap-3">
                        <GovButton variant="secondary" size="sm" @click="showCreateModal = false">Batal</GovButton>
                        <GovButton variant="primary" size="sm" :loading="isSubmitting" @click="handleCreateBundle">
                            Sahkan & Simpan Bundel
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Pencatatan Nomor SP2D SIPD-RI (Camat / KPA) -->
        <div v-if="showSp2dModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-ink/40" @click="showSp2dModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-xl bg-surface shadow-floating border border-hairline overflow-hidden">
                    <div class="bg-surface text-ink border-b border-hairline px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <span>Pencatatan Realisasi SP2D SIPD-RI</span>
                            </h3>
                            <p class="text-xs text-accent-soft mt-0.5 font-mono">
                                Bundel: {{ selectedBundleForSp2d?.nomor_bundel || selectedBundleForSp2d?.nomor_spp }}
                            </p>
                        </div>
                        <button type="button" @click="showSp2dModal = false" class="text-ink-muted hover:text-ink p-1 rounded" aria-label="Tutup"><X class="w-4 h-4" :stroke-width="1.5" /></button>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 leading-relaxed text-xs">
                            <strong>Perhatian (Statutory Rule):</strong> Pencatatan SP2D ini bersifat definitif. Sistem akan mengeksekusi <em>settleBudget()</em> dan memindahkan seluruh saldo kuncian pagu kuitansi pada bundel ini menjadi realisasi riil pembukuan kas daerah.
                        </div>

                        <div>
                            <label class="block font-bold text-ink mb-1">
                                Nomor SP2D Resmi SIPD-RI <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="sp2dForm.nomor_sp2d"
                                type="text"
                                required
                                placeholder="Contoh: 0042/SP2D-GU/TARKID/2026"
                                class="w-full text-xs rounded-lg border-hairline py-2 px-3 font-mono focus:border-accent focus:ring-accent"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-ink mb-1">Nomor Pengantar SPP</label>
                                <input
                                    v-model="sp2dForm.nomor_spp"
                                    type="text"
                                    placeholder="Nomor SPP"
                                    class="w-full text-xs rounded-lg border-hairline py-2 px-3 font-mono"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-ink mb-1">Tanggal Terbit SP2D</label>
                                <input
                                    v-model="sp2dForm.tanggal_sp2d"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-hairline py-2 px-3"
                                />
                            </div>
                        </div>

                        <div class="p-3 bg-raised border border-hairline rounded-lg space-y-1 text-xs">
                            <div class="flex justify-between text-ink-muted">
                                <span>Jumlah Kuitansi:</span>
                                <span class="font-bold text-ink">{{ (selectedBundleForSp2d?.kuitansi_list || selectedBundleForSp2d?.items || []).length }} berkas</span>
                            </div>
                            <div class="flex justify-between text-ink-muted">
                                <span>Total Belanja Definitif:</span>
                                <span class="font-bold text-emerald-700 text-xs tabular-nums">
                                    {{ formatRupiah(selectedBundleForSp2d?.total_nominal || selectedBundleForSp2d?.total_bruto) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-raised px-6 py-4 border-t border-hairline flex justify-end gap-3">
                        <GovButton variant="secondary" size="sm" @click="showSp2dModal = false">Batal</GovButton>
                        <GovButton
                            variant="success"
                            size="sm"
                            :loading="isSubmittingSp2d"
                            @click="handleRecordSp2d"
                        >
                            Konfirmasi Pembukuan SP2D
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Input Kode Billing & NTPN per Baris Pajak -->
        <div
            v-if="showTaxModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40"
        >
            <div class="bg-surface rounded-xl shadow-floating max-w-2xl w-full border border-hairline overflow-hidden">
                <div class="px-6 py-4 bg-surface text-ink border-b border-hairline font-bold flex items-center justify-between">
                    <span class="text-sm">Setoran Pajak (Kode Billing &amp; NTPN) — {{ selectedReceiptForTax?.id }}</span>
                    <button type="button" @click="showTaxModal = false" class="text-ink-muted hover:text-ink p-1 rounded" aria-label="Tutup"><X class="w-4 h-4" :stroke-width="1.5" /></button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto scrollbar-thin">
                    <div
                        v-for="row in taxFormRows"
                        :key="row.kuitansi_pajak_id"
                        class="p-3 rounded-lg border border-hairline bg-raised space-y-2.5 text-xs"
                    >
                        <div class="flex items-center justify-between font-bold text-ink">
                            <span>{{ row.nama_pajak }} ({{ row.kode_pajak }})</span>
                            <span class="tabular-nums">{{ formatRupiah(row.nominal) }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label class="block text-ink-secondary mb-1">Kode Billing</label>
                                <input
                                    v-model="row.kode_billing"
                                    type="text"
                                    placeholder="15 digit Kode Billing"
                                    class="w-full rounded border-hairline text-xs py-1.5 px-2"
                                />
                            </div>
                            <div>
                                <label class="block text-ink-secondary mb-1">NTPN *</label>
                                <input
                                    v-model="row.ntpn"
                                    type="text"
                                    placeholder="Nomor NTPN"
                                    class="w-full rounded border-hairline text-xs py-1.5 px-2 font-mono"
                                />
                            </div>
                            <div>
                                <label class="block text-ink-secondary mb-1">Tanggal Setor</label>
                                <input
                                    v-model="row.tanggal_setor"
                                    type="date"
                                    class="w-full rounded border-hairline text-xs py-1.5 px-2"
                                />
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1">
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                @change="(e) => { row.file = e.target.files?.[0] || null; }"
                                class="text-xs text-ink-secondary"
                            />
                            <a
                                v-if="row.bukti_setor_path"
                                :href="`/api/v1/receipts/${selectedReceiptForTax?.kuitansi_id}/tax-settlement/${row.kuitansi_pajak_id}/preview`"
                                target="_blank"
                                class="text-xs font-semibold text-accent underline shrink-0"
                            >
                                Lihat Bukti Tersimpan
                            </a>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3 bg-raised border-t border-hairline flex justify-end gap-2">
                    <GovButton variant="secondary" size="sm" @click="showTaxModal = false">Batal</GovButton>
                    <GovButton variant="primary" size="sm" :loading="isSavingTax" @click="submitTaxSettlement">
                        Simpan NTPN
                    </GovButton>
                </div>
            </div>
        </div>
    </GovAppLayout>
</template>
