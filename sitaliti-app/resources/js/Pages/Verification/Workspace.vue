<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GovButton from '@/Components/UI/GovButton.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import PdfViewer from '@/Modules/Verification/PdfViewer.vue';
import RevisionDrawer from '@/Modules/Verification/RevisionDrawer.vue';
import AuditTimeline from '@/Components/UI/AuditTimeline.vue';
import { verificationService } from '@/Services/verificationService';
import { receiptService } from '@/Services/receiptService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';

const props = defineProps({
    id: {
        type: [Number, String],
        default: 'KW-2026-06-0042',
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const userRole = computed(() => currentUser.value.role || '');
const isVerifikator = computed(() => userRole.value === 'VERIFIKATOR' || userRole.value === 'KPA');
const isLurah = computed(() => userRole.value === 'LURAH' || userRole.value === 'KPA');
const isPptk = computed(() => userRole.value === 'PPTK' || userRole.value === 'KPA');

const detail = ref(null);
const loading = ref(true);
const activeAttachmentIndex = ref(0);
const activeTab = ref('checklist'); // 'checklist' | 'timeline'
const auditHistory = ref([]);
const loadingHistory = ref(false);

// Notification State
const notification = ref({ show: false, type: 'success', message: '' });

function showNotification(message, type = 'success') {
    notification.value = { show: true, type, message };
    setTimeout(() => {
        notification.value.show = false;
    }, 6000);
}

// Modal Revision & Otorisasi State
const showRevisionModal = ref(false);
const revisionActionType = ref('REVISION_MINOR');
const showQuickAuthModal = ref(false);
const quickAuthType = ref('APPROVE'); // 'APPROVE' | 'REJECT'
const quickAuthNotes = ref('');
const isSubmittingQuickAuth = ref(false);

onMounted(async () => {
    try {
        const res = await verificationService.getDetail(props.id);
        if (res) {
            if (Array.isArray(res.checklist)) {
                res.checklist = res.checklist.map((item) => ({
                    ...item,
                    ui_status:
                        item.is_verified || item.status_kesesuaian === 'SESUAI' || item.status_kesesuaian === 'VALID'
                            ? 'VALID'
                            : item.status_kesesuaian === 'TIDAK_SESUAI'
                              ? 'REVISION_NEEDED'
                              : 'PENDING',
                }));
            } else {
                res.checklist = [];
            }
            detail.value = res;
        }
        fetchHistory();
    } finally {
        loading.value = false;
    }
});

async function fetchHistory() {
    if (!detail.value) return;
    loadingHistory.value = true;
    try {
        auditHistory.value = await receiptService.getHistory(detail.value.id || props.id);
    } catch (error) {
        console.warn('Could not load audit history:', error);
        auditHistory.value = [];
    } finally {
        loadingHistory.value = false;
    }
}

const lampiranList = computed(() => {
    if (!detail.value) return [];
    const raw = Array.isArray(detail.value.lampiran)
        ? detail.value.lampiran
        : Array.isArray(detail.value.attachments)
          ? detail.value.attachments
          : [];

    return raw.map((lamp) => ({
        id: lamp.id,
        checklist_code: lamp.checklist_code || null,
        nama_dokumen: lamp.nama_dokumen || lamp.doc_name || lamp.nama_file_asli || lamp.file_name,
        file_name: lamp.nama_file_asli || lamp.file_name || lamp.nama_dokumen || 'Lampiran',
        file_url: lamp.file_url || `/receipts/${detail.value.id}/attachments/${lamp.id}/preview`,
    }));
});

const activeAttachment = computed(() => {
    return lampiranList.value[activeAttachmentIndex.value] || null;
});

function selectAttachmentForChecklist(item) {
    if (!lampiranList.value.length) return;
    const matchIdx = lampiranList.value.findIndex(
        (att) => att.checklist_code && item.kode_item && att.checklist_code === item.kode_item
    );
    if (matchIdx >= 0) {
        activeAttachmentIndex.value = matchIdx;
    }
}

// Cek kelayakan lolos verifikasi
const allMandatoryVerified = computed(() => {
    if (!detail.value) return false;
    const checklist = Array.isArray(detail.value.checklist) ? detail.value.checklist : [];
    if (checklist.length === 0) return false;
    if (lampiranList.value.length === 0) return false;

    return checklist
        .filter((item) => Boolean(item.is_required))
        .every((item) => item.ui_status === 'VALID');
});

function setChecklistStatus(index, newStatus) {
    if (!isVerifikator.value) return;
    if (detail.value?.checklist?.[index]) {
        detail.value.checklist[index].ui_status = newStatus;
    }
}

function openRevisionModal(type) {
    revisionActionType.value = type;
    showRevisionModal.value = true;
}

async function handleActionSubmit(payload) {
    showRevisionModal.value = false;
    const res = await verificationService.submitAction(detail.value.id, payload);
    showNotification(res.message || 'Aksi verifikasi berhasil diproses.');
    setTimeout(() => {
        router.visit(route('verification.index'));
    }, 1000);
}

async function handleApprove() {
    if (!allMandatoryVerified.value) {
        showNotification(
            'Seluruh butir checklist wajib harus berstatus Lolos dan minimal 1 lampiran harus tersedia.',
            'error'
        );
        return;
    }

    const checklistResults = detail.value.checklist.map((item) => ({
        item_id: item.item_id,
        status: item.ui_status === 'REVISION_NEEDED' ? 'TIDAK_SESUAI' : 'SESUAI',
        notes: item.catatan || 'Sesuai dengan ketentuan regulasi dan bukti fisik.',
    }));

    try {
        const res = await verificationService.submitAction(detail.value.id, {
            action: 'VERIFIED',
            notes: 'Semua kelengkapan dokumen telah diteliti dan valid.',
            checklist_results: checklistResults,
        });
        showNotification(res.message || 'Kuitansi dinyatakan Lolos Verifikasi Akuntabel.');
        setTimeout(() => {
            router.visit(route('control-sheet.show', { id: detail.value.id }));
        }, 1200);
    } catch (err) {
        showNotification(err.response?.data?.error?.message || err.message || 'Gagal memproses verifikasi.', 'error');
    }
}

// Quick Auth (Lurah / PPTK) dari Workspace
function openQuickAuth(type) {
    quickAuthType.value = type;
    quickAuthNotes.value = '';
    showQuickAuthModal.value = true;
}

async function handleQuickAuthSubmit() {
    if (!detail.value) return;
    if (quickAuthType.value === 'REJECT' && (!quickAuthNotes.value || quickAuthNotes.value.trim().length < 3)) {
        showNotification('Mohon cantumkan alasan penolakan secara jelas (minimal 3 karakter).', 'error');
        return;
    }

    isSubmittingQuickAuth.value = true;
    const targetId = detail.value.id;

    try {
        if (detail.value.status === 'MENUNGGU_LURAH' && isLurah.value) {
            if (quickAuthType.value === 'APPROVE') {
                await receiptService.approveByLurah(targetId, quickAuthNotes.value || 'Disetujui dan diotorisasi oleh Lurah.');
                showNotification('Kuitansi berhasil diotorisasi Lurah dan diteruskan ke Tim Verifikator Kecamatan.');
            } else {
                await receiptService.rejectByLurah(targetId, quickAuthNotes.value);
                showNotification('Kuitansi dikembalikan oleh Lurah ke draf Operator.');
            }
        } else if (detail.value.status === 'MENUNGGU_PPTK' && isPptk.value) {
            if (quickAuthType.value === 'APPROVE') {
                await receiptService.approveByPptk(targetId, quickAuthNotes.value);
                showNotification('Kuitansi disetujui PPTK dan diteruskan ke tahapan selanjutnya.');
            } else {
                await receiptService.rejectByPptk(targetId, quickAuthNotes.value);
                showNotification('Kuitansi ditolak oleh PPTK dan dikembalikan ke draf Operator.');
            }
        }
        showQuickAuthModal.value = false;
        setTimeout(() => {
            router.visit(route('verification.index'));
        }, 1200);
    } catch (err) {
        showNotification(
            err.response?.data?.error?.message || err.response?.data?.message || err.message || 'Gagal memproses otorisasi.',
            'error'
        );
    } finally {
        isSubmittingQuickAuth.value = false;
    }
}
</script>

<template>
    <Head :title="`Verifikasi ${detail?.id || 'Kuitansi'}`" />

    <!-- Fullscreen Workspace Layout -->
    <div class="h-screen flex flex-col bg-slate-100 overflow-hidden font-sans">
        <!-- Top Workspace Bar -->
        <header class="bg-gov-navy-950 text-white border-b border-gov-navy-800 px-6 py-3 flex items-center justify-between shrink-0 shadow-md">
            <div class="flex items-center gap-4">
                <Link
                    :href="route('verification.index')"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-gov-navy-900 flex items-center gap-1.5 text-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Kembali ke Antrean</span>
                </Link>

                <div class="h-5 w-px bg-slate-700"></div>

                <div v-if="detail" class="flex items-center gap-3">
                    <span class="font-bold text-sm text-white tracking-wide font-mono">{{ detail.nomor_kuitansi || detail.id }}</span>
                    <GovBadge :variant="STATUS_VARIANTS[detail.status]">
                        {{ STATUS_LABELS[detail.status] }}
                    </GovBadge>
                    <span class="text-xs text-slate-400">{{ formatTanggalIndo(detail.tanggal_transaksi || detail.tanggal) }}</span>
                </div>
            </div>

            <!-- Action Buttons Berdasarkan Wewenang & Status Kuitansi -->
            <div v-if="detail" class="flex items-center gap-2.5">
                <!-- 1. Unduh PDF Lembar Kendali jika Lolos Verifikasi -->
                <a
                    v-if="detail.status === 'LOLOS_VERIFIKASI' || detail.qr_token"
                    :href="route('control-sheet.download-pdf', { id: detail.id || props.id })"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-gov-gold-500 text-gov-navy-950 hover:bg-gov-gold-400 shadow-sm transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Unduh PDF Lembar Kendali
                </a>

                <!-- 2. Aksi Khusus LURAH jika status MENUNGGU_LURAH -->
                <template v-if="detail.status === 'MENUNGGU_LURAH' && isLurah">
                    <GovButton variant="danger" size="sm" @click="openQuickAuth('REJECT')">
                        Tolak / Kembalikan
                    </GovButton>
                    <GovButton variant="primary" size="sm" class="bg-purple-700 hover:bg-purple-800 text-white" @click="openQuickAuth('APPROVE')">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Otorisasi Lurah
                    </GovButton>
                </template>

                <!-- 3. Aksi Khusus PPTK jika status MENUNGGU_PPTK -->
                <template v-if="detail.status === 'MENUNGGU_PPTK' && isPptk">
                    <GovButton variant="danger" size="sm" @click="openQuickAuth('REJECT')">
                        Tolak Kuitansi
                    </GovButton>
                    <GovButton variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white" @click="openQuickAuth('APPROVE')">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Setujui PPTK
                    </GovButton>
                </template>

                <!-- 4. Aksi Tim VERIFIKATOR Kecamatan jika status MENUNGGU_VERIFIKASI atau REVISI_MINOR -->
                <template v-if="isVerifikator && ['MENUNGGU_VERIFIKASI', 'REVISI_MINOR'].includes(detail.status)">
                    <GovButton variant="secondary" size="sm" @click="openRevisionModal('REVISION_MINOR')">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Revisi Minor
                    </GovButton>

                    <GovButton variant="danger" size="sm" @click="openRevisionModal('REVISION_MAJOR')">
                        Tolak (Revisi Mayor)
                    </GovButton>

                    <GovButton
                        variant="success"
                        size="sm"
                        :disabled="!allMandatoryVerified"
                        @click="handleApprove"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Lolos Verifikasi
                    </GovButton>
                </template>
            </div>
        </header>

        <!-- Notification Toast In-App -->
        <div
            v-if="notification.show"
            class="mx-6 mt-3 p-3 rounded-lg text-xs font-semibold flex items-center justify-between border shadow-sm transition-all"
            :class="notification.type === 'error' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
        >
            <span>{{ notification.message }}</span>
            <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 ml-4">&times;</button>
        </div>

        <!-- Main Split-Screen Workspace (55% Kiri / 45% Kanan) -->
        <div v-if="detail" class="flex-1 flex overflow-hidden p-3 gap-3">
            <!-- SISI KIRI (55%): PDF / Document Viewer -->
            <div class="w-[55%] h-full flex flex-col gap-2">
                <div v-if="lampiranList.length > 0" class="flex items-center gap-1.5 overflow-x-auto pb-1 shrink-0">
                    <button
                        v-for="(att, idx) in lampiranList"
                        :key="att.id || idx"
                        type="button"
                        @click="activeAttachmentIndex = idx"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border whitespace-nowrap transition"
                        :class="activeAttachmentIndex === idx
                            ? 'bg-gov-navy-900 text-white border-gov-navy-900'
                            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    >
                        {{ att.checklist_code ? `[${att.checklist_code}] ` : '' }}{{ att.nama_dokumen }}
                    </button>
                </div>
                <div class="flex-1 min-h-0">
                    <PdfViewer
                        :file-url="activeAttachment?.file_url"
                        :file-name="activeAttachment?.file_name"
                    />
                </div>
            </div>

            <!-- SISI KANAN (45%): Checklist Dokumen & Validasi Nilai -->
            <div class="w-[45%] h-full flex flex-col bg-white rounded-xl border border-gov-surface-border shadow-sm overflow-hidden">
                <!-- Panel Tab & Meta -->
                <div class="bg-gov-surface-subtle p-4 border-b border-gov-surface-border shrink-0">
                    <div class="flex items-center justify-between text-xs gap-2">
                        <span class="text-slate-500">
                            Unit: <strong class="text-gov-navy-950">{{ detail.unit_kerja?.nama_unit || detail.unit || '-' }}</strong>
                        </span>
                        <div class="text-right">
                            <span class="text-base font-bold text-gov-navy-950 tabular-nums block">
                                {{ formatRupiah(detail.nominal_bruto ?? detail.nominal ?? 0) }}
                            </span>
                            <span class="text-[11px] text-emerald-700 font-semibold tabular-nums">
                                Neto: {{ formatRupiah(detail.nominal_neto ?? detail.nominal_bruto ?? 0) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-700 mt-1 line-clamp-2 leading-relaxed">{{ detail.uraian }}</p>

                    <!-- Tab Navigation: Checklist | Riwayat Audit -->
                    <div class="flex items-center gap-1 mt-3 border-t border-gov-surface-border pt-3">
                        <button
                            type="button"
                            @click="activeTab = 'checklist'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                            :class="activeTab === 'checklist'
                                ? 'bg-gov-navy-900 text-white shadow-sm'
                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                        >
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                Checklist Dokumen
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'timeline'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                            :class="activeTab === 'timeline'
                                ? 'bg-gov-navy-900 text-white shadow-sm'
                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                        >
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Riwayat Audit
                                <span v-if="auditHistory.length > 0" class="ml-0.5 px-1.5 py-0.5 rounded-full bg-gov-navy-100 text-gov-navy-800 text-[10px] font-bold">
                                    {{ auditHistory.length }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content: Checklist Dokumen Fisik -->
                <div v-show="activeTab === 'checklist'" class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs font-bold text-gov-navy-900 border-b pb-2">
                        <span>Checklist Hasil Pemeriksaan Fisik Dokumen</span>
                        <span class="text-[11px] font-normal text-slate-500">
                            {{ detail.checklist?.length || 0 }} butir • {{ lampiranList.length }} lampiran
                        </span>
                    </div>

                    <div
                        v-if="!detail.checklist || detail.checklist.length === 0"
                        class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800"
                    >
                        Daftar checklist kategori rekening ini belum tersedia. Tombol Lolos Verifikasi dikunci sampai checklist tersedia dan terverifikasi.
                    </div>

                    <div
                        v-if="lampiranList.length === 0"
                        class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800"
                    >
                        Belum ada berkas lampiran yang diunggah pada kuitansi ini.
                    </div>

                    <div
                        v-for="(item, idx) in detail.checklist || []"
                        :key="item.item_id || item.kode_item || idx"
                        @click="selectAttachmentForChecklist(item)"
                        class="p-3 rounded-lg border transition-all cursor-pointer text-xs border-gov-surface-border bg-white hover:bg-slate-50"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 flex-1">
                                <span
                                    class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 text-[10px] font-bold"
                                    :class="[
                                        item.ui_status === 'VALID' ? 'bg-emerald-100 text-emerald-800' :
                                        item.ui_status === 'REVISION_NEEDED' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'
                                    ]"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-[10px] text-slate-500">{{ item.kode_item }}</span>
                                        <h4 class="font-bold text-gov-navy-950">{{ item.nama_item }}</h4>
                                        <span v-if="item.is_required" class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1 rounded">
                                            Wajib
                                        </span>
                                        <span v-else class="text-[9px] font-semibold text-slate-500 bg-slate-100 px-1 rounded">
                                            Opsional
                                        </span>
                                    </div>
                                    <p v-if="item.deskripsi" class="text-[11px] text-slate-500 mt-0.5">{{ item.deskripsi }}</p>
                                </div>
                            </div>

                            <!-- Document Status Buttons (Khusus Verifikator) atau Status Badge -->
                            <div v-if="isVerifikator" class="flex items-center gap-1 shrink-0">
                                <button
                                    type="button"
                                    @click.stop="setChecklistStatus(idx, 'VALID')"
                                    class="px-2 py-1 rounded text-[10px] font-bold transition-colors"
                                    :class="item.ui_status === 'VALID' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                >
                                    ✓ Lolos
                                </button>
                                <button
                                    type="button"
                                    @click.stop="setChecklistStatus(idx, 'REVISION_NEEDED')"
                                    class="px-2 py-1 rounded text-[10px] font-bold transition-colors"
                                    :class="item.ui_status === 'REVISION_NEEDED' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                >
                                    ✕ Ada Catatan
                                </button>
                            </div>
                            <div v-else class="shrink-0">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                    :class="item.ui_status === 'VALID' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ item.ui_status === 'VALID' ? 'Lolos' : 'Pending' }}
                                </span>
                            </div>
                        </div>

                        <!-- Catatan Temuan jika ada -->
                        <div v-if="item.catatan" class="mt-2 p-2 bg-amber-50 rounded text-[11px] text-amber-800 border border-amber-200">
                            <strong>Temuan:</strong> {{ item.catatan }}
                        </div>
                    </div>

                    <!-- Riwayat Revisi Terdahulu (Audit Trail) -->
                    <div v-if="detail.revision_history?.length > 0" class="mt-4 pt-3 border-t">
                        <h5 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Riwayat Revisi Berkas</h5>
                        <div class="space-y-2">
                            <div
                                v-for="(rev, rIdx) in detail.revision_history"
                                :key="rIdx"
                                class="p-2.5 rounded bg-amber-50/70 border border-amber-200 text-xs"
                            >
                                <div class="flex items-center justify-between text-[10px] text-amber-900 font-semibold">
                                    <span>{{ rev.author }}</span>
                                    <span>{{ rev.timestamp }}</span>
                                </div>
                                <p class="text-[11px] text-slate-700 mt-1">{{ rev.note }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Riwayat Audit Timeline -->
                <div v-show="activeTab === 'timeline'" class="flex-1 overflow-y-auto p-4">
                    <div v-if="loadingHistory" class="flex items-center justify-center py-10">
                        <div class="text-sm text-slate-400 animate-pulse">Memuat riwayat audit...</div>
                    </div>
                    <AuditTimeline v-else :events="auditHistory" />
                </div>

                <!-- Footer Rangkuman Kas -->
                <div class="p-3 bg-gov-surface-subtle border-t border-gov-surface-border flex items-center justify-between text-xs shrink-0">
                    <span class="text-slate-500">
                        Status Kelayakan:
                        <strong :class="allMandatoryVerified ? 'text-emerald-700' : 'text-amber-700'">
                            {{ allMandatoryVerified ? 'Siap Lolos Verifikasi' : 'Belum Semua Dokumen Lolos' }}
                        </strong>
                    </span>
                    <span class="text-[11px] text-slate-400">Pemeriksa: {{ currentUser?.name || 'Verifikator' }}</span>
                </div>
            </div>
        </div>

        <!-- Modal Revisi Drawer (Verifikator) -->
        <RevisionDrawer
            :show="showRevisionModal"
            :receipt-id="detail?.id || ''"
            :action-type="revisionActionType"
            @close="showRevisionModal = false"
            @submit="handleActionSubmit"
        />

        <!-- Modal Otorisasi Cepat (Lurah & PPTK) -->
        <div
            v-if="showQuickAuthModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden">
                <div
                    :class="[
                        'px-6 py-4 text-white font-bold flex items-center justify-between',
                        quickAuthType === 'APPROVE' ? (detail?.status === 'MENUNGGU_LURAH' ? 'bg-purple-800' : 'bg-emerald-700') : 'bg-rose-700'
                    ]"
                >
                    <span class="text-sm font-bold">
                        {{ detail?.status === 'MENUNGGU_LURAH' ? (quickAuthType === 'APPROVE' ? 'Otorisasi Lurah (KPA Kelurahan)' : 'Penolakan oleh Lurah') : (quickAuthType === 'APPROVE' ? 'Persetujuan PPTK' : 'Penolakan PPTK') }}
                    </span>
                    <button @click="showQuickAuthModal = false" class="text-white/80 hover:text-white text-lg">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-slate-500">No. Kuitansi:</span>
                            <span class="font-bold text-slate-800">{{ detail?.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Nominal:</span>
                            <span class="font-bold text-gov-navy-950">{{ formatRupiah(detail?.nominal || 0) }}</span>
                        </div>
                    </div>

                    <div v-if="quickAuthType === 'APPROVE'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Persetujuan (Opsional):</label>
                        <textarea
                            v-model="quickAuthNotes"
                            rows="3"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-gov-navy-800 focus:ring-gov-navy-800"
                            placeholder="Catatan..."
                        ></textarea>
                    </div>
                    <div v-else>
                        <label class="block text-xs font-semibold text-rose-800 mb-1">Alasan Penolakan (Wajib):</label>
                        <textarea
                            v-model="quickAuthNotes"
                            rows="3"
                            class="w-full text-xs rounded-lg border-rose-300 focus:border-rose-500 focus:ring-rose-500"
                            placeholder="Alasan penolakan..."
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <GovButton variant="secondary" size="sm" @click="showQuickAuthModal = false">Batal</GovButton>
                        <GovButton
                            :variant="quickAuthType === 'APPROVE' ? 'primary' : 'danger'"
                            size="sm"
                            :disabled="isSubmittingQuickAuth"
                            @click="handleQuickAuthSubmit"
                        >
                            {{ isSubmittingQuickAuth ? 'Memproses...' : (quickAuthType === 'APPROVE' ? 'Konfirmasi Setujui' : 'Konfirmasi Tolak') }}
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
