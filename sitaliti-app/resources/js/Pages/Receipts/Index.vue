<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import ReceiptDetailDrawer from '@/Pages/Receipts/Partials/ReceiptDetailDrawer.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo, NAMA_BULAN } from '@/Utils/date';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isKelurahan = computed(() => currentUser.value?.is_kelurahan_scoped);
const canCreate = computed(() => currentUser.value?.role === 'OPERATOR');

const props = defineProps({
    receipts: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    tabCounts: {
        type: Object,
        default: () => ({
            semua: 0,
            draft: 0,
            menunggu_pptk: 0,
            menunggu_lurah: 0,
            menunggu_verifikasi: 0,
            revisi_minor: 0,
            siap_bundel: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    unitKerjaList: {
        type: Array,
        default: () => [],
    },
});

// State Filter
const initialStatus = props.filters.status || '';
const activeTab = ref(initialStatus === 'TERVERIFIKASI' ? 'LOLOS_VERIFIKASI' : initialStatus);
const searchQuery = ref(props.filters.search || '');
const selectedUnit = ref(props.filters.unit_kerja_id || '');
const selectedKategori = ref(props.filters.kategori_belanja || '');
const selectedBulan = ref(props.filters.bulan_anggaran || '');

// Konfigurasi Tab Status
const tabs = computed(() => {
    const list = [
        { key: '', label: 'Semua Kuitansi', count: props.tabCounts.semua || 0 },
        { key: 'DRAFT', label: 'Draft', count: props.tabCounts.draft || 0 },
        { key: 'MENUNGGU_PPTK', label: 'Menunggu PPTK', count: props.tabCounts.menunggu_pptk || 0 },
    ];

    if (props.tabCounts.menunggu_lurah !== undefined) {
        list.push({ key: 'MENUNGGU_LURAH', label: 'Menunggu Lurah', count: props.tabCounts.menunggu_lurah || 0 });
    }

    list.push(
        { key: 'MENUNGGU_VERIFIKASI', label: 'Menunggu Verifikasi', count: props.tabCounts.menunggu_verifikasi || 0 },
        { key: 'REVISI_MINOR', label: 'Perlu Revisi (SLA)', count: props.tabCounts.revisi_minor || 0, isAlert: (props.tabCounts.revisi_minor || 0) > 0 },
        { key: 'LOLOS_VERIFIKASI', label: 'Lolos / Siap Bundel', count: props.tabCounts.siap_bundel || 0 },
    );

    return list;
});

// Opsi Kategori Belanja B.1 s.d. B.6
const kategoriOptions = [
    { value: '', label: 'Semua Kategori Belanja' },
    { value: 'B.1', label: 'B.1 Belanja Alat Tulis Kantor (ATK)' },
    { value: 'B.2', label: 'B.2 Belanja Makanan & Minuman' },
    { value: 'B.3', label: 'B.3 Honorarium ASN / Narasumber' },
    { value: 'B.4', label: 'B.4 Belanja Perjalanan Dinas' },
    { value: 'B.5', label: 'B.5 Belanja Pemeliharaan & Jasa' },
    { value: 'B.6', label: 'B.6 Belanja Modal / Aset' },
];

// Eksekusi Partial Reload via Inertia
let searchDebounceTimeout = null;

function applyFilter() {
    router.get(route('receipts.index'), {
        status: activeTab.value || undefined,
        unit_kerja_id: selectedUnit.value || undefined,
        kategori_belanja: selectedKategori.value || undefined,
        bulan_anggaran: selectedBulan.value || undefined,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['receipts', 'tabCounts'],
    });
}

function handleTabChange(tabKey) {
    activeTab.value = tabKey;
    applyFilter();
}

function handleSearchInput() {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        applyFilter();
    }, 400);
}

function resetFilter() {
    activeTab.value = '';
    searchQuery.value = '';
    selectedUnit.value = isKelurahan.value ? (currentUser.value?.unit_kerja_id || '') : '';
    selectedKategori.value = '';
    selectedBulan.value = '';
    applyFilter();
}

// URL Ekspor Rekapitulasi Data
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (activeTab.value) params.append('status', activeTab.value);
    if (selectedUnit.value) params.append('unit_kerja_id', selectedUnit.value);
    if (selectedKategori.value) params.append('kategori_belanja', selectedKategori.value);
    if (selectedBulan.value) params.append('bulan_anggaran', selectedBulan.value);
    if (searchQuery.value) params.append('search', searchQuery.value);

    const qs = params.toString();
    return `/api/v1/receipts/export${qs ? '?' + qs : ''}`;
});

