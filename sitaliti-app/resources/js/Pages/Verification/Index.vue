<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clock, Check, X, Eye } from 'lucide-vue-next';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { verificationService } from '@/Services/verificationService';
import { receiptService } from '@/Services/receiptService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const { confirm } = useConfirm();

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const userRole = computed(() => user.value.role || 'VERIFIKATOR');
const isKelurahan = computed(() => user.value.is_kelurahan_scoped);

const queue = ref([]);
const loading = ref(true);
const activeTab = ref('ALL'); // ALL, MENUNGGU_PPTK, MENUNGGU_LURAH, MENUNGGU_VERIFIKASI, REVISI_MINOR, LOLOS_VERIFIKASI

async function loadQueue() {
    loading.value = true;
    try {
        queue.value = await verificationService.getQueue();
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (userRole.value === 'PPTK') {
        activeTab.value = 'MENUNGGU_PPTK';
    } else if (userRole.value === 'LURAH') {
        activeTab.value = 'MENUNGGU_LURAH';
    }
    loadQueue();
});

const filteredQueue = computed(() => {
    if (activeTab.value === 'ALL') return queue.value;
    return queue.value.filter(item => item.status === activeTab.value);
});

const countPptk = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_PPTK').length);
const countLurah = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_LURAH').length);
const countVerifikasi = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_VERIFIKASI').length);
const countRevisi = computed(() => queue.value.filter(item => item.status === 'REVISI_MINOR').length);
const countLolos = computed(() => queue.value.filter(item => item.status === 'LOLOS_VERIFIKASI').length);

async function openAuthModal(item, role, action) {
    const targetId = item.kuitansi_id || item.id;
    const isReject = action === 'REJECT';

    await confirm({
        title: isReject
            ? role === 'LURAH'
                ? 'Tolak Kuitansi (Lurah)?'
                : 'Tolak Kuitansi (PPTK)?'
            : role === 'LURAH'
              ? 'Otorisasi Kuitansi (Lurah)?'
              : 'Setujui Kuitansi (PPTK)?',
        message: isReject
            ? 'Kuitansi akan dikembalikan ke draf Operator dan pagu anggaran kas dipulihkan.'
            : role === 'LURAH'
              ? 'Kuitansi akan diotorisasi dan diteruskan ke Tim Verifikator Kecamatan.'
              : 'Kuitansi akan disetujui dan diteruskan ke tahapan selanjutnya.',
        details: item.nomor_bukti ? `No. Bukti: ${item.nomor_bukti}` : '',
        variant: isReject ? 'danger' : 'primary',
        confirmText: isReject ? 'Tolak & Kembalikan' : 'Ya, Lanjutkan',
        requireReason: isReject,
        reasonLabel: 'Alasan Penolakan',
        reasonPlaceholder: 'Cantumkan alasan penolakan minimal 5 karakter...',
        minReasonLength: 5,
        onConfirm: async (reason) => {
            if (!isReject) {
                await receiptService.approveByPptk(targetId, '');
                toast.success('Kuitansi berhasil disetujui PPTK dan diteruskan ke tahapan selanjutnya.');
            } else {
                await receiptService.rejectByPptk(targetId, reason);
                toast.success('Kuitansi ditolak oleh PPTK dan dikembalikan ke draf Operator.');
            }
            await loadQueue();
        },
    });
}
</script>

