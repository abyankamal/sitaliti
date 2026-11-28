<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { X, Loader2, AlertCircle } from 'lucide-vue-next';
import axios from 'axios';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import AuditTimeline from '@/Components/UI/AuditTimeline.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { getStatusMeta } from '@/Constants/workflowStatus';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    receiptId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['close', 'refresh']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const userRole = computed(() => currentUser.value?.role || '');

const canMutateReceipt = computed(() => {
    if (!receipt.value) return false;
    if (['LURAH', 'KPA'].includes(userRole.value)) return false;
    if (userRole.value === 'VERIFIKATOR') return true;
    if (['BPP', 'BP'].includes(userRole.value)) {
        return Number(receipt.value.unit_kerja_id) === Number(currentUser.value?.unit_kerja_id);
    }
    return false;
});

const canVerifyReceipt = computed(() => ['BP', 'VERIFIKATOR'].includes(userRole.value));

const receipt = ref(null);
const isLoading = ref(false);
const isActionLoading = ref(false);
const errorMessage = ref('');

// Computed untuk memisahkan potongan pajak rekanan dan Bea Meterai dokumen
const taxDeductions = computed(() => (receipt.value?.pajak || []).filter(p => p.kode_pajak !== 'BEA_METERAI'));
const meteraiTax = computed(() => (receipt.value?.pajak || []).find(p => p.kode_pajak === 'BEA_METERAI'));

const toast = useToast();
const { confirm } = useConfirm();

// Load detail kuitansi saat receiptId berubah atau drawer dibuka
watch(
    () => [props.show, props.receiptId],
    async ([newShow, newId]) => {
        if (newShow && newId) {
            await fetchReceiptDetail(newId);
        } else if (!newShow) {
            receipt.value = null;
            errorMessage.value = '';
        }
    }
);

async function fetchReceiptDetail(id) {
    isLoading.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.get(`/api/v1/receipts/${id}`);
        if (response.data?.success) {
            receipt.value = response.data.data;
        } else {
            errorMessage.value = response.data?.message || 'Gagal mengambil detail kuitansi.';
        }
    } catch (err) {
        console.error('Fetch receipt detail error:', err);
        errorMessage.value = err.response?.data?.error?.message || err.response?.data?.message || 'Terjadi kesalahan jaringan.';
    } finally {
        isLoading.value = false;
    }
}

function closeDrawer() {
    emit('close');
}