function handleExport() {
    window.location.href = exportUrl.value;
}

// Mapping status badge variant
function getStatusBadge(status) {
    switch (status) {
        case 'DRAFT':
            return { variant: 'neutral', label: 'Draft' };
        case 'MENUNGGU_PPTK':
            return { variant: 'info', label: 'Menunggu PPTK' };
        case 'MENUNGGU_LURAH':
            return { variant: 'warning', label: 'Menunggu Lurah' };
        case 'MENUNGGU_VERIFIKASI':
            return { variant: 'primary', label: 'Menunggu Verifikasi' };
        case 'REVISI_MINOR':
            return { variant: 'warning', label: 'Revisi Minor' };
        case 'REVISI_MAYOR':
            return { variant: 'danger', label: 'Revisi Mayor' };
        case 'TERVERIFIKASI':
        case 'LOLOS_VERIFIKASI':
            return { variant: 'success', label: 'Lolos Verifikasi' };
        case 'DIBUNDEL':
            return { variant: 'info', label: 'Dibundel (SPP)' };
        case 'SELESAI_SP2D':
        case 'SYNCED_SIPD':
            return { variant: 'success', label: 'Cair (SIPD-RI)' };
        default:
            return { variant: 'neutral', label: status };
    }
}

// State Drawer (disiapkan untuk Task 15.3)
const selectedReceiptId = ref(null);
const isDrawerOpen = ref(false);

function openReceiptDetail(receipt) {
    selectedReceiptId.value = receipt.id;
    isDrawerOpen.value = true;
}
</script>