<template>
    <Head title="Antrean Verifikasi Kuitansi SPJ" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-ink flex items-center gap-2">
                        <span>Antrean Verifikasi &amp; Otorisasi Kuitansi</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-md font-semibold bg-accent-soft text-accent border border-accent-border">
                            {{ isKelurahan ? (user.unit_kerja?.nama_unit || 'Kelurahan') : 'Tim Verifikator & PPK' }}
                        </span>
                    </h1>
                    <p class="text-xs text-ink-muted mt-1">
                        Pemeriksaan bukti kepatuhan akuntabilitas dan alur pengesahan bertingkat sebelum dibundel menjadi SPJ
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Filter Tabs -->
            <div class="flex items-center gap-2 border-b border-hairline pb-2 overflow-x-auto text-xs font-medium">
                <button
                    @click="activeTab = 'ALL'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap',
                        activeTab === 'ALL'
                            ? 'bg-accent text-white font-semibold'
                            : 'bg-surface text-ink-secondary hover:bg-raised border border-hairline'
                    ]"
                >
                    Semua Berkas ({{ queue.length }})
                </button>
                <button
                    @click="activeTab = 'MENUNGGU_PPTK'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_PPTK'
                            ? 'bg-amber-600 text-white font-semibold'
                            : 'bg-surface text-amber-700 hover:bg-amber-50 border border-amber-200'
                    ]"
                >
                    <span>Persetujuan PPTK</span>
                    <span v-if="countPptk > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-semibold">
                        {{ countPptk }}
                    </span>
                </button>
                <button
                    v-if="isKelurahan || countLurah > 0 || userRole === 'LURAH'"
                    @click="activeTab = 'MENUNGGU_LURAH'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_LURAH'
                            ? 'bg-accent text-white font-semibold'
                            : 'bg-surface text-accent hover:bg-accent-soft border border-accent-border'
                    ]"
                >
                    <span>Otorisasi Lurah</span>
                    <span v-if="countLurah > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-semibold">
                        {{ countLurah }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'MENUNGGU_VERIFIKASI'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_VERIFIKASI'
                            ? 'bg-semantic-info text-white font-semibold'
                            : 'bg-surface text-semantic-info hover:bg-sky-50 border border-sky-200'
                    ]"
                >
                    <span>Verifikasi Teknis</span>
                    <span v-if="countVerifikasi > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-semibold">
                        {{ countVerifikasi }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'REVISI_MINOR'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'REVISI_MINOR'
                            ? 'bg-semantic-danger text-white font-semibold'
                            : 'bg-surface text-semantic-danger hover:bg-rose-50 border border-rose-200'
                    ]"
                >
                    <span>Revisi / SLA</span>
                    <span v-if="countRevisi > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-red-50 text-semantic-danger font-semibold">
                        {{ countRevisi }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'LOLOS_VERIFIKASI'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'LOLOS_VERIFIKASI'
                            ? 'bg-semantic-success text-white font-semibold'
                            : 'bg-surface text-semantic-success hover:bg-emerald-50 border border-emerald-200'
                    ]"
                >
                    <span>Lolos Verifikasi</span>
                    <span v-if="countLolos > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-emerald-50 text-semantic-success font-semibold">
                        {{ countLolos }}
                    </span>
                </button>
            </div>

            <GovCard :no-padding="true">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-hairline text-left">
                        <thead class="bg-raised text-xs font-semibold text-ink-secondary">
                            <tr>
                                <th class="py-3 px-4">No. Bukti / Tanggal</th>
                                <th class="py-3 px-4">Unit Kerja &amp; Subkegiatan</th>
                                <th class="py-3 px-4">Uraian / Penyedia</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Aksi Pemeriksaan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline text-xs">
                            <tr v-if="loading">
                                <td colspan="7" class="py-8 text-center text-ink-muted">
                                    Memuat antrean berkas...
                                </td>
                            </tr>
                            <tr v-else-if="filteredQueue.length === 0">
                                <td colspan="7" class="py-8 text-center text-ink-muted">
                                    Tidak ada dokumen dalam kategori antrean ini.
                                </td>
                            </tr>
                            <tr v-for="item in filteredQueue" :key="item.id" class="hover:bg-raised/60">
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-ink font-mono">{{ item.id }}</div>
                                    <div class="text-xs text-ink-muted">{{ formatTanggalIndo(item.tanggal) }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-ink">{{ item.unit }}</div>
                                    <div class="text-xs text-ink-muted max-w-xs truncate">{{ item.sub_activity }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-ink max-w-xs truncate font-medium">{{ item.uraian }}</div>
                                    <div class="text-xs text-ink-muted font-mono">{{ item.vendor }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-raised text-ink border border-hairline">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-ink font-mono tabular-nums">
                                    {{ formatRupiah(item.nominal) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <GovBadge :variant="STATUS_VARIANTS[item.status]">
                                            {{ STATUS_LABELS[item.status] }}
                                        </GovBadge>

                                        <!-- Indikator SLA 2 Hari Kerja jika status REVISI_MINOR -->
                                        <template v-if="item.status === 'REVISI_MINOR'">
                                            <span
                                                v-if="item.is_sla_breached"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300"
                                                title="SLA Terlampaui (Lebih dari 2 hari kerja)"
                                            >
                                                <Clock class="w-3 h-3 text-semantic-danger" :stroke-width="1.5" />
                                                SLA Terlewat
                                            </span>
                                            <span
                                                v-else-if="item.sla_remaining_hours !== null && item.sla_remaining_hours !== undefined"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                                            >
                                                <Clock class="w-3 h-3 text-semantic-warning" :stroke-width="1.5" />
                                                Sisa {{ Math.max(0, Math.round(item.sla_remaining_hours)) }} jam
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <!-- Aksi Spesifik PPTK jika status MENUNGGU_PPTK -->
                                    <div v-if="item.status === 'MENUNGGU_PPTK'" class="flex items-center justify-center gap-1.5">
                                        <button
                                            v-if="userRole === 'PPTK'"
                                            @click="openAuthModal(item, 'PPTK', 'APPROVE')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-semantic-success text-white hover:bg-emerald-700 shadow-subtle"
                                            title="Setujui dan teruskan ke tahapan berikutnya"
                                        >
                                            <Check class="w-3.5 h-3.5" :stroke-width="1.5" />
                                            Setujui
                                        </button>
                                        <button
                                            v-if="userRole === 'PPTK'"
                                            @click="openAuthModal(item, 'PPTK', 'REJECT')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-semantic-danger text-white hover:bg-red-700 shadow-subtle"
                                            title="Tolak dan pulihkan pagu kas"
                                        >
                                            <X class="w-3.5 h-3.5" :stroke-width="1.5" />
                                            Tolak
                                        </button>
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-raised text-ink-secondary hover:bg-hairline"
                                            title="Periksa kelengkapan berkas"
                                        >
                                            Review
                                        </Link>
                                    </div>

                                    <!-- Aksi Spesifik LURAH jika status MENUNGGU_LURAH (historis) -->
                                    <div v-else-if="item.status === 'MENUNGGU_LURAH'" class="flex items-center justify-center gap-1.5">
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-raised text-ink-secondary hover:bg-hairline"
                                            title="Periksa kelengkapan berkas"
                                        >
                                            Review
                                        </Link>
                                    </div>

                                    <!-- Aksi Verifikator jika status sudah lewat persetujuan -->
                                    <div v-else class="flex items-center justify-center gap-1.5">
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-accent text-white hover:bg-accent-hover shadow-subtle"
                                        >
                                            <Eye class="w-3.5 h-3.5" :stroke-width="1.5" />
                                            Buka Workspace
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </GovCard>
        </div>
    </GovAppLayout>
</template>
