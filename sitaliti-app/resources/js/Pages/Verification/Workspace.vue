<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
        type: String,
        default: 'KW-2026-06-0042',
    },
});

const detail = ref(null);
const loading = ref(true);
const activeAttachmentIndex = ref(0);
const activeTab = ref('checklist'); // 'checklist' | 'timeline'
const auditHistory = ref([]);
const loadingHistory = ref(false);

// Modal Revision State
const showRevisionModal = ref(false);
const revisionActionType = ref('REVISION_MINOR');

onMounted(async () => {
    try {
        detail.value = await verificationService.getDetail(props.id);
        // Fetch audit trail history in parallel
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

const activeAttachment = computed(() => {
    if (!detail.value || !detail.value.attachments) return null;
    return detail.value.attachments[activeAttachmentIndex.value] || null;
});

// Cek kelayakan lolos verifikasi
const allMandatoryVerified = computed(() => {
    if (!detail.value || !detail.value.attachments) return false;
    return detail.value.attachments
        .filter(att => att.required)
        .every(att => att.status === 'VALID');
});

function setDocStatus(index, newStatus) {
    if (detail.value && detail.value.attachments[index]) {
        detail.value.attachments[index].status = newStatus;
    }
}

function openRevisionModal(type) {
    revisionActionType.value = type;
    showRevisionModal.value = true;
}

async function handleActionSubmit(payload) {
    showRevisionModal.value = false;
    const res = await verificationService.submitAction(detail.value.id, payload);
    alert(res.message);
    router.visit(route('verification.index'));
}

async function handleApprove() {
    if (!allMandatoryVerified.value) {
        alert('Seluruh dokumen bertanda Wajib harus diverifikasi Lolos (Valid) terlebih dahulu.');
        return;
    }

    if (confirm('Konfirmasi: Berkas kuitansi ini memenuhi seluruh syarat akuntabilitas. Terbitkan Lembar Kendali Verifikasi?')) {
        const res = await verificationService.submitAction(detail.value.id, {
            action: 'VERIFIED',
            notes: 'Semua kelengkapan dokumen telah diteliti dan valid.',
        });
        alert(res.message);
        router.visit(route('control-sheet.show', { id: detail.value.id }));
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
                    <span class="text-sm font-bold text-white">{{ detail.id }}</span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-gov-gold-600/20 text-gov-gold-400 border border-gov-gold-500/30">
                        Kategori {{ detail.category }}
                    </span>
                    <GovBadge :variant="STATUS_VARIANTS[detail.status]">
                        {{ STATUS_LABELS[detail.status] }}
                    </GovBadge>
                </div>
            </div>

            <!-- Action Buttons Verifikator -->
            <div class="flex items-center gap-2.5">
                <a
                    v-if="detail && (detail.status === 'LOLOS_VERIFIKASI' || detail.qr_token)"
                    :href="route('control-sheet.download-pdf', { id: detail.id || props.id })"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-gov-gold-500 text-gov-navy-950 hover:bg-gov-gold-400 shadow-sm transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Unduh PDF Lembar Kendali
                </a>

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
            </div>
        </header>

        <!-- Main Split-Screen Workspace (55% Kiri / 45% Kanan) -->
        <div v-if="detail" class="flex-1 flex overflow-hidden p-3 gap-3">
            <!-- SISI KIRI (55%): PDF / Document Viewer -->
            <div class="w-[55%] h-full flex flex-col">
                <PdfViewer
                    :file-url="activeAttachment?.file_url"
                    :file-name="activeAttachment?.file_name"
                />
            </div>

            <!-- SISI KANAN (45%): Checklist Dokumen & Validasi Nilai -->
            <div class="w-[45%] h-full flex flex-col bg-white rounded-xl border border-gov-surface-border shadow-sm overflow-hidden">
                <!-- Panel Tab & Meta -->
                <div class="bg-gov-surface-subtle p-4 border-b border-gov-surface-border shrink-0">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Unit: <strong class="text-gov-navy-950">{{ detail.unit }}</strong></span>
                        <span class="text-base font-bold text-gov-navy-950 tabular-nums">{{ formatRupiah(detail.nominal) }}</span>
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

                <!-- Tab Content: Checklist Items List (Scrollable) -->
                <div v-show="activeTab === 'checklist'" class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs font-bold text-gov-navy-900 border-b pb-2">
                        <span>Checklist Hasil Pemeriksaan Fisik Dokumen</span>
                        <span class="text-[11px] font-normal text-slate-500">Pilih dokumen untuk diuji</span>
                    </div>

                    <div
                        v-for="(att, idx) in detail.attachments"
                        :key="att.id"
                        @click="activeAttachmentIndex = idx"
                        class="p-3 rounded-lg border transition-all cursor-pointer text-xs"
                        :class="[
                            activeAttachmentIndex === idx
                                ? 'border-gov-navy-800 ring-2 ring-gov-navy-800/10 bg-gov-navy-50/50'
                                : 'border-gov-surface-border bg-white hover:bg-slate-50'
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 flex-1">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 text-[10px] font-bold"
                                    :class="[
                                        att.status === 'VALID' ? 'bg-emerald-100 text-emerald-800' :
                                        att.status === 'REVISION_NEEDED' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'
                                    ]"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="font-bold text-gov-navy-950">{{ att.doc_name }}</h4>
                                        <span v-if="att.required" class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1 rounded">
                                            Wajib
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 font-mono truncate max-w-xs mt-0.5">{{ att.file_name }}</p>
                                </div>
                            </div>

                            <!-- Document Status Buttons -->
                            <div class="flex items-center gap-1 shrink-0">
                                <button
                                    type="button"
                                    @click.stop="setDocStatus(idx, 'VALID')"
                                    class="px-2 py-1 rounded text-[10px] font-bold transition-colors"
                                    :class="att.status === 'VALID' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                >
                                    ✓ Lolos
                                </button>
                                <button
                                    type="button"
                                    @click.stop="setDocStatus(idx, 'REVISION_NEEDED')"
                                    class="px-2 py-1 rounded text-[10px] font-bold transition-colors"
                                    :class="att.status === 'REVISION_NEEDED' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                >
                                    ✕ Ada Catatan
                                </button>
                            </div>
                        </div>

                        <!-- Catatan Temuan jika ada -->
                        <div v-if="att.notes" class="mt-2 p-2 bg-amber-50 rounded text-[11px] text-amber-800 border border-amber-200">
                            <strong>Temuan:</strong> {{ att.notes }}
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
                    <span class="text-[11px] text-slate-400">Verifikator: PPK Kecamatan</span>
                </div>
            </div>
        </div>

        <!-- Modal Revisi Drawer -->
        <RevisionDrawer
            :show="showRevisionModal"
            :receipt-id="detail?.id || ''"
            :action-type="revisionActionType"
            @close="showRevisionModal = false"
            @submit="handleActionSubmit"
        />
    </div>
</template>