<template>
    <Head title="Daftar & Manajemen Kuitansi" />

    <GovAppLayout title="Daftar & Manajemen Kuitansi">
        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- Header Halaman & Aksi Cepat -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gov-navy-950 tracking-tight">
                        Daftar & Manajemen Kuitansi
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Monitoring pre-audit akuntabilitas belanja daerah se-Kecamatan Tarogong Kidul sebelum pembundelan SPJ.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <GovButton
                        @click="handleExport"
                        variant="secondary"
                        class="flex items-center gap-2 border-emerald-600/30 text-emerald-800 hover:bg-emerald-50 shadow-sm"
                        title="Unduh rekapitulasi data kuitansi dan perpajakan ke format spreadsheet CSV"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Ekspor Rekapitulasi (.csv)
                    </GovButton>

                    <Link v-if="canCreate" :href="route('receipts.create')">
                        <GovButton variant="primary" class="flex items-center gap-2 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Input Kuitansi Baru
                        </GovButton>
                    </Link>
                </div>
            </div>

            <!-- Tab Filter Status -->
            <div class="border-b border-slate-200">
                <nav class="flex space-x-2 overflow-x-auto pb-px" aria-label="Tabs Status Kuitansi">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="handleTabChange(tab.key)"
                        class="whitespace-nowrap py-3 px-4 rounded-t-lg font-medium text-xs sm:text-sm transition-all flex items-center gap-2 border-b-2"
                        :class="[
                            activeTab === tab.key
                                ? 'border-gov-navy-900 text-gov-navy-950 font-bold bg-white shadow-xs'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums"
                            :class="[
                                tab.isAlert
                                    ? 'bg-rose-100 text-rose-700 border border-rose-200'
                                    : (activeTab === tab.key ? 'bg-gov-navy-100 text-gov-navy-900' : 'bg-slate-100 text-slate-600')
                            ]"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Filter Bar Multi-Dimensi -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Search Input -->
                    <div class="lg:col-span-2 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            @input="handleSearchInput"
                            type="text"
                            placeholder="Cari nomor bukti, uraian, rekanan..."
                            class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gov-navy-600/20 focus:border-gov-navy-700 placeholder-slate-400"
                        />
                    </div>

                    <!-- Dropdown Unit Kerja -->
                    <div>
                        <select
                            v-model="selectedUnit"
                            @change="applyFilter"
                            :disabled="isKelurahan"
                            class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gov-navy-600/20 focus:border-gov-navy-700 bg-white disabled:bg-slate-100 disabled:text-slate-500"
                        >
                            <option v-if="!isKelurahan" value="">Semua Unit Kerja</option>
                            <option v-for="unit in unitKerjaList" :key="unit.id" :value="unit.id">
                                {{ unit.nama_unit }}
                            </option>
                        </select>
                    </div>

                    <!-- Dropdown Kategori Belanja B.1 s.d. B.6 -->
                    <div>
                        <select
                            v-model="selectedKategori"
                            @change="applyFilter"
                            class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gov-navy-600/20 focus:border-gov-navy-700 bg-white"
                        >
                            <option v-for="opt in kategoriOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Dropdown Bulan Kas RAK -->
                    <div>
                        <select
                            v-model="selectedBulan"
                            @change="applyFilter"
                            class="w-full py-2 px-3 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gov-navy-600/20 focus:border-gov-navy-700 bg-white"
                        >
                            <option value="">Semua Bulan RAK</option>
                            <option v-for="(nama, idx) in NAMA_BULAN" :key="idx + 1" :value="idx + 1">
                                Bulan {{ idx + 1 }} ({{ nama }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Tombol Reset Filter jika ada parameter aktif -->
                <div v-if="selectedUnit || selectedKategori || selectedBulan || searchQuery || activeTab" class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100">
                    <span class="text-slate-500">
                        Menampilkan hasil filter kuitansi belanja.
                    </span>
                    <button
                        @click="resetFilter"
                        class="text-gov-navy-700 hover:text-gov-navy-950 font-medium underline flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset Semua Filter
                    </button>
                </div>
            </div>

            <!-- Tabel Data Kuitansi -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-gov-navy-950 text-white font-semibold text-xs tracking-wider uppercase">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 sm:pl-6">No. Bukti / Tanggal</th>
                                <th scope="col" class="px-3 py-3.5">Unit & Rekening Anggaran</th>
                                <th scope="col" class="px-3 py-3.5">Uraian & Rekanan</th>
                                <th scope="col" class="px-3 py-3.5 text-right">Bruto (Rp)</th>
                                <th scope="col" class="px-3 py-3.5 text-right">Pajak (Rp)</th>
                                <th scope="col" class="px-3 py-3.5 text-right">Neto (Rp)</th>
                                <th scope="col" class="px-3 py-3.5 text-center">Status</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr
                                v-for="receipt in receipts.data"
                                :key="receipt.id"
                                @click="openReceiptDetail(receipt)"
                                class="hover:bg-slate-50/80 transition-colors cursor-pointer group"
                            >
                                <!-- No. Bukti & Tanggal -->
                                <td class="whitespace-nowrap py-3.5 pl-4 pr-3 sm:pl-6">
                                    <div class="font-semibold text-gov-navy-900 group-hover:text-gov-navy-700">
                                        {{ receipt.nomor_kuitansi }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        {{ formatTanggalIndo(receipt.tanggal_transaksi) }}
                                    </div>
                                    <div v-if="receipt.rekening?.kategori_belanja" class="mt-1">
                                        <span class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ receipt.rekening.kategori_belanja }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Unit & Rekening -->
                                <td class="px-3 py-3.5 max-w-xs">
                                    <div class="text-xs font-semibold text-slate-900 truncate" :title="receipt.rekening?.unit_kerja?.nama_unit">
                                        {{ receipt.rekening?.unit_kerja?.nama_unit || '-' }}
                                    </div>
                                    <div class="text-xs text-slate-600 truncate mt-0.5 font-mono" :title="receipt.rekening?.kode_rekening">
                                        {{ receipt.rekening?.kode_rekening || '-' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 truncate" :title="receipt.rekening?.nama_rekening">
                                        {{ receipt.rekening?.nama_rekening || '-' }}
                                    </div>
                                </td>

                                <!-- Uraian & Rekanan -->
                                <td class="px-3 py-3.5 max-w-sm">
                                    <div class="text-xs text-slate-900 font-medium line-clamp-2" :title="receipt.uraian">
                                        {{ receipt.uraian }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span class="truncate font-medium text-slate-700">{{ receipt.nama_penerima || '-' }}</span>
                                    </div>
                                </td>

                                <!-- Nilai Bruto -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-right font-medium text-slate-900 tabular-nums">
                                    {{ formatRupiah(receipt.nominal_bruto) }}
                                </td>

                                <!-- Total Pajak -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-right text-rose-600 font-medium tabular-nums">
                                    {{ formatRupiah(receipt.total_pajak) }}
                                </td>

                                <!-- Nilai Neto -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-right font-bold text-gov-navy-950 tabular-nums">
                                    {{ formatRupiah(receipt.nominal_neto) }}
                                </td>

                                <!-- Status Badge & SLA -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                    <GovBadge :variant="getStatusBadge(receipt.status).variant" size="sm">
                                        {{ getStatusBadge(receipt.status).label }}
                                    </GovBadge>

                                    <!-- Indikator SLA Keterlambatan jika Revisi Minor -->
                                    <div v-if="receipt.status === 'REVISI_MINOR' && receipt.tenggat_sla" class="mt-1 text-[10px] text-amber-700 font-medium">
                                        SLA: {{ formatTanggalIndo(receipt.tenggat_sla, true) }}
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="whitespace-nowrap py-3.5 pl-3 pr-4 sm:pr-6 text-center text-xs font-medium">
                                    <button
                                        type="button"
                                        class="text-gov-navy-700 hover:text-gov-navy-950 bg-gov-navy-50 hover:bg-gov-navy-100 px-2.5 py-1.5 rounded-lg border border-gov-navy-200 transition-colors shadow-2xs"
                                    >
                                        Rincian
                                    </button>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!receipts.data || receipts.data.length === 0">
                                <td colspan="8" class="text-center py-12 text-slate-400">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="font-medium text-slate-600 text-sm">Tidak ada data kuitansi yang sesuai kriteria.</p>
                                        <p class="text-xs text-slate-400">Silakan ubah filter atau gunakan tombol 'Input Kuitansi Baru' untuk menambah data.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="receipts.links && receipts.links.length > 3" class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-xs text-slate-500">
                        Menampilkan <span class="font-medium text-slate-800">{{ receipts.from || 0 }}</span> s.d. <span class="font-medium text-slate-800">{{ receipts.to || 0 }}</span> dari total <span class="font-medium text-slate-800">{{ receipts.total || 0 }}</span> kuitansi
                    </div>

                    <div class="flex items-center space-x-1">
                        <!-- Aman: link.label berasal murni dari paginator bawaan Laravel (&laquo; / &raquo;), bukan input pengguna -->
                        <template v-for="(link, i) in receipts.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :only="['receipts']"
                                class="px-3 py-1 text-xs rounded-md font-medium transition-colors"
                                :class="link.active ? 'bg-gov-navy-900 text-white font-bold' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1 text-xs rounded-md font-medium bg-slate-100 text-slate-400 cursor-not-allowed"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide-Over Drawer Detail Kuitansi & Aksi Cepat -->
        <ReceiptDetailDrawer
            :show="isDrawerOpen"
            :receipt-id="selectedReceiptId"
            @close="isDrawerOpen = false"
            @refresh="applyFilter"
        />
    </GovAppLayout>
</template>
