<script setup>
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovSkeleton from '@/Components/UI/GovSkeleton.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';
import { ref, computed, onMounted } from 'vue';
import { Download, Plus, Info } from 'lucide-vue-next';
import { budgetService } from '@/Services/budgetService';
import { receiptService } from '@/Services/receiptService';
import { Head, router, usePage } from '@inertiajs/vue3';
import RAKSelectorModal from '@/Modules/Gatekeeper/RAKSelectorModal.vue';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isKelurahan = computed(() => currentUser.value?.is_kelurahan_scoped);
const canCreateReceipt = computed(() => ['BPP', 'BP', 'VERIFIKATOR'].includes(currentUser.value?.role));

const loading = ref(true);
const dashboardData = ref(null);
const showGatekeeperModal = ref(false);

onMounted(async () => {
    try {
        dashboardData.value = await budgetService.getDashboardSummary();
    } finally {
        loading.value = false;
    }
});

function handleGatekeeperSelected(context) {
    showGatekeeperModal.value = false;
    // Simpan konteks anggaran terpilih di sessionStorage untuk pengisian form kuitansi
    sessionStorage.setItem('sitaliti_budget_context', JSON.stringify(context));
    // Navigasi ke halaman form input kuitansi
    router.visit(route('receipts.create'));
}
</script>

