<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, Plus, Search, RotateCcw, User, FileText } from 'lucide-vue-next';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import ReceiptDetailDrawer from '@/Pages/Receipts/Partials/ReceiptDetailDrawer.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo, NAMA_BULAN } from '@/Utils/date';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isUnitScoped = computed(() => !currentUser.value?.is_skpd_wide);
const canCreate = computed(() => ['BPP', 'BP', 'VERIFIKATOR'].includes(currentUser.value?.role));

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
    subKegiatanList: {
        type: Array,
        default: () => [],
    },
    rekeningList: {
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
const selectedSubKegiatan = ref(props.filters.kode_sub_kegiatan || '');
const selectedRekening = ref(props.filters.kode_rekening || '');
const tanggalDari = ref(props.filters.tanggal_dari || '');
const tanggalSampai = ref(props.filters.tanggal_sampai || '');
const isExporting = ref(false);

const dateRangeError = computed(() => {
    if (tanggalDari.value && tanggalSampai.value && tanggalSampai.value < tanggalDari.value) {
        return 'Tanggal sampai tidak boleh lebih awal dari tanggal dari.';
    }
    return '';
});

const filteredSubKegiatanList = computed(() => {
    const unitId = selectedUnit.value ? Number(selectedUnit.value) : null;
    const seen = new Set();
    return props.subKegiatanList.filter((item) => {
        if (unitId && Number(item.unit_kerja_id) !== unitId) return false;
        if (seen.has(item.kode_sub_kegiatan)) return false;
        seen.add(item.kode_sub_kegiatan);
        return true;
    });
});

const filteredRekeningList = computed(() => {
    const unitId = selectedUnit.value ? Number(selectedUnit.value) : null;
    const subKode = selectedSubKegiatan.value || null;
    const seen = new Set();
    return props.rekeningList.filter((item) => {
        if (unitId && Number(item.unit_kerja_id) !== unitId) return false;
        if (subKode && item.kode_sub_kegiatan !== subKode) return false;
        if (seen.has(item.kode_rekening)) return false;
        seen.add(item.kode_rekening);
        return true;
    });
});

watch(selectedUnit, () => {
    if (
        selectedSubKegiatan.value &&
        !filteredSubKegiatanList.value.some((i) => i.kode_sub_kegiatan === selectedSubKegiatan.value)
    ) {
        selectedSubKegiatan.value = '';
    }
    if (
        selectedRekening.value &&
        !filteredRekeningList.value.some((i) => i.kode_rekening === selectedRekening.value)
    ) {
        selectedRekening.value = '';
    }
});

watch(selectedSubKegiatan, () => {
    if (
        selectedRekening.value &&
        !filteredRekeningList.value.some((i) => i.kode_rekening === selectedRekening.value)
    ) {
        selectedRekening.value = '';
    }
});

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
    if (dateRangeError.value) return;

    router.get(route('receipts.index'), {
        status: activeTab.value || undefined,
        unit_kerja_id: selectedUnit.value || undefined,
        kategori_belanja: selectedKategori.value || undefined,
        bulan_anggaran: selectedBulan.value || undefined,
        kode_sub_kegiatan: selectedSubKegiatan.value || undefined,
        kode_rekening: selectedRekening.value || undefined,
        tanggal_dari: tanggalDari.value || undefined,
        tanggal_sampai: tanggalSampai.value || undefined,
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
    selectedUnit.value = isUnitScoped.value ? (currentUser.value?.unit_kerja_id || '') : '';
    selectedKategori.value = '';
    selectedBulan.value = '';
    selectedSubKegiatan.value = '';
    selectedRekening.value = '';
    tanggalDari.value = '';
    tanggalSampai.value = '';
    applyFilter();
}

// URL Ekspor Rekapitulasi Data
function buildExportUrl(format) {
    const params = new URLSearchParams();
    params.append('format', format);
    if (activeTab.value) params.append('status', activeTab.value);
    if (selectedUnit.value) params.append('unit_kerja_id', selectedUnit.value);
    if (selectedKategori.value) params.append('kategori_belanja', selectedKategori.value);
    if (selectedBulan.value) params.append('bulan_anggaran', selectedBulan.value);
    if (selectedSubKegiatan.value) params.append('kode_sub_kegiatan', selectedSubKegiatan.value);
    if (selectedRekening.value) params.append('kode_rekening', selectedRekening.value);
    if (tanggalDari.value) params.append('tanggal_dari', tanggalDari.value);
    if (tanggalSampai.value) params.append('tanggal_sampai', tanggalSampai.value);
    if (searchQuery.value) params.append('search', searchQuery.value);

    return `/api/v1/receipts/export?${params.toString()}`;
}

function handleExport(format) {
    if (dateRangeError.value || isExporting.value) return;
    isExporting.value = true;
    window.location.href = buildExportUrl(format);
    setTimeout(() => {
        isExporting.value = false;
    }, 1500);
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
                    <h1 class="text-xl font-semibold text-ink tracking-tight">
                        Daftar & Manajemen Kuitansi
                    </h1>
                    <p class="text-xs text-ink-muted mt-1">
                        Monitoring pre-audit akuntabilitas belanja daerah se-Kecamatan Tarogong Kidul sebelum pembundelan SPJ
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <GovButton
                        @click="handleExport('xlsx')"
                        :disabled="isExporting || Boolean(dateRangeError)"
                        variant="secondary"
                        size="sm"
                        title="Unduh rekapitulasi data kuitansi dan perpajakan ke format Excel (.xlsx)"
                    >
                        <Download class="w-4 h-4" :stroke-width="1.5" />
                        Ekspor Excel (.xlsx)
                    </GovButton>

                    <GovButton
                        @click="handleExport('pdf')"
                        :disabled="isExporting || Boolean(dateRangeError)"
                        variant="secondary"
                        size="sm"
                        title="Unduh laporan rekapitulasi kuitansi resmi berformat PDF"
                    >
                        <FileText class="w-4 h-4" :stroke-width="1.5" />
                        Ekspor PDF (.pdf)
                    </GovButton>

                    <Link v-if="canCreate" :href="route('receipts.create')">
                        <GovButton variant="primary" size="sm">
                            <Plus class="w-4 h-4" :stroke-width="1.5" />
                            Input Kuitansi Baru
                        </GovButton>
                    </Link>
                </div>
            </div>

            <!-- Tab Filter Status -->
            <div class="border-b border-hairline">
                <nav class="flex space-x-1 overflow-x-auto pb-px" aria-label="Tabs Status Kuitansi">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="handleTabChange(tab.key)"
                        class="whitespace-nowrap py-2.5 px-3.5 rounded-t-lg font-medium text-xs sm:text-sm transition-colors duration-150 ease-unslop flex items-center gap-2 border-b-2"
                        :class="[
                            activeTab === tab.key
                                ? 'border-accent text-accent bg-surface'
                                : 'border-transparent text-ink-muted hover:text-ink hover:border-hairline'
                        ]"
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="px-1.5 py-0.5 rounded-md text-xs font-mono tabular-nums"
                            :class="[
                                tab.isAlert
                                    ? 'bg-red-50 text-semantic-danger border border-red-200'
                                    : (activeTab === tab.key ? 'bg-accent-soft text-accent' : 'bg-raised text-ink-secondary')
                            ]"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Filter Bar Multi-Dimensi -->
            <div class="bg-surface p-4 rounded-xl border border-hairline shadow-subtle space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Search Input -->
                    <div class="lg:col-span-2 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-muted">
                            <Search class="w-4 h-4" :stroke-width="1.5" />
                        </div>
                        <input
                            v-model="searchQuery"
                            @input="handleSearchInput"
                            type="text"
                            placeholder="Cari nomor bukti, uraian, rekanan"
                            class="w-full h-9 pl-9 pr-3 text-sm border border-hairline rounded-lg bg-surface text-ink placeholder:text-ink-muted focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent"
                        />
                    </div>

                    <!-- Dropdown Unit Kerja -->
                    <div>
                        <select
                            v-model="selectedUnit"
                            @change="applyFilter"
                            :disabled="isUnitScoped"
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent bg-surface text-ink disabled:bg-raised disabled:text-ink-muted"
                        >
                            <option v-if="!isUnitScoped" value="">Semua Unit Kerja</option>
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
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent bg-surface text-ink"
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
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent bg-surface text-ink"
                        >
                            <option value="">Semua Bulan RAK</option>
                            <option v-for="(nama, idx) in NAMA_BULAN" :key="idx + 1" :value="idx + 1">
                                Bulan {{ idx + 1 }} ({{ nama }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Filter Bar Tambahan: Sub Kegiatan, Kode Rekening, & Rentang Waktu -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-hairline">
                    <!-- Dropdown Sub Kegiatan -->
                    <div>
                        <select
                            v-model="selectedSubKegiatan"
                            @change="applyFilter"
                            aria-label="Filter Sub Kegiatan"
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent bg-surface text-ink"
                        >
                            <option value="">Semua Sub Kegiatan</option>
                            <option
                                v-for="sub in filteredSubKegiatanList"
                                :key="sub.kode_sub_kegiatan"
                                :value="sub.kode_sub_kegiatan"
                            >
                                {{ sub.kode_sub_kegiatan }} — {{ sub.nama_sub_kegiatan }}
                            </option>
                        </select>
                    </div>

                    <!-- Dropdown Kode Rekening Belanja -->
                    <div>
                        <select
                            v-model="selectedRekening"
                            @change="applyFilter"
                            aria-label="Filter Kode Rekening Belanja"
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent bg-surface text-ink"
                        >
                            <option value="">Semua Kode Rekening</option>
                            <option
                                v-for="rek in filteredRekeningList"
                                :key="rek.kode_rekening"
                                :value="rek.kode_rekening"
                            >
                                {{ rek.kode_rekening }} — {{ rek.nama_rekening }}
                            </option>
                        </select>
                    </div>

                    <!-- Input Tanggal Dari -->
                    <div>
                        <input
                            v-model="tanggalDari"
                            @change="applyFilter"
                            type="date"
                            aria-label="Tanggal Transaksi Dari"
                            title="Tanggal Transaksi Dari"
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg bg-surface text-ink focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent"
                        />
                    </div>

                    <!-- Input Tanggal Sampai -->
                    <div>
                        <input
                            v-model="tanggalSampai"
                            @change="applyFilter"
                            type="date"
                            aria-label="Tanggal Transaksi Sampai"
                            title="Tanggal Transaksi Sampai"
                            class="w-full h-9 px-3 text-sm border border-hairline rounded-lg bg-surface text-ink focus:outline-none focus:ring-1 focus:ring-accent focus:border-accent"
                        />
                    </div>
                </div>

                <p v-if="dateRangeError" class="text-xs text-semantic-danger font-medium">
                    {{ dateRangeError }}
                </p>

                <!-- Tombol Reset Filter jika ada parameter aktif -->
                <div v-if="selectedUnit || selectedKategori || selectedBulan || selectedSubKegiatan || selectedRekening || tanggalDari || tanggalSampai || searchQuery || activeTab" class="flex items-center justify-between text-xs text-ink-muted pt-2 border-t border-hairline">
                    <span>
                        Menampilkan hasil filter kuitansi belanja
                    </span>
                    <button
                        @click="resetFilter"
                        class="text-accent hover:text-accent-hover font-medium flex items-center gap-1.5 transition-colors duration-150 ease-unslop"
                    >
                        <RotateCcw class="w-3.5 h-3.5" :stroke-width="1.5" />
                        Reset Semua Filter
                    </button>
                </div>
            </div>

            <!-- Tabel Data Kuitansi -->
            <div class="bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-hairline text-left text-sm">
                        <thead class="bg-canvas text-ink-secondary font-medium text-xs">
                            <tr>
                                <th scope="col" class="py-3 pl-4 pr-3 sm:pl-6">No. Bukti / Tanggal</th>
                                <th scope="col" class="px-3 py-3">Unit & Rekening Anggaran</th>
                                <th scope="col" class="px-3 py-3">Uraian & Rekanan</th>
                                <th scope="col" class="px-3 py-3 text-right">Bruto (Rp)</th>
                                <th scope="col" class="px-3 py-3 text-right">Pajak (Rp)</th>
                                <th scope="col" class="px-3 py-3 text-right">Neto (Rp)</th>
                                <th scope="col" class="px-3 py-3 text-center">Status</th>
                                <th scope="col" class="relative py-3 pl-3 pr-4 sm:pr-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline bg-surface">
                            <tr
                                v-for="receipt in receipts.data"
                                :key="receipt.id"
                                @click="openReceiptDetail(receipt)"
                                class="hover:bg-raised/60 transition-colors duration-150 ease-unslop cursor-pointer"
                            >
                                <!-- No. Bukti & Tanggal -->
                                <td class="whitespace-nowrap py-3 pl-4 pr-3 sm:pl-6">
                                    <div class="font-mono font-medium text-ink">
                                        {{ receipt.nomor_kuitansi }}
                                    </div>
                                    <div class="text-xs text-ink-muted tabular-nums mt-0.5">
                                        {{ formatTanggalIndo(receipt.tanggal_transaksi) }}
                                    </div>
                                    <div v-if="receipt.rekening?.kategori_belanja" class="mt-1">
                                        <span class="inline-block text-xs font-mono font-medium px-1.5 py-0.5 rounded bg-raised text-ink-secondary border border-hairline">
                                            {{ receipt.rekening.kategori_belanja }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Unit & Rekening -->
                                <td class="px-3 py-3 max-w-xs">
                                    <div class="text-xs font-medium text-ink truncate" :title="receipt.rekening?.unit_kerja?.nama_unit">
                                        {{ receipt.rekening?.unit_kerja?.nama_unit || '-' }}
                                    </div>
                                    <div class="text-xs text-ink-secondary truncate mt-0.5 font-mono" :title="receipt.rekening?.kode_rekening">
                                        {{ receipt.rekening?.kode_rekening || '-' }}
                                    </div>
                                    <div class="text-xs text-ink-muted truncate" :title="receipt.rekening?.nama_rekening">
                                        {{ receipt.rekening?.nama_rekening || '-' }}
                                    </div>
                                </td>

                                <!-- Uraian & Rekanan -->
                                <td class="px-3 py-3 max-w-sm">
                                    <div class="text-xs text-ink font-medium line-clamp-2" :title="receipt.uraian">
                                        {{ receipt.uraian }}
                                    </div>
                                    <div class="text-xs text-ink-muted mt-1 flex items-center gap-1.5">
                                        <User class="w-3.5 h-3.5 shrink-0" :stroke-width="1.5" />
                                        <span class="truncate text-ink-secondary">{{ receipt.nama_penerima || '-' }}</span>
                                    </div>
                                </td>

                                <!-- Nilai Bruto -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono font-medium text-ink tabular-nums">
                                    {{ formatRupiah(receipt.nominal_bruto) }}
                                </td>

                                <!-- Total Pajak -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-semantic-danger font-medium tabular-nums">
                                    {{ formatRupiah(receipt.total_pajak) }}
                                </td>

                                <!-- Nilai Neto -->
                                <td class="whitespace-nowrap px-3 py-3 text-right font-mono font-medium text-ink tabular-nums">
                                    {{ formatRupiah(receipt.nominal_neto) }}
                                </td>

                                <!-- Status Badge & SLA -->
                                <td class="whitespace-nowrap px-3 py-3 text-center">
                                    <GovBadge :variant="getStatusBadge(receipt.status).variant" size="sm">
                                        {{ getStatusBadge(receipt.status).label }}
                                    </GovBadge>

                                    <!-- Indikator SLA Keterlambatan jika Revisi Minor -->
                                    <div v-if="receipt.status === 'REVISI_MINOR' && receipt.tenggat_sla" class="mt-1 text-xs text-semantic-warning font-medium tabular-nums">
                                        SLA: {{ formatTanggalIndo(receipt.tenggat_sla, true) }}
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="whitespace-nowrap py-3 pl-3 pr-4 sm:pr-6 text-center text-xs font-medium">
                                    <button
                                        type="button"
                                        class="text-accent hover:text-accent-hover bg-surface hover:bg-accent-soft px-2.5 py-1.5 rounded-lg border border-hairline transition-colors duration-150 ease-unslop"
                                    >
                                        Rincian
                                    </button>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!receipts.data || receipts.data.length === 0">
                                <td colspan="8" class="text-center py-12 text-ink-muted">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <FileText class="w-8 h-8 text-ink-muted" :stroke-width="1.5" />
                                        <p class="font-medium text-ink-secondary text-sm">Tidak ada data kuitansi yang sesuai kriteria</p>
                                        <p class="text-xs text-ink-muted">Ubah filter atau gunakan tombol Input Kuitansi Baru untuk menambah data</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="receipts.links && receipts.links.length > 3" class="px-4 py-3 bg-canvas border-t border-hairline flex items-center justify-between">
                    <div class="text-xs text-ink-muted tabular-nums">
                        Menampilkan <span class="font-medium text-ink">{{ receipts.from || 0 }}</span> s.d. <span class="font-medium text-ink">{{ receipts.to || 0 }}</span> dari total <span class="font-medium text-ink">{{ receipts.total || 0 }}</span> kuitansi
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
                                class="px-3 py-1 text-xs rounded-md font-medium transition-colors duration-150 ease-unslop"
                                :class="link.active ? 'bg-accent text-white' : 'bg-surface text-ink-secondary border border-hairline hover:bg-raised'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1 text-xs rounded-md font-medium bg-raised text-ink-muted cursor-not-allowed"
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
