<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { settlementService } from '@/Services/settlementService';
import { formatRupiah } from '@/Utils/currency';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const userRole = computed(() => currentUser.value?.role || '');
const isKelurahanScoped = computed(() => !!currentUser.value?.is_kelurahan_scoped);
const isKelurahanBpp = computed(() => !!currentUser.value?.is_kelurahan_bpp || (userRole.value === 'BPP' && isKelurahanScoped.value));
const isLurah = computed(() => userRole.value === 'LURAH');
const isKpa = computed(() => userRole.value === 'KPA');
const isVerifikator = computed(() => userRole.value === 'VERIFIKATOR' || isKpa.value);
const isBp = computed(() => userRole.value === 'BENDAHARA_PENGELUARAN' || (userRole.value === 'BPP' && !isKelurahanScoped.value));
const isBpp = computed(() => userRole.value === 'BPP' && isKelurahanScoped.value);

const canCreateBundle = computed(() => {
    if (isLurah.value) return false;
    return isBp.value || isBpp.value || isKpa.value;
});

const canApprovePpk = computed(() => isVerifikator.value);

const canRecordSp2d = computed(() => {
    if (isKelurahanScoped.value) return false;
    return isBp.value || isKpa.value || userRole.value === 'CAMAT';
});

const canUnbundle = computed(() => {
    if (isLurah.value) return false;
    return isBp.value || isBpp.value || isKpa.value;
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

// State Modal Konfirmasi In-App (submitPpk, approvePpk, unbundle, sp2dConfirm)
const actionModal = ref({
    open: false,
    type: null, // 'SUBMIT_PPK' | 'APPROVE_PPK' | 'UNBUNDLE' | 'CONFIRM_SP2D'
    title: '',
    message: '',
    confirmLabel: 'Ya, Lanjutkan',
    variant: 'primary',
    requireReason: false,
    reason: '',
    bundle: null,
    kuitansi: null,
});
const isProcessingAction = ref(false);

const newBundleForm = ref({
    jenis_bundel: 'SPP_GU',
    tahun_anggaran: 2026,
    bulan_periode: 6,
    nomor_spp_sipd: '',
    selected_kuitansi_ids: [],
});

function showFeedback(type, title, message) {
    feedbackBanner.value = { type, title, message };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

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
        showFeedback('error', 'Gagal Memuat Bundel', formatApiError(err, 'Tidak dapat memuat daftar bundel dari server.'));
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
        showFeedback('error', 'Gagal Memuat Kuitansi', formatApiError(err, 'Tidak dapat memuat kuitansi lolos verifikasi.'));
    } finally {
        loadingReceipts.value = false;
    }
}

async function handleCreateBundle() {
    if (newBundleForm.value.selected_kuitansi_ids.length === 0) {
        showFeedback('error', 'Kuitansi Belum Dipilih', 'Pilih minimal satu kuitansi lolos verifikasi untuk dibundel.');
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
        showFeedback('success', 'Bundel Dibuat', res.message || 'Bundel berhasil dibuat.');
    } catch (err) {
        showFeedback('error', 'Gagal Membuat Bundel', formatApiError(err, 'Gagal membuat bundel SPP.'));
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

function handleRecordSp2d() {
    if (!sp2dForm.value.nomor_sp2d?.trim()) {
        showFeedback('error', 'Nomor SP2D Wajib', 'Nomor SP2D SIPD-RI wajib diisi.');
        return;
    }

    actionModal.value = {
        open: true,
        type: 'CONFIRM_SP2D',
        title: 'Konfirmasi Pembukuan SP2D',
        message: `Catat SP2D ${sp2dForm.value.nomor_sp2d}? Tindakan ini akan memindahkan anggaran terkunci menjadi realisasi belanja definitif di sistem.`,
        confirmLabel: 'Ya, Catat SP2D',
        variant: 'success',
        requireReason: false,
        reason: '',
        bundle: selectedBundleForSp2d.value,
        kuitansi: null,
    };
}

function openSubmitPpkModal(bundle) {
    actionModal.value = {
        open: true,
        type: 'SUBMIT_PPK',
        title: 'Ajukan Bundel ke PPK',
        message: `Ajukan bundel ${bundle.nomor_spp || bundle.nomor_bundel} ke PPK untuk pengesahan sebelum pencatatan SP2D?`,
        confirmLabel: 'Ajukan ke PPK',
        variant: 'primary',
        requireReason: false,
        reason: '',
        bundle,
        kuitansi: null,
    };
}

function openApprovePpkModal(bundle) {
    actionModal.value = {
        open: true,
        type: 'APPROVE_PPK',
        title: 'Sahkan Bundel (PPK)',
        message: `Sahkan bundel ${bundle.nomor_spp || bundle.nomor_bundel} sebagai PPK? Setelah disahkan, Bendahara dapat mencatat SP2D SIPD-RI.`,
        confirmLabel: 'Sahkan Bundel',
        variant: 'success',
        requireReason: false,
        reason: '',
        bundle,
        kuitansi: null,
    };
}

function handleUnbundle(bundle, kw) {
    const nomor = kw.nomor_kuitansi || kw.id || kw.kuitansi_id;
    actionModal.value = {
        open: true,
        type: 'UNBUNDLE',
        title: `Keluarkan Kuitansi ${nomor}`,
        message: `Masukkan alasan mengeluarkan kuitansi ${nomor} dari bundel ini. Status kuitansi akan kembali menjadi Lolos Verifikasi.`,
        confirmLabel: 'Keluarkan Kuitansi',
        variant: 'danger',
        requireReason: true,
        reason: 'Penyesuaian berkas SPP',
        bundle,
        kuitansi: kw,
    };
}

async function executeActionModal() {
    const modal = actionModal.value;
    if (modal.requireReason && !modal.reason.trim()) {
        showFeedback('error', 'Alasan Wajib Diisi', 'Mohon isi alasan sebelum melanjutkan.');
        return;
    }

    isProcessingAction.value = true;
    try {
        if (modal.type === 'SUBMIT_PPK') {
            const res = await settlementService.submitPpk(modal.bundle.id);
            showFeedback('success', 'Diajukan ke PPK', res.message || 'Bundel berhasil diajukan ke PPK.');
        } else if (modal.type === 'APPROVE_PPK') {
            const res = await settlementService.approvePpk(modal.bundle.id);
            showFeedback('success', 'Disahkan PPK', res.message || 'Bundel berhasil disahkan PPK.');
        } else if (modal.type === 'UNBUNDLE') {
            const kuitansiId = modal.kuitansi.kuitansi_id || modal.kuitansi.id;
            const res = await settlementService.unbundleReceipt(modal.bundle.id, kuitansiId, modal.reason.trim());
            showFeedback('success', 'Kuitansi Dikeluarkan', res.message || 'Kuitansi berhasil dikeluarkan dari bundel.');
        } else if (modal.type === 'CONFIRM_SP2D') {
            isSubmittingSp2d.value = true;
            const res = await settlementService.recordSp2d(modal.bundle.id, sp2dForm.value);
            showSp2dModal.value = false;
            showFeedback('success', 'SP2D Dicatat', res.message || 'Nomor SP2D SIPD-RI berhasil dicatat.');
        }

        actionModal.value.open = false;
        await loadBundles();
    } catch (err) {
        actionModal.value.open = false;
        showFeedback('error', 'Operasi Gagal', formatApiError(err, 'Gagal memproses aksi bundel.'));
    } finally {
        isProcessingAction.value = false;
        isSubmittingSp2d.value = false;
    }
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
                    <h1 class="text-lg font-bold text-gov-navy-950 flex items-center gap-2">
                        <span>{{ isKelurahanScoped ? 'Pengelompokan Bundel LPJ BPP' : 'Pengelompokan Bundel SPJ (Two-Tier Settlement)' }}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200">
                            {{ isKelurahanScoped ? 'Tier 2 Pertanggungjawaban BPP' : 'Tier 2 Pengesahan' }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ isKelurahanScoped
                            ? 'Penyatuan kuitansi-kuitansi yang telah Lolos Verifikasi menjadi berkas bundel Laporan Pertanggungjawaban (LPJ-BPP) Kelurahan'
                            : 'Penyatuan kuitansi-kuitansi yang telah Lolos Verifikasi menjadi berkas bundel pengesahan SPP-GU / TU / LS' }}
                    </p>
                </div>
                <GovButton v-if="canCreateBundle" variant="gold" size="sm" @click="openCreateModal">
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
            <div v-for="bundle in bundles" :key="bundle.id" class="bg-white rounded-xl border border-gov-surface-border shadow-sm overflow-hidden">
                <div class="bg-gov-surface-subtle p-5 border-b border-gov-surface-border flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-gov-navy-900 text-white font-mono">
                                {{ bundle.jenis || bundle.jenis_bundel }}
                            </span>
                            <h3 class="text-sm font-bold text-gov-navy-950">{{ bundle.nomor_spp || bundle.nomor_bundel }}</h3>
                            <GovBadge :variant="BUNDLE_STATUS_VARIANTS[bundle.status || bundle.status_bundel]">
                                {{ BUNDLE_STATUS_LABELS[bundle.status || bundle.status_bundel] }}
                            </GovBadge>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Unit: {{ bundle.unit || 'Kecamatan Tarogong Kidul' }} • Periode: {{ bundle.periode || 'Juni 2026' }}
                        </p>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-slate-500">Total Nominal Bundel</div>
                        <div class="text-lg font-black text-gov-navy-900 tabular-nums">
                            {{ formatRupiah(bundle.total_nominal || bundle.total_bruto) }}
                        </div>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sisi Kiri: 9 Dokumen Bagian A -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5">
                            Status 9 Dokumen Wajib Pengesahan SPJ (Bagian A)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="(status, docName) in (bundle.checklist_dokumen || {
                                    '1. Nota Pencairan Dana (NPD)': 'LENGKAP',
                                    '2. Lembar Pengantar SPJ': 'LENGKAP',
                                    '3. BAP Kas & Register Penutupan': 'LENGKAP',
                                    '4. Laporan Pengeluaran Kas': 'LENGKAP',
                                    '5. SPJ Fungsional': 'LENGKAP',
                                    '6. Buku Kas Umum (BKU)': 'LENGKAP',
                                    '7. Buku Pembantu Pajak': 'LENGKAP',
                                    '8. Rekening Koran Bank BJB': 'LENGKAP'
                                })"
                                :key="docName"
                                class="p-2 rounded border border-slate-200 bg-slate-50 flex items-center justify-between"
                            >
                                <span class="truncate pr-2 text-slate-700 text-[11px]">{{ docName }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono"
                                    :class="status === 'LENGKAP' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Kuitansi-Kuitansi yang Tergabung -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                            <span>Daftar Kuitansi Tergabung (Tier 1)</span>
                            <span class="text-slate-500 text-[11px]">{{ (bundle.kuitansi_list || bundle.items || []).length }} Bukti Kuitansi</span>
                        </h4>
                        <div class="space-y-2">
                            <div
                                v-for="kw in (bundle.kuitansi_list || bundle.items || [])"
                                :key="kw.id"
                                class="p-2.5 rounded-lg border border-slate-200 flex items-center justify-between text-xs hover:border-slate-300 transition-colors"
                            >
                                <div class="flex-1 min-w-0 pr-2">
                                    <div class="font-bold text-gov-navy-950 flex items-center gap-1.5 flex-wrap">
                                        <span>{{ kw.id || kw.nomor_kuitansi }}</span>
                                        <span class="text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.2 rounded font-mono">
                                            {{ kw.kategori || 'B.1' }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate max-w-xs">{{ kw.uraian || kw.uraian_belanja }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="text-right">
                                        <div class="font-bold text-slate-800 tabular-nums">{{ formatRupiah(kw.nominal || kw.nominal_bruto) }}</div>
                                        <span class="text-[10px] text-emerald-700 font-medium">Lolos Verifikasi</span>
                                    </div>
                                    <button
                                        v-if="canUnbundle && (bundle.status || bundle.status_bundel) !== 'SYNCED_SIPD'"
                                        type="button"
                                        @click="handleUnbundle(bundle, kw)"
                                        :disabled="isProcessingAction"
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

                <div class="px-5 py-3 bg-slate-50 border-t border-gov-surface-border flex items-center justify-between text-xs flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500 text-[11px]">
                            Status:
                            <strong v-if="bundle.status === 'SYNCED_SIPD'" class="text-emerald-700">
                                Anggaran Telah Cair Definitif (SP2D: {{ bundle.nomor_sipd || bundle.nomor_sp2d_sipd }})
                            </strong>
                            <strong v-else class="text-slate-700">
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
                                variant="gold"
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
                            <span v-else-if="isKelurahanScoped" class="text-xs text-slate-500 italic">
                                Pencatatan SP2D dilakukan oleh BP Kecamatan
                            </span>
                            <span v-else-if="isLurah" class="text-xs text-slate-500 italic">
                                Pengawasan Lurah (Read-Only)
                            </span>
                        </template>
                        <span
                            v-else-if="(bundle.status || bundle.status_bundel) === 'SYNCED_SIPD'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200"
                        >
                            ✓ Realisasi Definitif Selesai
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Buat Bundel SPP / LPJ-BPP Baru -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm" @click="showCreateModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-gov-surface-border overflow-hidden">
                    <div class="bg-gov-navy-950 text-white px-6 py-4 flex items-center justify-between">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            {{ isKelurahanBpp ? 'Penyatuan Bundel LPJ-BPP Baru (Kelurahan)' : 'Penyatuan Bundel SPP Baru (Two-Tier Model)' }}
                        </h3>
                        <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">
                                    {{ isKelurahanBpp ? 'Jenis Pertanggungjawaban *' : 'Jenis Pengajuan SPP *' }}
                                </label>
                                <select v-model="newBundleForm.jenis_bundel" class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3" :disabled="isKelurahanBpp">
                                    <option v-if="isKelurahanBpp" value="LPJ_BPP">LPJ-BPP (Laporan Pertanggungjawaban BPP Kelurahan)</option>
                                    <template v-else>
                                        <option value="SPP_GU">SPP-GU (Ganti Uang Persediaan)</option>
                                        <option value="SPP_TU">SPP-TU (Tambah Uang)</option>
                                        <option value="SPP_LS">SPP-LS (Langsung)</option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">
                                    {{ isKelurahanBpp ? 'Nomor Referensi LPJ-BPP' : 'Nomor Referensi SPP SIPD-RI' }}
                                </label>
                                <input
                                    v-model="newBundleForm.nomor_spp_sipd"
                                    type="text"
                                    :placeholder="isKelurahanBpp ? 'Contoh: 001/LPJ-BPP/PATARUMAN/2026' : 'Contoh: 0012/SPP-GU/TARKID/2026'"
                                    class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gov-navy-900 mb-1.5 flex items-center justify-between">
                                <span>Pilih Kuitansi Lolos Verifikasi (Tier 1) *</span>
                                <span class="text-slate-500 font-normal">Hanya kuitansi yang sah bertanda Lolos</span>
                            </label>
                            
                            <div v-if="loadingReceipts" class="p-6 text-center text-slate-400">
                                Memuat kuitansi lolos verifikasi...
                            </div>
                            <div v-else-if="verifiedReceipts.length === 0" class="p-6 text-center text-slate-500 bg-slate-50 rounded-lg border border-slate-200">
                                Belum ada kuitansi baru berstatus LOLOS_VERIFIKASI untuk dibundel.
                            </div>
                            <div v-else class="max-h-60 overflow-y-auto space-y-2 border border-slate-200 rounded-lg p-2">
                                <label
                                    v-for="rec in verifiedReceipts"
                                    :key="rec.kuitansi_id || rec.id"
                                    class="flex items-center justify-between p-2.5 rounded bg-slate-50 hover:bg-slate-100 cursor-pointer border border-slate-200"
                                >
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :value="rec.kuitansi_id || rec.id"
                                            v-model="newBundleForm.selected_kuitansi_ids"
                                            class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                        />
                                        <div>
                                            <div class="font-bold text-gov-navy-950">{{ rec.nomor_kuitansi || rec.id }}</div>
                                            <div class="text-[11px] text-slate-500">{{ rec.uraian_belanja || rec.uraian }}</div>
                                        </div>
                                    </div>
                                    <div class="font-bold text-gov-navy-900 tabular-nums">
                                        {{ formatRupiah(rec.nominal_bruto || rec.nominal) }}
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                        <GovButton variant="secondary" size="sm" @click="showCreateModal = false">Batal</GovButton>
                        <GovButton variant="gold" size="sm" :loading="isSubmitting" @click="handleCreateBundle">
                            Sahkan & Simpan Bundel
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Pencatatan Nomor SP2D SIPD-RI (Camat / KPA) -->
        <div v-if="showSp2dModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm" @click="showSp2dModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gov-surface-border overflow-hidden">
                    <div class="bg-gov-navy-950 text-white px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <span>Pencatatan Realisasi SP2D SIPD-RI</span>
                            </h3>
                            <p class="text-[11px] text-gov-gold-400 mt-0.5 font-mono">
                                Bundel: {{ selectedBundleForSp2d?.nomor_bundel || selectedBundleForSp2d?.nomor_spp }}
                            </p>
                        </div>
                        <button type="button" @click="showSp2dModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 leading-relaxed text-[11px]">
                            <strong>Perhatian (Statutory Rule):</strong> Pencatatan SP2D ini bersifat definitif. Sistem akan mengeksekusi <em>settleBudget()</em> dan memindahkan seluruh saldo kuncian pagu kuitansi pada bundel ini menjadi realisasi riil pembukuan kas daerah.
                        </div>

                        <div>
                            <label class="block font-bold text-gov-navy-900 mb-1">
                                Nomor SP2D Resmi SIPD-RI <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="sp2dForm.nomor_sp2d"
                                type="text"
                                required
                                placeholder="Contoh: 0042/SP2D-GU/TARKID/2026"
                                class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3 font-mono focus:border-gov-navy-900 focus:ring-gov-navy-900"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">Nomor Pengantar SPP</label>
                                <input
                                    v-model="sp2dForm.nomor_spp"
                                    type="text"
                                    placeholder="Nomor SPP"
                                    class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3 font-mono"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">Tanggal Terbit SP2D</label>
                                <input
                                    v-model="sp2dForm.tanggal_sp2d"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3"
                                />
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1 text-[11px]">
                            <div class="flex justify-between text-slate-500">
                                <span>Jumlah Kuitansi:</span>
                                <span class="font-bold text-slate-800">{{ (selectedBundleForSp2d?.kuitansi_list || selectedBundleForSp2d?.items || []).length }} berkas</span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>Total Belanja Definitif:</span>
                                <span class="font-bold text-emerald-700 text-xs tabular-nums">
                                    {{ formatRupiah(selectedBundleForSp2d?.total_nominal || selectedBundleForSp2d?.total_bruto) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
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

        <!-- Modal Konfirmasi Aksi In-App -->
        <div v-if="actionModal.open" class="fixed inset-0 z-[60] overflow-y-auto">
            <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm" @click="actionModal.open = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
                    <h3 class="text-base font-bold text-gov-navy-950">{{ actionModal.title }}</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">{{ actionModal.message }}</p>

                    <div v-if="actionModal.requireReason">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alasan *</label>
                        <textarea
                            v-model="actionModal.reason"
                            rows="3"
                            class="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:border-gov-navy-900 focus:ring-gov-navy-900"
                            placeholder="Tuliskan alasan..."
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <GovButton variant="secondary" size="sm" :disabled="isProcessingAction" @click="actionModal.open = false">
                            Batal
                        </GovButton>
                        <GovButton :variant="actionModal.variant" size="sm" :loading="isProcessingAction" @click="executeActionModal">
                            {{ actionModal.confirmLabel }}
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>
    </GovAppLayout>
</template>