<template>
    <Head title="Dashboard Tata Kelola & Asistensi SPJ" />

    <GovAppLayout>
        <!-- Top Page Header -->
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-ink tracking-tight">
                        Pusat Kendali Akuntabilitas SPJ
                    </h1>
                    <p class="text-xs text-ink-muted mt-1">
                        {{ isKelurahan && currentUser?.unit_kerja ? `Sistem Informasi Tata Kelola dan Asistensi SPJ · ${currentUser.unit_kerja.nama_unit}` : 'Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ Kecamatan Tarogong Kidul' }}
                    </p>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <GovButton variant="secondary" size="sm" @click="receiptService.exportReceipts()">
                        <Download class="w-4 h-4" :stroke-width="1.5" />
                        Ekspor Laporan
                    </GovButton>
                    <GovButton v-if="canCreateReceipt" variant="primary" size="sm" @click="showGatekeeperModal = true">
                        <Plus class="w-4 h-4" :stroke-width="1.5" />
                        Input Kuitansi Baru
                    </GovButton>
                </div>
            </div>
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="space-y-6" aria-busy="true" aria-label="Memuat ringkasan anggaran">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <GovSkeleton v-for="i in 4" :key="i" variant="card" />
            </div>
            <div class="bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden">
                <GovSkeleton variant="table-row" :rows="5" :columns="4" />
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="dashboardData" class="space-y-6">
            <!-- Contextual Note: Pre-Audit Clearing House Info -->
            <div class="bg-surface rounded-xl px-4 py-3.5 border border-hairline shadow-subtle flex items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <Info class="w-4 h-4 text-accent mt-0.5 shrink-0" :stroke-width="1.5" />
                    <p class="text-xs text-ink-secondary leading-relaxed">
                        <span class="font-medium text-ink">Prinsip Pre-Audit:</span>
                        E - Verifikatur Kecamatan Tarogong Kidul memverifikasi kepatuhan, keabsahan SSH/SBM, kelengkapan pajak, dan validasi pagu RAK. Penerbitan SP2D serta pencairan kas tetap menjadi otoritas SIPD-RI.
                    </p>
                </div>
                <div class="hidden lg:flex items-center gap-2 shrink-0 text-xs text-ink-secondary">
                    <span class="w-1.5 h-1.5 rounded-[50%] bg-semantic-success"></span>
                    <span class="font-medium">RAK Terkunci Real-Time</span>
                </div>
            </div>

            <!-- KPI Metrik Anggaran & SPJ (4 Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Total Pagu Anggaran -->
                <div class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle">
                    <div class="text-xs font-medium text-ink-muted">
                        Total Pagu (RAK-P)
                    </div>
                    <div class="mt-2 text-xl font-medium font-mono text-ink tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_pagu) }}
                    </div>
                    <p class="text-xs text-ink-muted mt-2 tabular-nums">
                        {{ dashboardData.meta?.unit_count || 1 }} unit · {{ dashboardData.meta?.sub_activity_count || dashboardData.meta?.total_sub_activities || 0 }} subkegiatan · {{ dashboardData.meta?.rekening_count || dashboardData.meta?.total_accounts || 0 }} rekening
                    </p>
                </div>

                <!-- Card 2: Realisasi Pagu -->
                <div class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-ink-muted">Realisasi Penyerapan</span>
                        <span class="text-xs font-mono font-medium text-semantic-success tabular-nums">
                            {{ dashboardData.summary.persentase_realisasi }}%
                        </span>
                    </div>
                    <div class="mt-2 text-xl font-medium font-mono text-ink tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_terserap) }}
                    </div>
                    <p class="text-xs text-ink-muted mt-2">
                        Terhadap total pagu RAK-P berjalan
                    </p>
                </div>

                <!-- Card 3: Pagu Terkunci & Sisa -->
                <div class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-ink-muted">Pagu Terkunci / Komitmen</span>
                        <span class="text-xs font-medium text-semantic-warning">Dalam proses</span>
                    </div>
                    <div class="mt-2 text-xl font-medium font-mono text-ink tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_terkunci) }}
                    </div>
                    <p class="text-xs text-ink-muted mt-2">
                        Sisa bebas: <span class="font-mono font-medium text-ink-secondary tabular-nums">{{ formatRupiah(dashboardData.summary.total_sisa) }}</span>
                    </p>
                </div>

                <!-- Card 4: Antrean Verifikasi & PPTK -->
                <div class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-ink-muted">Antrean Verifikasi</span>
                        <span class="w-2 h-2 rounded-[50%] bg-accent"></span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-medium font-mono text-ink tabular-nums">
                            {{ dashboardData.summary.pending_verification_count }}
                        </span>
                        <span class="text-xs text-ink-muted">berkas kuitansi</span>
                    </div>
                    <p class="text-xs text-ink-muted mt-2 tabular-nums">
                        <template v-if="dashboardData.queue_counts?.menunggu_pptk || dashboardData.queue_counts?.menunggu_lurah || dashboardData.summary.active_revisions_count">
                            <span v-if="dashboardData.queue_counts?.menunggu_pptk">{{ dashboardData.queue_counts.menunggu_pptk }} PPTK</span>
                            <span v-if="dashboardData.queue_counts?.menunggu_pptk && (dashboardData.queue_counts?.menunggu_lurah || dashboardData.summary.active_revisions_count)"> · </span>
                            <span v-if="dashboardData.queue_counts?.menunggu_lurah">{{ dashboardData.queue_counts.menunggu_lurah }} Lurah</span>
                            <span v-if="dashboardData.queue_counts?.menunggu_lurah && dashboardData.summary.active_revisions_count"> · </span>
                            <span v-if="dashboardData.summary.active_revisions_count" class="text-semantic-warning">{{ dashboardData.summary.active_revisions_count }} revisi</span>
                        </template>
                        <template v-else>
                            Tidak ada antrean tertunda
                        </template>
                    </p>
                </div>
            </div>

            <!-- Monitoring Grid: 12 Bulan RAK & Alokasi Unit -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom 1 & 2: Alokasi RAK 12 Bulan -->
                <div class="lg:col-span-2">
                    <GovCard
                        title="Distribusi RAK 12 Bulan (Tahun Anggaran 2026)"
                        subtitle="Prinsip carry-over: sisa pagu bulan lalu dapat dibawa, pagu bulan depan terkunci"
                        :no-padding="true"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-hairline text-left">
                                <thead class="bg-canvas text-xs font-medium text-ink-secondary">
                                    <tr>
                                        <th class="py-3 px-4">Bulan</th>
                                        <th class="py-3 px-4 text-right">Target RAK</th>
                                        <th class="py-3 px-4 text-right">Realisasi</th>
                                        <th class="py-3 px-4 text-center">Status Pagu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline text-xs">
                                    <tr
                                        v-for="item in dashboardData.monthly_allocation"
                                        :key="item.month"
                                        :class="[
                                            item.status === 'active' ? 'bg-accent-soft/70 font-medium' : 'hover:bg-raised/60'
                                        ]"
                                    >
                                        <td class="py-2.5 px-4 font-medium text-ink flex items-center gap-2">
                                            <span
                                                v-if="item.status === 'active'"
                                                class="w-1.5 h-1.5 rounded-[50%] bg-accent shrink-0"
                                            ></span>
                                            <span>Bulan {{ item.month }} - {{ item.name }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-ink-secondary font-mono tabular-nums">
                                            {{ formatRupiah(item.target) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-medium tabular-nums" :class="item.realisasi > 0 ? 'text-ink' : 'text-ink-muted'">
                                            {{ formatRupiah(item.realisasi) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-center">
                                            <GovBadge v-if="item.status === 'closed'" variant="neutral" size="sm">
                                                Selesai / Ditutup
                                            </GovBadge>
                                            <GovBadge v-else-if="item.status === 'active'" variant="primary" size="sm">
                                                Bulan Berjalan
                                            </GovBadge>
                                            <GovBadge v-else variant="neutral" size="sm">
                                                Terkunci
                                            </GovBadge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </GovCard>
                </div>

                <!-- Kolom 3: Pagu Per Unit Organisasi ATAU Breakdown Subkegiatan Kelurahan -->
                <div>
                    <GovCard
                        v-if="isKelurahan && dashboardData.sub_activities?.length"
                        :title="`Pagu Subkegiatan ${currentUser?.unit_kerja?.nama_unit || 'Kelurahan'}`"
                        subtitle="Realisasi Sarpras & Pemberdayaan Masyarakat"
                    >
                        <div class="space-y-3">
                            <div
                                v-for="sub in dashboardData.sub_activities"
                                :key="sub.code"
                                class="p-3.5 rounded-lg border border-hairline bg-canvas hover:bg-raised/60 transition-colors duration-150 ease-unslop"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-xs font-mono text-ink-secondary bg-surface px-1.5 py-0.5 rounded border border-hairline inline-block mb-1.5">
                                            {{ sub.code }}
                                        </span>
                                        <h5 class="text-xs font-medium text-ink leading-snug">
                                            {{ sub.name }}
                                        </h5>
                                    </div>
                                    <span class="text-xs text-ink-secondary font-mono shrink-0 font-medium tabular-nums">
                                        {{ sub.pagu > 0 ? Math.round((sub.terserap / sub.pagu) * 100) : 0 }}%
                                    </span>
                                </div>
                                <div class="mt-2.5 flex items-center justify-between text-xs">
                                    <span class="text-ink-muted">Terserap</span>
                                    <span class="font-mono font-medium text-ink tabular-nums">{{ formatRupiah(sub.terserap) }}</span>
                                </div>
                                <div class="w-full bg-hairline h-1.5 rounded-sm mt-1.5 overflow-hidden">
                                    <div
                                        class="bg-accent h-full rounded-sm transition-[width] duration-300 ease-unslop"
                                        :style="{ width: `${sub.pagu > 0 ? Math.min(100, Math.round((sub.terserap / sub.pagu) * 100)) : 0}%` }"
                                    ></div>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs text-ink-muted font-mono tabular-nums">
                                    <span>Pagu: {{ formatRupiah(sub.pagu) }}</span>
                                    <span>Sisa: {{ formatRupiah(sub.sisa) }}</span>
                                </div>
                            </div>
                        </div>
                    </GovCard>

                    <GovCard
                        v-else
                        title="Pagu Unit Organisasi (6 Unit)"
                        subtitle="Realisasi penyerapan per unit kerja"
                    >
                        <div class="space-y-3">
                            <div
                                v-for="unit in dashboardData.units"
                                :key="unit.id"
                                class="p-3.5 rounded-lg border border-hairline bg-canvas hover:bg-raised/60 transition-colors duration-150 ease-unslop"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <h5 class="text-xs font-medium text-ink leading-snug">
                                        {{ unit.name }}
                                    </h5>
                                    <span class="text-xs text-ink-secondary font-mono shrink-0 tabular-nums">
                                        {{ unit.pagu > 0 ? Math.round((unit.terserap / unit.pagu) * 100) : 0 }}%
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="text-ink-muted">Terserap</span>
                                    <span class="font-mono font-medium text-ink tabular-nums">{{ formatRupiah(unit.terserap) }}</span>
                                </div>
                                <div class="w-full bg-hairline h-1.5 rounded-sm mt-1.5 overflow-hidden">
                                    <div
                                        class="bg-accent h-full rounded-sm transition-[width] duration-300 ease-unslop"
                                        :style="{ width: `${unit.pagu > 0 ? Math.min(100, Math.round((unit.terserap / unit.pagu) * 100)) : 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </GovCard>
                </div>
            </div>

            <!-- Aktivitas SPJ & Kuitansi Terakhir -->
            <GovCard
                title="Aktivitas Kuitansi & Status Verifikasi Terakhir"
                subtitle="Monitoring alur transaksi sebelum dibundel menjadi SPP"
                :no-padding="true"
            >
                <div v-if="dashboardData.recent_activities?.length === 0" class="p-8 text-center text-ink-muted text-xs">
                    Belum ada aktivitas transaksi kuitansi yang tercatat.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-hairline text-left">
                        <thead class="bg-canvas text-xs font-medium text-ink-secondary">
                            <tr>
                                <th class="py-3 px-4">No. Bukti & Tanggal</th>
                                <th class="py-3 px-4">Unit Kerja & Kegiatan</th>
                                <th class="py-3 px-4">Uraian Belanja</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline text-xs">
                            <tr
                                v-for="act in dashboardData.recent_activities"
                                :key="act.id"
                                class="hover:bg-raised/60 transition-colors duration-150 ease-unslop"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-mono font-medium text-ink">{{ act.id }}</div>
                                    <div class="text-xs text-ink-muted tabular-nums mt-0.5">
                                        {{ act.timestamp || formatTanggalIndo(act.tanggal) }}
                                    </div>
                                    <div v-if="act.actor_name" class="text-xs text-ink-muted mt-0.5">
                                        Oleh: {{ act.actor_name }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-ink">{{ act.unit }}</div>
                                    <div class="text-xs text-ink-secondary truncate max-w-xs mt-0.5">{{ act.kegiatan }}</div>
                                </td>
                                <td class="py-3 px-4 text-ink-secondary max-w-sm">
                                    <p class="truncate">{{ act.uraian }}</p>
                                    <span v-if="act.action" class="inline-block text-xs text-ink-muted font-mono mt-0.5">
                                        Aksi: {{ act.action }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-md text-xs font-mono font-medium bg-raised text-ink-secondary border border-hairline">
                                        {{ act.kategori }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-medium text-ink tabular-nums whitespace-nowrap">
                                    {{ formatRupiah(act.nominal) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <GovBadge :variant="STATUS_VARIANTS[act.status] || 'neutral'">
                                        {{ STATUS_LABELS[act.status] || act.status }}
                                    </GovBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </GovCard>
        </div>

        <!-- Pre-Entry Gatekeeper Modal -->
        <RAKSelectorModal
            :show="showGatekeeperModal"
            @close="showGatekeeperModal = false"
            @select="handleGatekeeperSelected"
        />
    </GovAppLayout>
</template>
