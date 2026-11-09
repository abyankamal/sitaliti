<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import AuditTimeline from '@/Components/UI/AuditTimeline.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

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
const userRole = computed(() => currentUser.value?.role || 'OPERATOR');

const receipt = ref(null);
const isLoading = ref(false);
const isActionLoading = ref(false);
const errorMessage = ref('');

// State Modal Penolakan PPTK
const isRejectModalOpen = ref(false);
const rejectReason = ref('');
const rejectError = ref('');

// Load detail kuitansi saat receiptId berubah atau drawer dibuka
watch(
    () => [props.show, props.receiptId],
    async ([newShow, newId]) => {
        if (newShow && newId) {
            await fetchReceiptDetail(newId);
        } else if (!newShow) {
            receipt.value = null;
            errorMessage.value = '';
            isRejectModalOpen.value = false;
            rejectReason.value = '';
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
        if (isRejectModalOpen.value) {
            isRejectModalOpen.value = false;
        } else {
            closeDrawer();
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

// Aksi Operator: Submit ke PPTK
async function handleOperatorSubmit() {
    if (!receipt.value || isActionLoading.value) return;
    if (!confirm(`Ajukan kuitansi ${receipt.value.nomor_kuitansi} ke PPTK untuk diverifikasi?`)) return;

    isActionLoading.value = true;
    try {
        const response = await axios.post(`/api/v1/receipts/${receipt.value.id}/submit`);
        if (response.data?.success) {
            alert('Kuitansi berhasil diajukan ke PPTK. Pagu anggaran kas telah dikunci.');
            emit('refresh');
            await fetchReceiptDetail(receipt.value.id);
        }
    } catch (err) {
        alert(err.response?.data?.error?.message || err.response?.data?.message || 'Gagal mengajukan kuitansi.');
    } finally {
        isActionLoading.value = false;
    }
}

// Aksi PPTK: Setujui Kuitansi
async function handlePptkApprove() {
    if (!receipt.value || isActionLoading.value) return;
    const catatan = prompt('Masukkan catatan persetujuan teknis (opsional):', 'Sesuai dengan spesifikasi teknis dan ketersediaan anggaran.');
    if (catatan === null) return; // User cancel

    isActionLoading.value = true;
    try {
        const response = await axios.post(`/api/v1/receipts/${receipt.value.id}/pptk-approve`, {
            catatan: catatan || 'Disetujui oleh PPTK',
        });
        if (response.data?.success) {
            alert('Kuitansi disetujui PPTK dan berhasil diteruskan ke Tim Verifikator Kecamatan.');
            emit('refresh');
            await fetchReceiptDetail(receipt.value.id);
        }
    } catch (err) {
        alert(err.response?.data?.error?.message || err.response?.data?.message || 'Gagal menyetujui kuitansi.');
    } finally {
        isActionLoading.value = false;
    }
}

// Aksi PPTK: Tolak Kuitansi
function openRejectModal() {
    rejectReason.value = '';
    rejectError.value = '';
    isRejectModalOpen.value = true;
}

async function handlePptkRejectConfirm() {
    if (rejectReason.value.trim().length < 5) {
        rejectError.value = 'Alasan penolakan wajib diisi minimal 5 karakter demi akuntabilitas jejak audit.';
        return;
    }

    isActionLoading.value = true;
    rejectError.value = '';
    try {
        const response = await axios.post(`/api/v1/receipts/${receipt.value.id}/pptk-reject`, {
            alasan: rejectReason.value.trim(),
        });
        if (response.data?.success) {
            alert('Kuitansi berhasil dikembalikan ke DRAFT dan pagu anggaran kas telah dipulihkan.');
            isRejectModalOpen.value = false;
            emit('refresh');
            await fetchReceiptDetail(receipt.value.id);
        }
    } catch (err) {
        rejectError.value = err.response?.data?.error?.message || err.response?.data?.message || 'Gagal memproses penolakan kuitansi.';
    } finally {
        isActionLoading.value = false;
    }
}

// Format status badge
function getStatusBadge(status) {
    switch (status) {
        case 'DRAFT':
            return { variant: 'neutral', label: 'Draft' };
        case 'MENUNGGU_PPTK':
            return { variant: 'info', label: 'Menunggu PPTK' };
        case 'MENUNGGU_VERIFIKASI':
            return { variant: 'primary', label: 'Menunggu Verifikasi' };
        case 'REVISI_MINOR':
            return { variant: 'warning', label: 'Revisi Minor (SLA)' };
        case 'REVISI_MAYOR':
            return { variant: 'danger', label: 'Revisi Mayor' };
        case 'TERVERIFIKASI':
            return { variant: 'success', label: 'Lolos Verifikasi' };
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
                class="fixed inset-0 bg-slate-900/60 z-50 backdrop-blur-xs"
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
                class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl bg-white shadow-2xl flex flex-col border-l border-slate-200"
                aria-label="Panel Rincian Kuitansi"
            >
                <!-- Drawer Header -->
                <div class="px-6 py-4 bg-gov-navy-950 text-white flex items-center justify-between border-b border-gov-navy-800">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gov-navy-800 border border-gov-gold-500/30 flex items-center justify-center font-bold text-gov-gold-400 text-xs">
                            KW
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-bold text-base tracking-tight text-white">
                                    {{ receipt?.nomor_kuitansi || 'Memuat Data...' }}
                                </h2>
                                <GovBadge v-if="receipt" :variant="getStatusBadge(receipt.status).variant" size="sm">
                                    {{ getStatusBadge(receipt.status).label }}
                                </GovBadge>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Lembar Rincian Pre-Audit Akuntabilitas Belanja
                            </p>
                        </div>
                    </div>

                    <button
                        @click="closeDrawer"
                        class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-gov-navy-800 focus:outline-none transition-colors"
                        title="Tutup Panel (ESC)"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Drawer Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-3 text-slate-400">
                        <svg class="w-8 h-8 animate-spin text-gov-navy-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <p class="text-sm font-medium">Memuat detail kuitansi...</p>
                    </div>

                    <!-- Error State -->
                    <div v-else-if="errorMessage" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                        <div class="font-bold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Gagal Memuat Kuitansi
                        </div>
                        <p class="mt-1">{{ errorMessage }}</p>
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
                                    <p v-if="receipt.catatan_verifikasi" class="text-slate-700 italic bg-white/70 p-2 rounded border border-amber-200">
                                        "{{ receipt.catatan_verifikasi }}"
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 1. Ringkasan Pos Anggaran & Identitas Belanja -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Pos Anggaran & Dokumen Belanja
                            </h3>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">Unit Kerja:</span>
                                    <span class="font-semibold text-slate-900">{{ receipt.rekening?.unit_kerja?.nama_unit || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Bulan Kas RAK:</span>
                                    <span class="font-semibold text-slate-900">Bulan {{ receipt.bulan_anggaran }} (2026)</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-slate-500 block">Kode & Nama Rekening:</span>
                                    <span class="font-mono font-semibold text-slate-900">{{ receipt.rekening?.kode_rekening }}</span>
                                    <span class="text-slate-700 block mt-0.5">{{ receipt.rekening?.nama_rekening }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-slate-500 block">Uraian Transaksi:</span>
                                    <span class="font-medium text-slate-900">{{ receipt.uraian }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Rekanan / Penerima:</span>
                                    <span class="font-semibold text-slate-900">{{ receipt.nama_penerima || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">NPWP Rekanan:</span>
                                    <span class="font-mono text-slate-900">{{ receipt.npwp_penerima || 'Tanpa NPWP (Penalti 100%)' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Rincian Item Belanja -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Rincian Barang / Jasa
                            </h3>
                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <table class="min-w-full divide-y divide-slate-200 text-xs text-left">
                                    <thead class="bg-slate-100 font-semibold text-slate-700">
                                        <tr>
                                            <th class="py-2.5 px-3">Nama Barang / Jasa</th>
                                            <th class="py-2.5 px-3 text-center">Volume</th>
                                            <th class="py-2.5 px-3 text-right">Harga Satuan (Rp)</th>
                                            <th class="py-2.5 px-3 text-right">Subtotal (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="(item, idx) in receipt.items" :key="idx" class="hover:bg-slate-50/50">
                                            <td class="py-2 px-3 font-medium text-slate-900">{{ item.nama_barang }}</td>
                                            <td class="py-2 px-3 text-center text-slate-600">{{ item.volume }} {{ item.satuan || 'Pcs' }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums text-slate-700">{{ formatRupiah(item.harga_satuan) }}</td>
                                            <td class="py-2 px-3 text-right tabular-nums font-semibold text-slate-900">
                                                {{ formatRupiah(Number(item.volume) * Number(item.harga_satuan)) }}
                                            </td>
                                        </tr>
                                        <tr v-if="!receipt.items || receipt.items.length === 0">
                                            <td colspan="4" class="py-4 text-center text-slate-400">Tidak ada item rincian.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 3. Rekonsiliasi Keuangan & Perhitungan Pajak -->
                        <div class="bg-gov-navy-950/5 p-4 rounded-xl border border-gov-navy-900/10 space-y-3">
                            <h3 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider">
                                Rekonsiliasi Keuangan & Pemotongan Pajak
                            </h3>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between py-1 border-b border-slate-200">
                                    <span class="text-slate-600">Nominal Bruto Belanja:</span>
                                    <span class="font-bold text-slate-900 tabular-nums">{{ formatRupiah(receipt.nominal_bruto) }}</span>
                                </div>

                                <!-- Rincian Komponen Pajak -->
                                <div v-for="pjk in receipt.pajak" :key="pjk.id" class="flex justify-between text-slate-600 pl-3">
                                    <span>• {{ pjk.nama_pajak }} (DPP: {{ formatRupiah(pjk.dpp) }}):</span>
                                    <span class="font-medium text-rose-600 tabular-nums">- {{ formatRupiah(pjk.nominal) }}</span>
                                </div>

                                <div v-if="!receipt.pajak || receipt.pajak.length === 0" class="text-slate-400 italic text-[11px] pl-3">
                                    Bebas pemotongan pajak (PMK 59/2022).
                                </div>

                                <div class="flex justify-between py-1 border-b border-slate-200">
                                    <span class="text-slate-600 font-medium">Total Potongan Pajak:</span>
                                    <span class="font-bold text-rose-600 tabular-nums">- {{ formatRupiah(receipt.total_pajak) }}</span>
                                </div>

                                <div class="flex justify-between py-2 text-sm bg-gov-navy-900 text-white px-3 rounded-lg font-bold">
                                    <span>Nominal Pembayaran Neto:</span>
                                    <span class="tabular-nums text-gov-gold-400">{{ formatRupiah(receipt.nominal_neto) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Berkas Bukti & Lampiran SHA-256 -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Berkas Bukti & Hash SHA-256 (Anti-Duplikasi)
                            </h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="lampiran in receipt.lampiran"
                                    :key="lampiran.id"
                                    class="p-3 bg-white rounded-lg border border-slate-200 text-xs flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2 max-w-sm truncate">
                                        <svg class="w-4 h-4 text-gov-navy-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <div class="truncate">
                                            <div class="font-medium text-slate-800 truncate">{{ lampiran.nama_dokumen || lampiran.file_name }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono truncate">SHA: {{ lampiran.file_hash }}</div>
                                        </div>
                                    </div>

                                    <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        Tervalidasi
                                    </span>
                                </div>

                                <div v-if="!receipt.lampiran || receipt.lampiran.length === 0" class="p-3 bg-slate-50 rounded-lg text-center text-slate-400 text-xs">
                                    Belum ada lampiran berkas yang diunggah.
                                </div>
                            </div>
                        </div>

                        <!-- 5. Jejak Audit BPK (Timeline) -->
                        <div class="space-y-3 pt-2">
                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Jejak Audit BPK & Riwayat Transisi Status
                            </h3>
                            <div class="bg-white p-4 rounded-xl border border-slate-200">
                                <AuditTimeline :events="receipt.riwayat || []" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer: Aksi Kontekstual Berdasarkan Role -->
                <div v-if="receipt" class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3">
                    <GovButton @click="closeDrawer" variant="secondary" size="sm">
                        Tutup
                    </GovButton>

                    <div class="flex items-center gap-2">
                        <!-- Aksi Operator: Submit jika DRAFT -->
                        <GovButton
                            v-if="(userRole === 'OPERATOR' || userRole === 'KPA') && receipt.status === 'DRAFT'"
                            @click="handleOperatorSubmit"
                            variant="primary"
                            size="sm"
                            :disabled="isActionLoading"
                            class="shadow-sm"
                        >
                            {{ isActionLoading ? 'Memproses...' : 'Ajukan ke PPTK' }}
                        </GovButton>

                        <!-- Aksi PPTK: Quick Approve & Reject jika MENUNGGU_PPTK -->
                        <template v-if="(userRole === 'PPTK' || userRole === 'KPA') && receipt.status === 'MENUNGGU_PPTK'">
                            <GovButton
                                @click="openRejectModal"
                                variant="danger"
                                size="sm"
                                :disabled="isActionLoading"
                            >
                                Tolak / Revisi
                            </GovButton>

                            <GovButton
                                @click="handlePptkApprove"
                                variant="primary"
                                size="sm"
                                :disabled="isActionLoading"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white"
                            >
                                {{ isActionLoading ? 'Memproses...' : 'Setujui PPTK' }}
                            </GovButton>
                        </template>

                        <!-- Aksi Verifikator: Tautan ke Workspace Verifikasi -->
                        <Link
                            v-if="(userRole === 'VERIFIKATOR' || userRole === 'KPA') && ['MENUNGGU_VERIFIKASI', 'REVISI_MINOR'].includes(receipt.status)"
                            :href="route('verification.workspace', receipt.id)"
                        >
                            <GovButton variant="primary" size="sm">
                                Buka Workspace Verifikasi
                            </GovButton>
                        </Link>

                        <!-- Unduh PDF Lembar Kendali jika Lolos Verifikasi -->
                        <a
                            v-if="['TERVERIFIKASI', 'SYNCED_SIPD'].includes(receipt.status)"
                            :href="route('control-sheet.download-pdf', receipt.id)"
                            target="_blank"
                        >
                            <GovButton variant="secondary" size="sm" class="border-emerald-600/30 text-emerald-800 hover:bg-emerald-50">
                                Unduh Lembar Kendali (PDF)
                            </GovButton>
                        </a>
                    </div>
                </div>
            </aside>
        </transition>

        <!-- Modal Dialog Penolakan PPTK -->
        <div v-if="isRejectModalOpen" class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4 border border-slate-200">
                <div class="flex items-center gap-3 text-rose-600">
                    <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base">Tolak Kuitansi ke DRAFT</h3>
                        <p class="text-xs text-slate-500">Pagu anggaran kas yang terkunci akan otomatis dipulihkan.</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700">Alasan Penolakan / Catatan Perbaikan:</label>
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        placeholder="Tuliskan alasan penolakan secara spesifik (min. 5 karakter)..."
                        class="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"
                    ></textarea>
                    <p v-if="rejectError" class="text-[11px] text-rose-600 font-medium">{{ rejectError }}</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <GovButton @click="isRejectModalOpen = false" variant="secondary" size="sm" :disabled="isActionLoading">
                        Batal
                    </GovButton>
                    <GovButton @click="handlePptkRejectConfirm" variant="danger" size="sm" :disabled="isActionLoading">
                        {{ isActionLoading ? 'Memproses...' : 'Konfirmasi Penolakan' }}
                    </GovButton>
                </div>
            </div>
        </div>
    </div>
</template>

