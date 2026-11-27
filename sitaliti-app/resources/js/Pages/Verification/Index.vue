<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clock, Check, X, Eye, AlertCircle, RefreshCw } from 'lucide-vue-next';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovSkeleton from '@/Components/UI/GovSkeleton.vue';
import { verificationService } from '@/Services/verificationService';
import { receiptService } from '@/Services/receiptService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { getStatusMeta } from '@/Constants/workflowStatus';
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
const errorMessage = ref('');
const activeTab = ref('ALL');

async function loadQueue() {
    loading.value = true;
    errorMessage.value = '';
    try {
        queue.value = await verificationService.getQueue();
    } catch (err) {
        errorMessage.value =
            err?.response?.data?.message || 'Gagal memuat antrean verifikasi dari server. Silakan coba lagi.';
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

const filterTabs = computed(() => {
    const items = [
        { key: 'ALL', label: 'Semua Berkas', count: queue.value.length },
        { key: 'MENUNGGU_PPTK', label: 'Persetujuan PPTK', count: countPptk.value },
    ];
    if (isKelurahan.value || countLurah.value > 0 || userRole.value === 'LURAH') {
        items.push({ key: 'MENUNGGU_LURAH', label: 'Otorisasi Lurah (Historis)', count: countLurah.value });
    }
    items.push(
        { key: 'MENUNGGU_VERIFIKASI', label: 'Verifikasi Teknis', count: countVerifikasi.value },
        { key: 'REVISI_MINOR', label: 'Revisi / SLA', count: countRevisi.value },
        { key: 'LOLOS_VERIFIKASI', label: 'Lolos Verifikasi', count: countLolos.value },
    );
    return items;
});

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
            <!-- Filter Tabs (Quiet Ledger Chrome + Single Active Accent) -->
            <div class="flex items-center gap-2 border-b border-hairline pb-3 overflow-x-auto scrollbar-thin text-xs font-medium">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    type="button"
                    @click="activeTab = tab.key"
                    class="min-h-[44px] sm:min-h-0 px-3.5 py-2 sm:py-1.5 rounded-lg border transition-colors duration-150 ease-unslop whitespace-nowrap inline-flex items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    :class="[
                        activeTab === tab.key
                            ? 'bg-accent text-white border-accent font-semibold shadow-subtle'
                            : 'bg-surface text-ink-secondary border-hairline hover:bg-raised hover:text-ink'
                    ]"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        class="px-1.5 py-0.5 rounded text-xs font-mono tabular-nums"
                        :class="activeTab === tab.key ? 'bg-white/15 text-white' : 'bg-raised text-ink-secondary'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Error State -->
            <div
                v-if="errorMessage"
                role="alert"
                class="bg-red-50 border border-red-200 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-start gap-3">
                    <AlertCircle class="w-5 h-5 text-semantic-danger shrink-0 mt-0.5" :stroke-width="1.5" />
                    <div>
                        <h2 class="text-sm font-semibold text-red-900">Antrean Verifikasi Gagal Dimuat</h2>
                        <p class="text-xs text-red-800 mt-1">{{ errorMessage }}</p>
                    </div>
                </div>
                <GovButton variant="secondary" size="sm" class="shrink-0 min-h-[44px] sm:min-h-0" @click="loadQueue">
                    <RefreshCw class="w-4 h-4" :stroke-width="1.5" />
                    Muat Ulang
                </GovButton>
            </div>

            <GovCard v-else :no-padding="true">
                <!-- Loading State -->
                <div v-if="loading" aria-busy="true" aria-label="Memuat antrean verifikasi">
                    <GovSkeleton variant="table-row" :rows="5" :columns="6" />
                </div>

                <!-- Empty State -->
                <div v-else-if="filteredQueue.length === 0" class="p-8 text-center space-y-3">
                    <p class="text-sm font-medium text-ink-secondary">
                        {{ queue.length === 0 ? 'Belum ada berkas dalam antrean verifikasi' : 'Tidak ada berkas pada kategori filter ini' }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        {{ queue.length === 0 ? 'Berkas akan muncul otomatis setelah kuitansi diajukan untuk pemeriksaan.' : 'Ubah tab filter untuk melihat berkas pada tahapan lainnya.' }}
                    </p>
                    <div v-if="activeTab !== 'ALL' && queue.length > 0" class="pt-1">
                        <GovButton variant="secondary" size="sm" @click="activeTab = 'ALL'">
                            Tampilkan Semua Berkas
                        </GovButton>
                    </div>
                </div>

                <template v-else>
                    <!-- Mobile Card List (< md) -->
                    <div class="md:hidden divide-y divide-hairline">
                        <div v-for="item in filteredQueue" :key="`m-${item.id}`" class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-mono font-semibold text-sm text-ink">{{ item.id }}</div>
                                    <div class="text-xs text-ink-muted tabular-nums">{{ formatTanggalIndo(item.tanggal) }}</div>
                                </div>
                                <GovBadge :variant="getStatusMeta(item.status).variant" size="sm">
                                    {{ getStatusMeta(item.status).label }}
                                </GovBadge>
                            </div>
                            <div class="text-xs space-y-1">
                                <div class="font-medium text-ink">{{ item.unit }}</div>
                                <div class="text-ink-secondary line-clamp-2">{{ item.uraian }}</div>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-hairline">
                                <span class="font-mono font-semibold text-sm text-ink tabular-nums">
                                    {{ formatRupiah(item.nominal) }}
                                </span>
                                <Link
                                    :href="route('verification.workspace', { id: item.kuitansi_id ?? item.id })"
                                    class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 rounded-lg text-xs font-semibold bg-accent text-white hover:bg-accent-hover focus-visible:ring-2 focus-visible:ring-accent"
                                >
                                    <Eye class="w-4 h-4" :stroke-width="1.5" />
                                    Buka Workspace
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table (>= md) -->
                    <div class="hidden md:block overflow-x-auto">
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
                                <tr v-for="item in filteredQueue" :key="item.id" class="hover:bg-raised/60 transition-colors duration-150 ease-unslop">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-ink font-mono">{{ item.id }}</div>
                                        <div class="text-xs text-ink-muted tabular-nums">{{ formatTanggalIndo(item.tanggal) }}</div>
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
                                            <GovBadge :variant="getStatusMeta(item.status).variant">
                                                {{ getStatusMeta(item.status).label }}
                                            </GovBadge>

                                            <!-- Indikator SLA 2 Hari Kerja jika status REVISI_MINOR -->
                                            <template v-if="item.status === 'REVISI_MINOR'">
                                                <GovBadge
                                                    v-if="item.is_sla_breached"
                                                    variant="danger"
                                                    size="sm"
                                                    title="SLA Terlampaui (Lebih dari 2 hari kerja)"
                                                >
                                                    <Clock class="w-3 h-3" :stroke-width="1.5" />
                                                    SLA Terlewat
                                                </GovBadge>
                                                <GovBadge
                                                    v-else-if="item.sla_remaining_hours !== null && item.sla_remaining_hours !== undefined"
                                                    variant="warning"
                                                    size="sm"
                                                >
                                                    <Clock class="w-3 h-3" :stroke-width="1.5" />
                                                    Sisa {{ Math.max(0, Math.round(item.sla_remaining_hours)) }} jam
                                                </GovBadge>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <!-- Aksi Spesifik PPTK jika status MENUNGGU_PPTK -->
                                        <div v-if="item.status === 'MENUNGGU_PPTK'" class="flex items-center justify-center gap-1.5">
                                            <button
                                                v-if="userRole === 'PPTK'"
                                                @click="openAuthModal(item, 'PPTK', 'APPROVE')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-semantic-success text-white hover:bg-emerald-800 shadow-subtle focus-visible:ring-2 focus-visible:ring-emerald-700"
                                                title="Setujui dan teruskan ke tahapan berikutnya"
                                            >
                                                <Check class="w-3.5 h-3.5" :stroke-width="1.5" />
                                                Setujui
                                            </button>
                                            <button
                                                v-if="userRole === 'PPTK'"
                                                @click="openAuthModal(item, 'PPTK', 'REJECT')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-semantic-danger text-white hover:bg-red-800 shadow-subtle focus-visible:ring-2 focus-visible:ring-red-700"
                                                title="Tolak dan pulihkan pagu kas"
                                            >
                                                <X class="w-3.5 h-3.5" :stroke-width="1.5" />
                                                Tolak
                                            </button>
                                            <Link
                                                :href="route('verification.workspace', { id: item.kuitansi_id ?? item.id })"
                                                class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-raised text-ink-secondary hover:bg-hairline focus-visible:ring-2 focus-visible:ring-accent"
                                                title="Periksa kelengkapan berkas"
                                            >
                                                Review
                                            </Link>
                                        </div>

                                        <!-- Aksi Spesifik LURAH jika status MENUNGGU_LURAH (historis) -->
                                        <div v-else-if="item.status === 'MENUNGGU_LURAH'" class="flex items-center justify-center gap-1.5">
                                            <Link
                                                :href="route('verification.workspace', { id: item.kuitansi_id ?? item.id })"
                                                class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-raised text-ink-secondary hover:bg-hairline focus-visible:ring-2 focus-visible:ring-accent"
                                                title="Periksa kelengkapan berkas"
                                            >
                                                Review
                                            </Link>
                                        </div>

                                        <!-- Aksi Verifikator jika status sudah lewat persetujuan -->
                                        <div v-else class="flex items-center justify-center gap-1.5">
                                            <Link
                                                :href="route('verification.workspace', { id: item.kuitansi_id ?? item.id })"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-accent text-white hover:bg-accent-hover shadow-subtle focus-visible:ring-2 focus-visible:ring-accent"
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
                </template>
            </GovCard>
        </div>
    </GovAppLayout>
</template>
