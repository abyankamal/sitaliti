<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { verificationService } from '@/Services/verificationService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';

const queue = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        queue.value = await verificationService.getQueue();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Antrean Verifikasi Kuitansi SPJ" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-bold text-gov-navy-950 flex items-center gap-2">
                        <span>Antrean Verifikasi Dokumen Kuitansi</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200">
                            Tim Verifikator & PPK
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Pemeriksaan bukti kepatuhan akuntabilitas sebelum dibundel menjadi SPP (GU/TU/LS)
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <GovCard :no-padding="true">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gov-surface-border text-left">
                        <thead class="bg-gov-surface-subtle text-[11px] uppercase tracking-wider font-semibold text-slate-600">
                            <tr>
                                <th class="py-3 px-4">No. Bukti / Tanggal</th>
                                <th class="py-3 px-4">Unit Kerja & Subkegiatan</th>
                                <th class="py-3 px-4">Uraian / Penyedia</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Aksi Pemeriksaan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gov-surface-border text-xs">
                            <tr v-for="item in queue" :key="item.id" class="hover:bg-slate-50">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gov-navy-900">{{ item.id }}</div>
                                    <div class="text-[11px] text-slate-400">{{ formatTanggalIndo(item.tanggal) }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800">{{ item.unit }}</div>
                                    <div class="text-[11px] text-slate-500 max-w-xs truncate">{{ item.sub_activity }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-800 max-w-xs truncate font-medium">{{ item.uraian }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ item.vendor }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-gov-navy-900 border border-slate-200">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gov-navy-950 tabular-nums">
                                    {{ formatRupiah(item.nominal) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <GovBadge :variant="STATUS_VARIANTS[item.status]">
                                        {{ STATUS_LABELS[item.status] }}
                                    </GovBadge>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <Link
                                        :href="route('verification.workspace', { id: item.id })"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gov-navy-900 text-white hover:bg-gov-navy-800 shadow-sm"
                                    >
                                        <svg class="w-3.5 h-3.5 text-gov-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Buka Workspace
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </GovCard>
        </div>
    </GovAppLayout>
</template>

