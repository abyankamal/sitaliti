<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Download, Check, RotateCcw, ClipboardList, Clock } from 'lucide-vue-next';
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
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const { confirm } = useConfirm();

const props = defineProps({
    id: {
        type: [Number, String],
        default: 'KW-2026-06-0042',
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const userRole = computed(() => currentUser.value.role || '');
const isVerifikator = computed(() => ['BP', 'VERIFIKATOR'].includes(userRole.value));
const isPptk = computed(() => userRole.value === 'PPTK');

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
    try {
        const res = await verificationService.submitAction(detail.value.id, payload);
        toast.success(res.message || 'Aksi verifikasi berhasil diproses.');
        router.visit(route('verification.index'));
    } catch (err) {
        toast.error(
            err.response?.data?.error?.message || err.response?.data?.message || err.message || 'Gagal memproses aksi verifikasi.'
        );
    }
}

async function handleApprove() {
    if (!allMandatoryVerified.value) {
        toast.error('Seluruh butir checklist wajib harus berstatus Lolos dan minimal 1 lampiran harus tersedia.');
        return;
    }

    const checklistResults = detail.value.checklist.map((item) => ({
        item_id: item.item_id,
        status: item.ui_status === 'REVISION_NEEDED' ? 'TIDAK_SESUAI' : 'SESUAI',
        notes: item.catatan || 'Sesuai dengan ketentuan regulasi dan bukti fisik.',
    }));

    await confirm({
        title: 'Sahkan Lolos Verifikasi?',
        message: 'Kuitansi akan dinyatakan Lolos Verifikasi Akuntabel dan siap dibundel SPP.',
        details: detail.value.nomor_bukti ? `No. Bukti: ${detail.value.nomor_bukti}` : '',
        variant: 'primary',
        confirmText: 'Ya, Lolos Verifikasi',
        onConfirm: async () => {
            const res = await verificationService.submitAction(detail.value.id, {
                action: 'VERIFIED',
                notes: 'Semua kelengkapan dokumen telah diteliti dan valid.',
                checklist_results: checklistResults,
            });
            toast.success(res.message || 'Kuitansi dinyatakan Lolos Verifikasi Akuntabel.');
            router.visit(route('control-sheet.show', { id: detail.value.id }));
        },
    });
}

// Quick Auth (Lurah / PPTK) dari Workspace
async function openQuickAuth(type) {
    if (!detail.value) return;
    const isReject = type === 'REJECT';
    const targetId = detail.value.id;

    await confirm({
        title: isReject ? 'Tolak Kuitansi?' : 'Setujui Kuitansi?',
        message: isReject
            ? 'Kuitansi akan dikembalikan ke draf Operator.'
            : 'Kuitansi akan disetujui dan diteruskan ke tahapan selanjutnya.',
        variant: isReject ? 'danger' : 'primary',
        confirmText: isReject ? 'Tolak & Kembalikan' : 'Ya, Setujui',
        requireReason: isReject,
        reasonLabel: 'Alasan Penolakan',
        reasonPlaceholder: 'Cantumkan alasan penolakan minimal 5 karakter...',
        minReasonLength: 5,
        onConfirm: async (reason) => {
            if (detail.value.status === 'MENUNGGU_PPTK' && isPptk.value) {
                if (!isReject) {
                    await receiptService.approveByPptk(targetId, '');
                    toast.success('Kuitansi disetujui PPTK dan diteruskan ke tahapan selanjutnya.');
                } else {
                    await receiptService.rejectByPptk(targetId, reason);
                    toast.success('Kuitansi ditolak oleh PPTK dan dikembalikan ke draf Operator.');
                }
            }
            router.visit(route('verification.index'));
        },
    });
}
</script>

<template>
    <Head :title="`Verifikasi ${detail?.id || 'Kuitansi'}`" />

    <!-- Fullscreen Workspace Layout -->
    <div class="min-h-screen lg:h-screen flex flex-col bg-canvas lg:overflow-hidden font-sans text-ink">
        <!-- Top Workspace Bar -->
        <header class="bg-surface text-ink border-b border-hairline px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <Link
                    :href="route('verification.index')"
                    class="p-1.5 rounded-lg text-ink-secondary hover:text-ink hover:bg-raised flex items-center gap-1.5 text-xs shrink-0"
                >
                    <ArrowLeft class="w-4 h-4" :stroke-width="1.5" />
                    <span>Kembali ke Antrean</span>
                </Link>

                <div class="h-5 w-px bg-hairline shrink-0"></div>

                <div v-if="detail" class="flex items-center gap-2.5 min-w-0 flex-wrap">
                    <span class="font-semibold text-sm text-ink tracking-wide font-mono truncate">{{ detail.nomor_kuitansi || detail.id }}</span>
                    <GovBadge :variant="STATUS_VARIANTS[detail.status]">
                        {{ STATUS_LABELS[detail.status] }}
                    </GovBadge>
                    <span class="text-xs text-ink-muted">{{ formatTanggalIndo(detail.tanggal_transaksi || detail.tanggal) }}</span>
                </div>
            </div>

            <!-- Action Buttons Berdasarkan Wewenang & Status Kuitansi -->
            <div v-if="detail" class="flex items-center gap-2 flex-wrap">
                <!-- 1. Unduh PDF Lembar Kendali jika Lolos Verifikasi -->
                <a
                    v-if="detail.status === 'LOLOS_VERIFIKASI' || detail.qr_token"
                    :href="route('control-sheet.download-pdf', { id: detail.id || props.id })"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-medium bg-accent text-white hover:bg-accent-hover shadow-subtle transition-colors"
                >
                    <Download class="w-3.5 h-3.5" :stroke-width="1.5" />
                    Unduh PDF Lembar Kendali
                </a>

                <!-- 2. Aksi Khusus PPTK jika status MENUNGGU_PPTK -->
                <template v-if="detail.status === 'MENUNGGU_PPTK' && isPptk">
                    <GovButton variant="danger" size="sm" @click="openQuickAuth('REJECT')">
                        Tolak Kuitansi
                    </GovButton>
                    <GovButton variant="success" size="sm" @click="openQuickAuth('APPROVE')">
                        <Check class="w-3.5 h-3.5" :stroke-width="1.5" />
                        Setujui PPTK
                    </GovButton>
                </template>

                <!-- 4. Aksi Tim VERIFIKATOR Kecamatan jika status MENUNGGU_VERIFIKASI atau REVISI_MINOR -->
                <template v-if="isVerifikator && ['MENUNGGU_VERIFIKASI', 'REVISI_MINOR'].includes(detail.status)">
                    <GovButton variant="secondary" size="sm" @click="openRevisionModal('REVISION_MINOR')">
                        <RotateCcw class="w-3.5 h-3.5 text-semantic-warning" :stroke-width="1.5" />
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
                        <Check class="w-3.5 h-3.5" :stroke-width="1.5" />
                        Lolos Verifikasi
                    </GovButton>
                </template>
            </div>
        </header>

        <!-- Main Split-Screen Workspace (55% Kiri / 45% Kanan di Desktop, Stack di Mobile) -->
        <div v-if="detail" class="flex-1 flex flex-col lg:flex-row lg:overflow-hidden p-3 gap-3 min-h-0">
            <!-- SISI KIRI (55%): PDF / Document Viewer -->
            <div class="w-full lg:w-[55%] min-h-[420px] lg:h-full flex flex-col gap-2 min-h-0">
                <div v-if="lampiranList.length > 0" class="flex items-center gap-1.5 overflow-x-auto scrollbar-thin pb-1 shrink-0">
                    <button
                        v-for="(att, idx) in lampiranList"
                        :key="att.id || idx"
                        type="button"
                        @click="activeAttachmentIndex = idx"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold border whitespace-nowrap transition-colors"
                        :class="activeAttachmentIndex === idx
                            ? 'bg-accent text-white border-accent'
                            : 'bg-surface text-ink-secondary border-hairline hover:bg-raised'"
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
            <div class="w-full lg:w-[45%] lg:h-full flex flex-col bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden min-h-0">
                <!-- Panel Tab & Meta -->
                <div class="bg-raised p-4 border-b border-hairline shrink-0">
                    <div class="flex items-center justify-between text-xs gap-2">
                        <span class="text-ink-muted">
                            Unit: <strong class="text-ink">{{ detail.unit_kerja?.nama_unit || detail.unit || '-' }}</strong>
                        </span>
                        <div class="text-right">
                            <span class="text-base font-semibold text-ink font-mono tabular-nums block">
                                {{ formatRupiah(detail.nominal_bruto ?? detail.nominal ?? 0) }}
                            </span>
                            <span class="text-xs text-semantic-success font-semibold font-mono tabular-nums">
                                Neto: {{ formatRupiah(detail.nominal_neto ?? detail.nominal_bruto ?? 0) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-ink-secondary mt-1 line-clamp-2 leading-relaxed">{{ detail.uraian }}</p>

                    <!-- Tab Navigation: Checklist | Riwayat Audit -->
                    <div class="flex items-center gap-1 mt-3 border-t border-hairline pt-3">
                        <button
                            type="button"
                            @click="activeTab = 'checklist'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                            :class="activeTab === 'checklist'
                                ? 'bg-accent text-white shadow-subtle'
                                : 'text-ink-muted hover:bg-surface hover:text-ink'"
                        >
                            <span class="flex items-center gap-1.5">
                                <ClipboardList class="w-3.5 h-3.5" :stroke-width="1.5" />
                                Checklist Dokumen
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'timeline'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                            :class="activeTab === 'timeline'
                                ? 'bg-accent text-white shadow-subtle'
                                : 'text-ink-muted hover:bg-surface hover:text-ink'"
                        >
                            <span class="flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5" :stroke-width="1.5" />
                                Riwayat Audit
                                <span v-if="auditHistory.length > 0" class="ml-0.5 px-1.5 py-0.5 rounded-md bg-accent-soft text-accent text-xs font-semibold">
                                    {{ auditHistory.length }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content: Checklist Dokumen Fisik -->
                <div v-show="activeTab === 'checklist'" class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs font-semibold text-ink border-b border-hairline pb-2">
                        <span>Checklist Hasil Pemeriksaan Fisik Dokumen</span>
                        <span class="text-xs font-normal text-ink-muted">
                            {{ detail.checklist?.length || 0 }} butir • {{ lampiranList.length }} lampiran
                        </span>
                    </div>

                    <div
                        v-if="(detail.sbm_warnings || []).length > 0"
                        role="status"
                        class="p-3 rounded-lg bg-amber-50 border border-amber-300 text-xs text-amber-900 space-y-1"
                    >
                        <div class="font-semibold">Peringatan Kewajaran Harga SBM/SSH</div>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li v-for="(warn, wIdx) in detail.sbm_warnings" :key="wIdx">
                                {{ warn.message }}
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="(detail.pajak || []).length > 0"
                        class="p-3 rounded-lg bg-raised border border-hairline text-xs space-y-2"
                    >
                        <div class="font-semibold text-ink">
                            Status Setor Pajak (Kode Billing &amp; NTPN)
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="pj in detail.pajak"
                                :key="pj.id"
                                class="flex items-center justify-between gap-2 bg-surface p-2 rounded border border-hairline"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-ink">{{ pj.nama_pajak }}</div>
                                    <div class="text-xs text-ink-muted font-mono tabular-nums">
                                        {{ formatRupiah(pj.nominal) }}
                                        <span v-if="pj.ntpn"> • NTPN: {{ pj.ntpn }}</span>
                                    </div>
                                </div>
                                <span
                                    v-if="pj.kode_pajak === 'BEA_METERAI'"
                                    class="px-1.5 py-0.5 rounded bg-raised text-ink-secondary text-xs font-semibold shrink-0"
                                >
                                    Dokumen Fisik
                                </span>
                                <span
                                    v-else-if="pj.ntpn"
                                    class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-xs font-semibold shrink-0"
                                >
                                    NTPN Terverifikasi
                                </span>
                                <span
                                    v-else
                                    class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-xs font-semibold shrink-0"
                                >
                                    Belum Setor
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="(detail.sppd_peserta && detail.sppd_peserta.length > 0) || detail.sppd_foto_url"
                        class="p-3 rounded-lg bg-raised border border-hairline text-xs space-y-2"
                    >
                        <div class="font-semibold text-ink">
                            Pelaksana SPPD ({{ detail.sppd_peserta?.length || 0 }} Orang) &amp; Foto Keberangkatan
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="peserta in detail.sppd_peserta || []"
                                :key="peserta.id"
                                class="flex items-center justify-between gap-2 bg-surface p-2 rounded border border-hairline"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-ink truncate">{{ peserta.pegawai?.nama || '-' }}</div>
                                    <div class="text-xs text-ink-muted font-mono tabular-nums">
                                        {{ peserta.pegawai?.nip || '-' }} • {{ peserta.tanggal_berangkat }} s/d {{ peserta.tanggal_pulang }}
                                    </div>
                                    <div v-if="peserta.tujuan" class="text-xs text-ink-secondary truncate">{{ peserta.tujuan }}</div>
                                </div>
                                <a
                                    v-if="peserta.effective_foto_url"
                                    :href="peserta.effective_foto_url"
                                    target="_blank"
                                    class="shrink-0"
                                    title="Buka Foto Keberangkatan"
                                >
                                    <img
                                        :src="peserta.effective_foto_url"
                                        alt="Foto Keberangkatan"
                                        class="w-10 h-10 rounded object-cover border border-hairline"
                                    />
                                </a>
                            </div>
                        </div>
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
                        class="p-3 rounded-lg border transition-colors duration-150 ease-unslop cursor-pointer text-xs border-hairline bg-surface hover:bg-raised"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 flex-1">
                                <span
                                    class="w-5 h-5 rounded-md flex items-center justify-center shrink-0 text-xs font-semibold"
                                    :class="[
                                        item.ui_status === 'VALID' ? 'bg-emerald-100 text-emerald-800' :
                                        item.ui_status === 'REVISION_NEEDED' ? 'bg-rose-100 text-rose-800' : 'bg-raised text-ink-secondary'
                                    ]"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-xs text-ink-muted">{{ item.kode_item }}</span>
                                        <h4 class="font-semibold text-ink">{{ item.nama_item }}</h4>
                                        <span v-if="item.is_required" class="text-xs font-semibold text-semantic-danger bg-rose-50 px-1 rounded">
                                            Wajib
                                        </span>
                                        <span v-else class="text-xs font-medium text-ink-muted bg-raised px-1 rounded">
                                            Opsional
                                        </span>
                                    </div>
                                    <p v-if="item.deskripsi" class="text-xs text-ink-muted mt-0.5">{{ item.deskripsi }}</p>
                                </div>
                            </div>

                            <!-- Document Status Buttons (Khusus Verifikator) atau Status Badge -->
                            <div v-if="isVerifikator" class="flex items-center gap-1 shrink-0">
                                <button
                                    type="button"
                                    @click.stop="setChecklistStatus(idx, 'VALID')"
                                    class="px-2 py-1 rounded text-xs font-semibold transition-colors"
                                    :class="item.ui_status === 'VALID' ? 'bg-semantic-success text-white' : 'bg-raised text-ink-secondary hover:bg-hairline'"
                                >
                                    Lolos
                                </button>
                                <button
                                    type="button"
                                    @click.stop="setChecklistStatus(idx, 'REVISION_NEEDED')"
                                    class="px-2 py-1 rounded text-xs font-semibold transition-colors"
                                    :class="item.ui_status === 'REVISION_NEEDED' ? 'bg-semantic-danger text-white' : 'bg-raised text-ink-secondary hover:bg-hairline'"
                                >
                                    Ada Catatan
                                </button>
                            </div>
                            <div v-else class="shrink-0">
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-semibold"
                                    :class="item.ui_status === 'VALID' ? 'bg-emerald-100 text-emerald-800' : 'bg-raised text-ink-secondary'"
                                >
                                    {{ item.ui_status === 'VALID' ? 'Lolos' : 'Pending' }}
                                </span>
                            </div>
                        </div>

                        <!-- Catatan Temuan jika ada -->
                        <div v-if="item.catatan" class="mt-2 p-2 bg-amber-50 rounded text-xs text-amber-800 border border-amber-200">
                            <strong>Temuan:</strong> {{ item.catatan }}
                        </div>
                    </div>

                    <!-- Riwayat Revisi Terdahulu (Audit Trail) -->
                    <div v-if="detail.revision_history?.length > 0" class="mt-4 pt-3 border-t border-hairline">
                        <h5 class="text-xs font-semibold text-ink-secondary mb-2">Riwayat Revisi Berkas</h5>
                        <div class="space-y-2">
                            <div
                                v-for="(rev, rIdx) in detail.revision_history"
                                :key="rIdx"
                                class="p-2.5 rounded bg-amber-50/70 border border-amber-200 text-xs"
                            >
                                <div class="flex items-center justify-between text-xs text-amber-900 font-semibold">
                                    <span>{{ rev.author }}</span>
                                    <span>{{ rev.timestamp }}</span>
                                </div>
                                <p class="text-xs text-ink-secondary mt-1">{{ rev.note }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Riwayat Audit Timeline -->
                <div v-show="activeTab === 'timeline'" class="flex-1 overflow-y-auto scrollbar-thin p-4">
                    <div v-if="loadingHistory" class="flex items-center justify-center py-10">
                        <div class="text-sm text-ink-muted">Memuat riwayat audit...</div>
                    </div>
                    <AuditTimeline v-else :events="auditHistory" />
                </div>

                <!-- Footer Rangkuman Kas -->
                <div class="p-3 bg-raised border-t border-hairline flex items-center justify-between text-xs shrink-0">
                    <span class="text-ink-muted">
                        Status Kelayakan:
                        <strong :class="allMandatoryVerified ? 'text-semantic-success' : 'text-semantic-warning'">
                            {{ allMandatoryVerified ? 'Siap Lolos Verifikasi' : 'Belum Semua Dokumen Lolos' }}
                        </strong>
                    </span>
                    <span class="text-xs text-ink-muted">Pemeriksa: {{ currentUser?.name || 'Verifikator' }}</span>
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
    </div>
</template>
