<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import bundlesData from '@/Mocks/bundlesMock.json';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

const bundles = ref([]);

onMounted(() => {
    bundles.value = bundlesData;
});

const BUNDLE_STATUS_VARIANTS = {
    'DRAFT': 'neutral',
    'MENUNGGU_PPK': 'warning',
    'DISETUJUI_PPK': 'success',
    'SYNCED_SIPD': 'primary',
};

const BUNDLE_STATUS_LABELS = {
    'DRAFT': 'Konsep Bundel',
    'MENUNGGU_PPK': 'Menunggu Pengesahan PPK',
    'DISETUJUI_PPK': 'Disetujui PPK',
    'SYNCED_SIPD': 'Terverifikasi SIPD-RI',
};
</script>

<template>
    <Head title="Bundel SPJ & Pengesahan SPP" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-bold text-gov-navy-950 flex items-center gap-2">
                        <span>Pengelompokan Bundel SPJ (Two-Tier Settlement)</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200">
                            Tier 2 Pengesahan
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Penyatuan kuitansi-kuitansi yang telah Lolos Verifikasi menjadi berkas bundel pengesahan SPP-GU / TU / LS
                    </p>
                </div>
                <GovButton variant="gold" size="sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Buat Bundel SPP Baru
                </GovButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Informasi Two-Tier Model -->
            <div class="p-4 bg-sky-50 border border-sky-200 rounded-xl text-sky-900 text-xs flex items-start gap-3">
                <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <strong>Prinsip Two-Tier Settlement Model:</strong> Kuitansi transaksi atomik (Tier 1) yang telah terbit Lembar Kendali Verifikasinya dihimpun bersama 9 Dokumen Wajib Bagian A (Tier 2: NPD, Pengantar SPJ, BAP Kas, Register Penutupan Kas, Lap. Pengeluaran, SPJ Fungsional, BKU, BP Pajak, Rekening Koran) sebelum diajukan sinkronisasi ke SIPD-RI.
                </div>
            </div>

            <!-- List Bundel Cards -->
            <div v-for="bundle in bundles" :key="bundle.id" class="bg-white rounded-xl border border-gov-surface-border shadow-sm overflow-hidden">
                <div class="bg-gov-surface-subtle p-5 border-b border-gov-surface-border flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-gov-navy-900 text-white font-mono">
                                {{ bundle.jenis }}
                            </span>
                            <h3 class="text-sm font-bold text-gov-navy-950">{{ bundle.nomor_spp }}</h3>
                            <GovBadge :variant="BUNDLE_STATUS_VARIANTS[bundle.status]">
                                {{ BUNDLE_STATUS_LABELS[bundle.status] }}
                            </GovBadge>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ bundle.unit }} • Tanggal: {{ formatTanggalIndo(bundle.tanggal) }} • Ref SIPD: <span class="font-mono text-gov-navy-900 font-semibold">{{ bundle.nomor_sipd }}</span>
                        </p>
                    </div>

                    <div class="md:text-right">
                        <span class="text-[11px] text-slate-400 block">Total Nilai Pengesahan Bundel</span>
                        <div class="text-lg font-black text-gov-navy-950 tabular-nums">
                            {{ formatRupiah(bundle.total_nominal) }}
                        </div>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Sisi Kiri: 9 Dokumen Umum Bagian A -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                            <span>Checklist Bagian A (9 Dokumen Wajib)</span>
                            <span class="text-emerald-700 text-[11px]">9/9 Lengkap</span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="docA in bundle.checklist_bagian_a"
                                :key="docA.id"
                                class="p-2 rounded bg-slate-50 border border-slate-200 flex items-center gap-2"
                            >
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span class="text-slate-700 truncate" :title="docA.name">{{ docA.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Kuitansi-Kuitansi yang Tergabung -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                            <span>Daftar Kuitansi Tergabung (Tier 1)</span>
                            <span class="text-slate-500 text-[11px]">{{ bundle.kuitansi_list.length }} Bukti Kuitansi</span>
                        </h4>
                        <div class="space-y-2">
                            <div
                                v-for="kw in bundle.kuitansi_list"
                                :key="kw.id"
                                class="p-2.5 rounded-lg border border-slate-200 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <div class="font-bold text-gov-navy-950 flex items-center gap-1.5">
                                        <span>{{ kw.id }}</span>
                                        <span class="text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.2 rounded font-mono">
                                            {{ kw.kategori }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate max-w-xs">{{ kw.uraian }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-slate-800 tabular-nums">{{ formatRupiah(kw.nominal) }}</div>
                                    <span class="text-[10px] text-emerald-700 font-medium">Lolos Verifikasi</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 bg-slate-50 border-t border-gov-surface-border flex items-center justify-between text-xs">
                    <span class="text-slate-500 text-[11px]">
                        Status Pengesahan: Siap Diajukan ke Bendahara Umum Daerah / SIPD-RI
                    </span>
                    <div class="flex items-center gap-2">
                        <GovButton variant="secondary" size="sm">Detail Berkas</GovButton>
                        <GovButton variant="primary" size="sm">Kirim ke PPK</GovButton>
                    </div>
                </div>
            </div>
        </div>
    </GovAppLayout>
</template>

