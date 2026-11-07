<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { settlementService } from '@/Services/settlementService';
import { verificationService } from '@/Services/verificationService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

const bundles = ref([]);
const loading = ref(true);
const showCreateModal = ref(false);
const verifiedReceipts = ref([]);
const loadingReceipts = ref(false);
const isSubmitting = ref(false);

const newBundleForm = ref({
    jenis_bundel: 'SPP_GU',
    tahun_anggaran: 2026,
    bulan_periode: 6,
    nomor_spp_sipd: '',
    selected_kuitansi_ids: []
});

onMounted(async () => {
    await loadBundles();
});

async function loadBundles() {
    loading.value = true;
    try {
        bundles.value = await settlementService.getBundles();
    } finally {
        loading.value = false;
    }
}

async function openCreateModal() {
    showCreateModal.value = true;
    loadingReceipts.value = true;
    try {
        const queue = await verificationService.getQueue();
        // Saring kuitansi yang sudah lolos verifikasi dan siap dibundel
        verifiedReceipts.value = (queue || []).filter(item => item.status === 'LOLOS_VERIFIKASI' || item.status === 'VERIFIED');
    } finally {
        loadingReceipts.value = false;
    }
}

async function handleCreateBundle() {
    if (newBundleForm.value.selected_kuitansi_ids.length === 0) {
        alert('Pilih minimal satu kuitansi lolos verifikasi untuk dibundel ke dalam SPP.');
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await settlementService.createBundle({
            jenis_bundel: newBundleForm.value.jenis_bundel,
            tahun_anggaran: newBundleForm.value.tahun_anggaran,
            bulan_periode: newBundleForm.value.bulan_periode,
            nomor_spp_sipd: newBundleForm.value.nomor_spp_sipd,
            kuitansi_ids: newBundleForm.value.selected_kuitansi_ids
        });
        alert(`Berhasil! Bundel ${res.message || 'SPP berhasil dibuat.'}`);
        showCreateModal.value = false;
        newBundleForm.value.selected_kuitansi_ids = [];
        await loadBundles();
    } catch (err) {
        alert(err.response?.data?.error?.message || err.message || 'Gagal membuat bundel SPP.');
    } finally {
        isSubmitting.value = false;
    }
}

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
                <GovButton variant="gold" size="sm" @click="openCreateModal">
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
                                {{ bundle.jenis || bundle.jenis_bundel }}
                            </span>
                            <h3 class="text-sm font-bold text-gov-navy-950">{{ bundle.nomor_spp || bundle.nomor_bundel }}</h3>
                            <GovBadge :variant="BUNDLE_STATUS_VARIANTS[bundle.status || bundle.status_bundel]">
                                {{ BUNDLE_STATUS_LABELS[bundle.status || bundle.status_bundel] }}
                            </GovBadge>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Unit: {{ bundle.unit || 'Kecamatan Tarogong Kidul' }} • Periode: {{ bundle.periode || 'Juni 2026' }}
                        </p>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-slate-500">Total Nominal Bundel</div>
                        <div class="text-lg font-black text-gov-navy-900 tabular-nums">
                            {{ formatRupiah(bundle.total_nominal || bundle.total_bruto) }}
                        </div>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sisi Kiri: 9 Dokumen Bagian A -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5">
                            Status 9 Dokumen Wajib Pengesahan SPJ (Bagian A)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="(status, docName) in (bundle.checklist_dokumen || {
                                    '1. Nota Pencairan Dana (NPD)': 'LENGKAP',
                                    '2. Lembar Pengantar SPJ': 'LENGKAP',
                                    '3. BAP Kas & Register Penutupan': 'LENGKAP',
                                    '4. Laporan Pengeluaran Kas': 'LENGKAP',
                                    '5. SPJ Fungsional': 'LENGKAP',
                                    '6. Buku Kas Umum (BKU)': 'LENGKAP',
                                    '7. Buku Pembantu Pajak': 'LENGKAP',
                                    '8. Rekening Koran Bank BJB': 'LENGKAP'
                                })"
                                :key="docName"
                                class="p-2 rounded border border-slate-200 bg-slate-50 flex items-center justify-between"
                            >
                                <span class="truncate pr-2 text-slate-700 text-[11px]">{{ docName }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono"
                                    :class="status === 'LENGKAP' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Kuitansi-Kuitansi yang Tergabung -->
                    <div>
                        <h4 class="text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                            <span>Daftar Kuitansi Tergabung (Tier 1)</span>
                            <span class="text-slate-500 text-[11px]">{{ (bundle.kuitansi_list || bundle.items || []).length }} Bukti Kuitansi</span>
                        </h4>
                        <div class="space-y-2">
                            <div
                                v-for="kw in (bundle.kuitansi_list || bundle.items || [])"
                                :key="kw.id"
                                class="p-2.5 rounded-lg border border-slate-200 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <div class="font-bold text-gov-navy-950 flex items-center gap-1.5">
                                        <span>{{ kw.id || kw.nomor_kuitansi }}</span>
                                        <span class="text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.2 rounded font-mono">
                                            {{ kw.kategori || 'B.1' }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate max-w-xs">{{ kw.uraian || kw.uraian_belanja }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-slate-800 tabular-nums">{{ formatRupiah(kw.nominal || kw.nominal_bruto) }}</div>
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

        <!-- Modal Buat Bundel SPP Baru -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm" @click="showCreateModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-gov-surface-border overflow-hidden">
                    <div class="bg-gov-navy-950 text-white px-6 py-4 flex items-center justify-between">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            Penyatuan Bundel SPP Baru (Two-Tier Model)
                        </h3>
                        <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">Jenis Pengajuan SPP *</label>
                                <select v-model="newBundleForm.jenis_bundel" class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3">
                                    <option value="SPP_GU">SPP-GU (Ganti Uang Persediaan)</option>
                                    <option value="SPP_TU">SPP-TU (Tambah Uang)</option>
                                    <option value="SPP_LS">SPP-LS (Langsung)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-gov-navy-900 mb-1">Nomor Referensi SPP SIPD-RI</label>
                                <input v-model="newBundleForm.nomor_spp_sipd" type="text" placeholder="Contoh: 0012/SPP-GU/TARKID/2026" class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gov-navy-900 mb-1.5 flex items-center justify-between">
                                <span>Pilih Kuitansi Lolos Verifikasi (Tier 1) *</span>
                                <span class="text-slate-500 font-normal">Hanya kuitansi yang sah bertanda Lolos</span>
                            </label>
                            
                            <div v-if="loadingReceipts" class="p-6 text-center text-slate-400">
                                Memuat kuitansi lolos verifikasi...
                            </div>
                            <div v-else-if="verifiedReceipts.length === 0" class="p-6 text-center text-slate-500 bg-slate-50 rounded-lg border border-slate-200">
                                Belum ada kuitansi baru berstatus LOLOS_VERIFIKASI untuk dibundel.
                            </div>
                            <div v-else class="max-h-60 overflow-y-auto space-y-2 border border-slate-200 rounded-lg p-2">
                                <label
                                    v-for="rec in verifiedReceipts"
                                    :key="rec.id"
                                    class="flex items-center justify-between p-2.5 rounded bg-slate-50 hover:bg-slate-100 cursor-pointer border border-slate-200"
                                >
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :value="rec.id"
                                            v-model="newBundleForm.selected_kuitansi_ids"
                                            class="rounded text-gov-navy-900 focus:ring-gov-navy-800"
                                        />
                                        <div>
                                            <div class="font-bold text-gov-navy-950">{{ rec.nomor_kuitansi || rec.id }}</div>
                                            <div class="text-[11px] text-slate-500">{{ rec.uraian_belanja || rec.uraian }}</div>
                                        </div>
                                    </div>
                                    <div class="font-bold text-gov-navy-900 tabular-nums">
                                        {{ formatRupiah(rec.nominal_bruto || rec.nominal) }}
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                        <GovButton variant="secondary" size="sm" @click="showCreateModal = false">Batal</GovButton>
                        <GovButton variant="gold" size="sm" :loading="isSubmitting" @click="handleCreateBundle">
                            Sahkan & Simpan Bundel
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>
    </GovAppLayout>
</template>