// Penanganan tombol ESC untuk menutup drawer
function handleKeyDown(e) {
    if (e.key === 'Escape' && props.show) {
        closeDrawer();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

async function openActionModal(mode) {
    if (!receipt.value) return;

    const configs = {
        SUBMIT_PPTK: {
            title: 'Ajukan Kuitansi ke PPTK?',
            message: 'Kuitansi akan dikunci dan pagu anggaran kas akan direservasi.',
            confirmText: 'Ya, Ajukan ke PPTK',
            endpoint: `/api/v1/receipts/${receipt.value.id}/submit`,
            payload: {},
            successMsg: 'Kuitansi berhasil diajukan ke PPTK. Pagu anggaran kas telah dikunci.',
        },
        RECALL_DRAFT: {
            title: 'Tarik Kembali ke DRAFT?',
            message: 'Kuitansi akan dikembalikan ke status DRAFT dan pagu anggaran kas dipulihkan.',
            confirmText: 'Ya, Tarik ke DRAFT',
            endpoint: `/api/v1/receipts/${receipt.value.id}/recall`,
            payload: { alasan: 'Ditarik kembali ke DRAFT oleh Operator untuk perbaikan data' },
            successMsg: 'Kuitansi berhasil ditarik kembali ke DRAFT dan pagu anggaran telah dipulihkan.',
        },
        PPTK_APPROVE: {
            title: 'Setujui Kuitansi (PPTK)?',
            message: 'Kuitansi akan disetujui oleh PPTK dan diteruskan ke tahap berikutnya.',
            confirmText: 'Ya, Setujui',
            endpoint: `/api/v1/receipts/${receipt.value.id}/pptk-approve`,
            payload: { catatan: 'Disetujui oleh PPTK' },
            successMsg: 'Kuitansi disetujui PPTK dan berhasil diteruskan ke tahap berikutnya.',
        },
    };

    const cfg = configs[mode];
    if (!cfg) return;

    await confirm({
        title: cfg.title,
        message: cfg.message,
        details: receipt.value.nomor_bukti ? `No. Bukti: ${receipt.value.nomor_bukti}` : '',
        variant: 'primary',
        confirmText: cfg.confirmText,
        onConfirm: async () => {
            const response = await axios.post(cfg.endpoint, cfg.payload);
            if (response.data?.success) {
                toast.success(cfg.successMsg);
                emit('refresh');
                await fetchReceiptDetail(receipt.value.id);
            }
        },
    });
}

// Aksi Penolakan (PPTK)
async function openRejectModal() {
    if (!receipt.value) return;
    const endpoint = `/api/v1/receipts/${receipt.value.id}/pptk-reject`;

    await confirm({
        title: 'Tolak Kuitansi (PPTK)?',
        message: 'Kuitansi akan dikembalikan ke DRAFT Operator & pagu anggaran kas akan dipulihkan.',
        variant: 'danger',
        confirmText: 'Tolak & Kembalikan',
        requireReason: true,
        reasonLabel: 'Alasan Penolakan',
        reasonPlaceholder: 'Tuliskan alasan penolakan minimal 5 karakter...',
        minReasonLength: 5,
        onConfirm: async (reason) => {
            const response = await axios.post(endpoint, { alasan: reason });
            if (response.data?.success) {
                toast.success('Kuitansi berhasil dikembalikan ke DRAFT Operator & pagu anggaran kas telah dipulihkan.');
                emit('refresh');
                await fetchReceiptDetail(receipt.value.id);
            }
        },
    });
}

// Format status badge
function getStatusBadge(status) {
    switch (status) {
        case 'DRAFT':
            return { variant: 'neutral', label: 'Draft' };
        case 'MENUNGGU_PPTK':
            return { variant: 'info', label: 'Menunggu PPTK' };
        case 'MENUNGGU_LURAH':
            return { variant: 'warning', label: 'Menunggu Lurah' };
        case 'MENUNGGU_VERIFIKASI':
            return { variant: 'primary', label: 'Menunggu Verifikasi' };
        case 'REVISI_MINOR':
            return { variant: 'warning', label: 'Revisi Minor (SLA)' };
        case 'REVISI_MAYOR':
            return { variant: 'danger', label: 'Revisi Mayor' };
        case 'TERVERIFIKASI':
        case 'LOLOS_VERIFIKASI':
            return { variant: 'success', label: 'Lolos Verifikasi' };
        case 'DIBUNDEL':
            return { variant: 'info', label: 'Dibundel (SPP)' };
        case 'SELESAI_SP2D':
        case 'SYNCED_SIPD':
            return { variant: 'success', label: 'Cair (SIPD-RI)' };
        default:
            return { variant: 'neutral', label: status || '-' };
    }
}
</script>

<template>
    <div>
        <!-- Backdrop -->
        <transition
            enter-active-class="transition-opacity ease-linear duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                @click="closeDrawer"
                class="fixed inset-0 bg-ink/40 z-50"
            ></div>
        </transition>

        <!-- Slide-Over Drawer Panel -->
        <transition
            enter-active-class="transform transition ease-in-out duration-300 sm:duration-400"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition ease-in-out duration-300 sm:duration-400"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="show"
                class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl bg-surface shadow-floating flex flex-col border-l border-hairline"
                aria-label="Panel Rincian Kuitansi"
            >
                <!-- Drawer Header -->
                <div class="px-6 py-4 bg-surface text-ink flex items-center justify-between border-b border-hairline">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="font-mono font-semibold text-base tracking-tight text-ink">
                                {{ receipt?.nomor_kuitansi || 'Memuat Data...' }}
                            </h2>
                            <GovBadge v-if="receipt" :variant="getStatusMeta(receipt.status).variant" size="sm">
                                {{ getStatusMeta(receipt.status).label }}
                            </GovBadge>
                        </div>
                        <p class="text-xs text-ink-muted mt-0.5">
                            Lembar Rincian Pre-Audit Akuntabilitas Belanja
                        </p>
                    </div>

                    <button
                        @click="closeDrawer"
                        class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 sm:w-8 sm:h-8 inline-flex items-center justify-center rounded-lg text-ink-muted hover:text-ink hover:bg-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-accent transition-colors duration-150 ease-unslop"
                        title="Tutup Panel (ESC)"
                    >
                        <X class="w-4 h-4" :stroke-width="1.5" />
                    </button>
                </div>

                <!-- Drawer Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto scrollbar-thin p-6 space-y-6">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-3 text-ink-muted">
                        <Loader2 class="w-6 h-6 animate-spin text-accent" :stroke-width="1.5" />
                        <p class="text-sm font-medium">Memuat detail kuitansi...</p>
                    </div>

                    <!-- Error State -->
                    <div v-else-if="errorMessage" class="p-4 rounded-xl bg-red-50/70 border border-red-200 text-semantic-danger text-sm">
                        <div class="font-medium flex items-center gap-2">
                            <AlertCircle class="w-4 h-4 shrink-0" :stroke-width="1.5" />
                            Gagal Memuat Kuitansi
                        </div>
                        <p class="mt-1 text-xs">{{ errorMessage }}</p>
                    </div>

                    <!-- Content State -->
                    <div v-else-if="receipt" class="space-y-6">
                        <!-- Alert SLA jika REVISI_MINOR -->
                        <div v-if="receipt.status === 'REVISI_MINOR'" class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div class="text-xs space-y-1">
                                    <div class="font-bold text-sm text-amber-800">
                                        Perlu Tindak Lanjut Revisi Minor (SLA 2 Hari Kerja)
                                    </div>
                                    <p>Batas Waktu SLA: <span class="font-semibold">{{ formatTanggalIndo(receipt.tenggat_sla, true) }}</span></p>
                                    <p v-if="receipt.catatan_verifikasi" class="text-ink-secondary italic bg-surface/80 p-2 rounded border border-amber-200">
                                        "{{ receipt.catatan_verifikasi }}"
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 1. Ringkasan Pos Anggaran & Identitas Belanja -->
                        <div class="bg-raised p-4 rounded-xl border border-hairline space-y-3">
                            <h3 class="text-xs font-bold text-ink-muted ">
                                Pos Anggaran & Dokumen Belanja
                            </h3>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-ink-muted block">Unit Kerja:</span>
                                    <span class="font-semibold text-ink">{{ receipt.unit_kerja?.nama_unit || receipt.rekening?.unit_kerja?.nama_unit || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-muted block">Bulan Kas RAK:</span>
                                    <span class="font-semibold text-ink">Bulan {{ receipt.bulan_anggaran }} (2026)</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-ink-muted block">Kode & Nama Rekening:</span>
                                    <span class="font-mono font-semibold text-ink">{{ receipt.rekening?.kode_rekening }}</span>
                                    <span class="text-ink-secondary block mt-0.5">{{ receipt.rekening?.nama_rekening }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-ink-muted block">Uraian Transaksi:</span>
                                    <span class="font-medium text-ink">{{ receipt.uraian }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-muted block">Rekanan / Penerima:</span>
                                    <span class="font-semibold text-ink">{{ receipt.nama_penerima || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-ink-muted block">NPWP Rekanan:</span>
                                    <span class="font-mono text-ink">{{ receipt.npwp_penerima || 'Tanpa NPWP (Penalti 100%)' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Rincian Item Belanja -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-ink-muted ">
                                Rincian Barang / Jasa
                            </h3>
                            <div class="border border-hairline rounded-xl overflow-hidden">
                                <table class="min-w-full divide-y divide-hairline text-xs text-left">
                                    <thead class="bg-raised font-semibold text-ink-secondary">
                                        <tr>
                                            <th class="py-2.5 px-3">Nama Barang / Jasa</th>
                                            <th class="py-2.5 px-3 text-center">Volume</th>
                                            <th class="py-2.5 px-3 text-right">Harga Satuan (Rp)</th>
                                            <th class="py-2.5 px-3 text-right">Subtotal (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline bg-surface">
                                        <tr v-for="(item, idx) in receipt.items" :key="idx" class="hover:bg-raised/60">
                                            <td class="py-2 px-3 font-medium text-ink">{{ item.nama_barang }}</td>
                                            <td class="py-2 px-3 text-center text-ink-secondary">{{ item.volume }} {{ item.satuan || 'Pcs' }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums text-ink-secondary">{{ formatRupiah(item.harga_satuan) }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums font-semibold text-ink">
                                                {{ formatRupiah(Number(item.volume) * Number(item.harga_satuan)) }}
                                            </td>
                                        </tr>
                                        <tr v-if="!receipt.items || receipt.items.length === 0">
                                            <td colspan="4" class="py-4 text-center text-ink-muted">Tidak ada item rincian.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 3. Rekonsiliasi Keuangan & Perhitungan Pajak -->
                        <div class="bg-raised p-4 rounded-xl border border-hairline space-y-3">
                            <h3 class="text-xs font-bold text-ink ">
                                Rekonsiliasi Keuangan & Pemotongan Pajak
                            </h3>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between py-1 border-b border-hairline">
                                    <span class="text-ink-secondary">Nominal Bruto Belanja:</span>
                                    <span class="font-bold text-ink tabular-nums">{{ formatRupiah(receipt.nominal_bruto) }}</span>
                                </div>

                                <!-- Rincian Komponen Pajak Rekanan -->
                                <div v-for="pjk in taxDeductions" :key="pjk.id" class="flex justify-between text-ink-secondary pl-3">
                                    <span>• {{ pjk.nama_pajak }} (DPP: {{ formatRupiah(pjk.dpp) }}):</span>
                                    <span class="font-medium text-rose-600 tabular-nums">- {{ formatRupiah(pjk.nominal) }}</span>
                                </div>

                                <div v-if="taxDeductions.length === 0" class="text-ink-muted italic text-xs pl-3">
                                    Bebas pemotongan pajak rekanan (PMK 59/2022).
                                </div>

                                <div class="flex justify-between py-1 border-b border-hairline">
                                    <span class="text-ink-secondary font-medium">Total Potongan Pajak Rekanan:</span>
                                    <span class="font-bold text-rose-600 tabular-nums">- {{ formatRupiah(receipt.total_pajak) }}</span>
                                </div>

                                <!-- Badge Informasi Bea Meterai (Dokumen Fiskal) -->
                                <div v-if="meteraiTax || (Number(receipt.nominal_bruto) > 5000000)" class="mt-2 p-2.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-1.5 font-medium">
                                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Bea Meterai Lunas (Dokumen Fisik)</span>
                                    </div>
                                    <span class="font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded text-xs">
                                        Rp 10.000 (Tidak Memotong Hak Rekanan)
                                    </span>
                                </div>

                                <div class="flex justify-between py-2 text-sm bg-accent text-white px-3 rounded-lg font-bold">
                                    <span>Nominal Pembayaran Neto:</span>
                                    <span class="tabular-nums text-accent-soft">{{ formatRupiah(receipt.nominal_neto) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Berkas Bukti & Lampiran SHA-256 -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-ink-muted ">
                                Berkas Bukti & Hash SHA-256 (Anti-Duplikasi)
                            </h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="lampiran in receipt.lampiran"
                                    :key="lampiran.id"
                                    class="p-3 bg-surface rounded-lg border border-hairline text-xs flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2 max-w-sm truncate">
                                        <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <div class="truncate">
                                            <div class="font-medium text-ink truncate">{{ lampiran.nama_dokumen || lampiran.file_name }}</div>
                                            <div class="text-xs text-ink-muted font-mono truncate">SHA: {{ lampiran.file_hash }}</div>
                                        </div>
                                    </div>

                                    <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        Tervalidasi
                                    </span>
                                </div>

                                <div v-if="!receipt.lampiran || receipt.lampiran.length === 0" class="p-3 bg-raised rounded-lg text-center text-ink-muted text-xs">
                                    Belum ada lampiran berkas yang diunggah.
                                </div>
                            </div>
                        </div>

                        <!-- 4b. Pelaksana & Foto Keberangkatan SPPD -->
                        <div v-if="(receipt.sppd_peserta && receipt.sppd_peserta.length > 0) || receipt.sppd_foto_url" class="space-y-2">
                            <h3 class="text-xs font-bold text-ink-muted">
                                Pelaksana Perjalanan Dinas (SPPD) &amp; Foto Keberangkatan
                            </h3>
                            <div class="bg-surface rounded-lg border border-hairline divide-y divide-hairline text-xs">
                                <div
                                    v-for="peserta in receipt.sppd_peserta || []"
                                    :key="peserta.id"
                                    class="p-3 flex items-center justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink truncate">
                                            {{ peserta.pegawai?.nama || `Pegawai #${peserta.pegawai_id}` }}
                                        </div>
                                        <div class="text-xs text-ink-muted font-mono tabular-nums">
                                            NIP {{ peserta.pegawai?.nip || '-' }} •
                                            {{ (peserta.tanggal_berangkat || '').slice(0, 10) }} s/d {{ (peserta.tanggal_pulang || '').slice(0, 10) }}
                                        </div>
                                        <div v-if="peserta.tujuan" class="text-xs text-ink-secondary mt-0.5 truncate">
                                            Tujuan: {{ peserta.tujuan }}
                                        </div>
                                    </div>
                                    <a
                                        v-if="peserta.foto_url || receipt.sppd_foto_url"
                                        :href="peserta.foto_url || receipt.sppd_foto_url"
                                        target="_blank"
                                        class="shrink-0 group"
                                        title="Buka Foto Keberangkatan"
                                    >
                                        <img
                                            :src="peserta.foto_url || receipt.sppd_foto_url"
                                            alt="Foto Keberangkatan"
                                            class="w-11 h-11 rounded-md object-cover border border-hairline group-hover:ring-2 group-hover:ring-accent"
                                        />
                                    </a>
                                </div>
                                <div v-if="receipt.sppd_foto_url && (!receipt.sppd_peserta || receipt.sppd_peserta.length === 0)" class="p-3 flex items-center justify-between">
                                    <span class="text-ink-secondary">Foto Keberangkatan Rombongan</span>
                                    <a :href="receipt.sppd_foto_url" target="_blank" class="text-accent font-medium underline">Lihat Foto</a>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Jejak Audit BPK (Timeline) -->
                        <div class="space-y-3 pt-2">
                            <h3 class="text-xs font-bold text-ink-muted ">
                                Jejak Audit BPK & Riwayat Transisi Status
                            </h3>
                            <div class="bg-surface p-4 rounded-xl border border-hairline">
                                <AuditTimeline :events="receipt.riwayat || []" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer: Aksi Kontekstual Berdasarkan Role -->
                <div v-if="receipt" class="p-4 bg-raised border-t border-hairline space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <GovButton @click="closeDrawer" variant="secondary" size="sm">
                            Tutup
                        </GovButton>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <!-- Aksi Penginput (BPP / BP / Kasubag): Edit / Revisi untuk DRAFT, REVISI_MINOR, REVISI_MAYOR -->
                            <Link
                                v-if="canMutateReceipt && ['DRAFT', 'REVISI_MINOR', 'REVISI_MAYOR'].includes(receipt.status)"
                                :href="`/receipts/${receipt.id}/edit`"
                            >
                                <GovButton variant="secondary" size="sm">
                                    Perbaiki / Revisi Kuitansi
                                </GovButton>
                            </Link>

                            <!-- Aksi Penginput (BPP / BP / Kasubag): Submit jika DRAFT -->
                            <GovButton
                                v-if="canMutateReceipt && receipt.status === 'DRAFT'"
                                @click="openActionModal('SUBMIT_PPTK')"
                                variant="primary"
                                size="sm"
                                :disabled="isActionLoading"
                                class="shadow-subtle"
                            >
                                {{ isActionLoading ? 'Memproses...' : (currentUser?.can_auto_finalize_receipt ? 'Ajukan & Finalisasi' : 'Ajukan Verifikasi') }}
                            </GovButton>

                            <!-- Aksi BPP: Self-Recall ke DRAFT jika milik unit sendiri & belum masuk siklus SLA Revisi Minor -->
                            <GovButton
                                v-if="
                                    userRole === 'BPP'
                                    && canMutateReceipt
                                    && (['MENUNGGU_PPTK', 'MENUNGGU_LURAH'].includes(receipt.status) || (receipt.status === 'MENUNGGU_VERIFIKASI' && !receipt.tenggat_sla))
                                "
                                @click="openActionModal('RECALL_DRAFT')"
                                variant="danger"
                                size="sm"
                                :disabled="isActionLoading"
                            >
                                {{ isActionLoading ? 'Memproses...' : 'Tarik ke Draft' }}
                            </GovButton>

                            <!-- Aksi PPTK: Quick Approve & Reject jika MENUNGGU_PPTK -->
                            <template v-if="userRole === 'PPTK' && receipt.status === 'MENUNGGU_PPTK'">
                                <GovButton
                                    @click="openRejectModal('PPTK')"
                                    variant="danger"
                                    size="sm"
                                    :disabled="isActionLoading"
                                >
                                    Tolak / Revisi
                                </GovButton>

                                <GovButton
                                    @click="openActionModal('PPTK_APPROVE')"
                                    variant="primary"
                                    size="sm"
                                    :disabled="isActionLoading"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                >
                                    {{ isActionLoading ? 'Memproses...' : 'Setujui PPTK' }}
                                </GovButton>
                            </template>

                        <!-- Aksi Verifikator (BP Kecamatan / Kasubag Keuangan): Tautan ke Workspace Verifikasi -->
                        <Link
                            v-if="canVerifyReceipt && ['MENUNGGU_VERIFIKASI', 'REVISI_MINOR'].includes(receipt.status)"
                            :href="route('verification.workspace', receipt.id)"
                        >
                            <GovButton variant="primary" size="sm">
                                Buka Workspace Verifikasi
                            </GovButton>
                        </Link>

                        <!-- Unduh PDF Lembar Kendali jika Lolos Verifikasi, Dibundel, atau Selesai SP2D -->
                        <a
                            v-if="['LOLOS_VERIFIKASI', 'TERVERIFIKASI', 'DIBUNDEL', 'SELESAI_SP2D', 'SYNCED_SIPD'].includes(receipt.status)"
                            :href="route('control-sheet.download-pdf', receipt.id)"
                            target="_blank"
                        >
                            <GovButton variant="secondary" size="sm" class="border-emerald-600/30 text-emerald-800 hover:bg-emerald-50">
                                Unduh Lembar Kendali (PDF)
                            </GovButton>
                        </a>
                        </div>
                    </div>
                </div>
            </aside>
        </transition>
    </div>
</template>

