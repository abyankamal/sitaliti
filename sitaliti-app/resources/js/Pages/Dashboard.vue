<script setup>
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';
import { ref, onMounted } from 'vue';
import { budgetService } from '@/Services/budgetService';
import { Head, router } from '@inertiajs/vue3';
import RAKSelectorModal from '@/Modules/Gatekeeper/RAKSelectorModal.vue';

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
                    <h1 class="text-xl font-bold text-gov-navy-950 flex items-center gap-2.5">
                        <span>Pusat Kendali Akuntabilitas SPJ</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200">
                            Pre-Audit Gateway
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ Kecamatan Tarogong Kidul
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <GovButton variant="secondary" size="sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Ekspor Laporan
                    </GovButton>
                    <GovButton variant="gold" size="sm" @click="showGatekeeperModal = true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Input Kuitansi Baru
                    </GovButton>
                </div>
            </div>
        </template>

        <!-- Main Content -->
        <div v-if="dashboardData" class="space-y-6">
            <!-- Alert Banner: Pre-Audit Clearing House Info -->
            <div class="bg-gradient-to-r from-gov-navy-900 to-gov-navy-800 text-white rounded-xl p-4 shadow-sm border border-gov-navy-700 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2 bg-gov-navy-950/60 rounded-lg text-gov-gold-400 border border-gov-gold-500/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gov-gold-300">Prinsip Kerja Pre-Audit SI-TALITI</h4>
                        <p class="text-xs text-slate-300 mt-0.5 leading-relaxed">
                            SI-TALITI melakukan verifikasi kepatuhan, keabsahan SSH/SBM, kelengkapan pajak, serta validasi pagu RAK. Sistem ini tidak menerbitkan SP2D dan tidak melakukan pencairan kas resmi (otoritas tetap pada SIPD-RI).
                        </p>
                    </div>
                </div>
                <div class="hidden lg:block text-right shrink-0">
                    <span class="text-[11px] text-slate-300 block">Status RAK Terkini</span>
                    <span class="text-xs font-semibold text-emerald-400">Terkontrol & Terkunci Real-Time</span>
                </div>
            </div>

            <!-- KPI Metrik Anggaran & SPJ (4 Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Total Pagu Anggaran -->
                <div class="bg-white rounded-xl border border-gov-surface-border p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Total Pagu (RAK-P)</span>
                        <div class="w-7 h-7 rounded-lg bg-gov-navy-50 text-gov-navy-900 flex items-center justify-center font-bold text-xs">
                            TA
                        </div>
                    </div>
                    <div class="text-lg font-bold text-gov-navy-950 tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_pagu) }}
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">6 Unit • 39 Subkegiatan • 132 Rekening</p>
                </div>

                <!-- Card 2: Realisasi Pagu -->
                <div class="bg-white rounded-xl border border-gov-surface-border p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Realisasi Penyerapan</span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                            {{ dashboardData.summary.persentase_realisasi }}%
                        </span>
                    </div>
                    <div class="text-lg font-bold text-emerald-700 tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_terserap) }}
                    </div>
                    <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                        <div
                            class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                            :style="{ width: `${dashboardData.summary.persentase_realisasi}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Card 3: Pagu Terkunci & Sisa -->
                <div class="bg-white rounded-xl border border-gov-surface-border p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Pagu Terkunci / Komitmen</span>
                        <span class="text-[11px] text-amber-700 font-medium">Dalam Proses</span>
                    </div>
                    <div class="text-lg font-bold text-amber-600 tabular-nums">
                        {{ formatRupiah(dashboardData.summary.total_terkunci) }}
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Sisa Pagu Bebas: <span class="font-semibold text-slate-700 tabular-nums">{{ formatRupiah(dashboardData.summary.total_sisa) }}</span>
                    </p>
                </div>

                <!-- Card 4: Antrean Verifikasi & PPTK -->
                <div class="bg-white rounded-xl border border-gov-surface-border p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Antrean Verifikasi</span>
                        <span class="flex h-2.5 w-2.5 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-sky-500"></span>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-black text-gov-navy-950 tabular-nums">
                            {{ dashboardData.summary.pending_verification_count }}
                        </span>
                        <span class="text-xs text-slate-500">berkas kuitansi</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5 text-[11px] flex-wrap">
                        <span v-if="dashboardData.queue_counts?.menunggu_pptk" class="text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded font-semibold border border-indigo-200">
                            {{ dashboardData.queue_counts.menunggu_pptk }} antre PPTK
                        </span>
                        <span v-if="dashboardData.summary.active_revisions_count" class="text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded font-semibold border border-amber-200">
                            {{ dashboardData.summary.active_revisions_count }} revisi
                        </span>
                    </div>
                </div>
            </div>

            <!-- Monitoring Grid: 12 Bulan RAK & Alokasi Unit -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom 1 & 2: Alokasi RAK 12 Bulan -->
                <div class="lg:col-span-2">
                    <GovCard
                        title="Distribusi RAK 12 Bulan (Tahun Anggaran 2026)"
                        subtitle="Prinsip Carry-Over: Sisa pagu bulan lalu dapat dibawa, pagu bulan depan terkunci"
                        :no-padding="true"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gov-surface-border text-left">
                                <thead class="bg-gov-surface-subtle text-[11px] uppercase tracking-wider font-semibold text-slate-600">
                                    <tr>
                                        <th class="py-3 px-4">Bulan</th>
                                        <th class="py-3 px-4 text-right">Target RAK</th>
                                        <th class="py-3 px-4 text-right">Realisasi</th>
                                        <th class="py-3 px-4 text-center">Status Pagu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gov-surface-border text-xs">
                                    <tr
                                        v-for="item in dashboardData.monthly_allocation"
                                        :key="item.month"
                                        :class="[
                                            item.status === 'active' ? 'bg-amber-50/60 font-medium' : 'hover:bg-slate-50/60'
                                        ]"
                                    >
                                        <td class="py-2.5 px-4 font-semibold text-gov-navy-900 flex items-center gap-2">
                                            <span
                                                v-if="item.status === 'active'"
                                                class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"
                                            ></span>
                                            <span>Bulan {{ item.month }} - {{ item.name }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right text-slate-600 tabular-nums">
                                            {{ formatRupiah(item.target) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-semibold tabular-nums" :class="item.realisasi > 0 ? 'text-gov-navy-950' : 'text-slate-400'">
                                            {{ formatRupiah(item.realisasi) }}
                                        </td>
                                        <td class="py-2.5 px-4 text-center">
                                            <span
                                                v-if="item.status === 'closed'"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700"
                                            >
                                                Selesai / Ditutup
                                            </span>
                                            <span
                                                v-else-if="item.status === 'active'"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200"
                                            >
                                                Bulan Berjalan (Open)
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-400"
                                            >
                                                Terkunci (Belum Terbuka)
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </GovCard>
                </div>

                <!-- Kolom 3: Pagu Per Unit Organisasi -->
                <div>
                    <GovCard
                        title="Pagu Unit Organisasi (6 Unit)"
                        subtitle="Realisasi penyerapan per unit kerja"
                    >
                        <div class="space-y-4">
                            <div
                                v-for="unit in dashboardData.units"
                                :key="unit.id"
                                class="p-3 rounded-lg border border-gov-surface-border bg-gov-surface-subtle hover:bg-slate-50 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <h5 class="text-xs font-bold text-gov-navy-900 leading-tight">
                                        {{ unit.name }}
                                    </h5>
                                    <span class="text-[10px] text-slate-400 font-mono shrink-0">
                                        {{ Math.round((unit.terserap / unit.pagu) * 100) }}%
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Terserap:</span>
                                    <span class="font-semibold text-slate-800 tabular-nums">{{ formatRupiah(unit.terserap) }}</span>
                                </div>
                                <div class="w-full bg-slate-200 h-1.5 rounded-full mt-1.5 overflow-hidden">
                                    <div
                                        class="bg-gov-navy-800 h-full rounded-full"
                                        :style="{ width: `${Math.round((unit.terserap / unit.pagu) * 100)}%` }"
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
                <div v-if="dashboardData.recent_activities?.length === 0" class="p-8 text-center text-slate-400 text-xs">
                    Belum ada aktivitas transaksi kuitansi yang tercatat.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gov-surface-border text-left">
                        <thead class="bg-gov-surface-subtle text-[11px] uppercase tracking-wider font-semibold text-slate-600">
                            <tr>
                                <th class="py-3 px-4">No. Bukti & Tanggal</th>
                                <th class="py-3 px-4">Unit Kerja & Kegiatan</th>
                                <th class="py-3 px-4">Uraian Belanja</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gov-surface-border text-xs">
                            <tr
                                v-for="act in dashboardData.recent_activities"
                                :key="act.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gov-navy-900">{{ act.id }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ act.timestamp || formatTanggalIndo(act.tanggal) }}
                                    </div>
                                    <div v-if="act.actor_name" class="text-[10px] text-slate-400 mt-0.5">
                                        Oleh: {{ act.actor_name }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800">{{ act.unit }}</div>
                                    <div class="text-[11px] text-slate-500 truncate max-w-xs">{{ act.kegiatan }}</div>
                                </td>
                                <td class="py-3 px-4 text-slate-700 max-w-sm">
                                    <p class="truncate">{{ act.uraian }}</p>
                                    <span v-if="act.action" class="inline-block text-[10px] text-slate-400 font-mono mt-0.5">
                                        Aksi: {{ act.action }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-gov-navy-900 border border-slate-200">
                                        {{ act.kategori }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gov-navy-950 tabular-nums">
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
