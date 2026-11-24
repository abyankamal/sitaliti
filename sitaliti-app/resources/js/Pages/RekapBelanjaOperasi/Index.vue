<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { rekapBelanjaOperasiService } from '@/Services/rekapBelanjaOperasiService';
import { useToast } from '@/Composables/useToast';
import { BarChart3, Download, FileText, Search, X, Eye } from 'lucide-vue-next';

const toast = useToast();

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSkpdWide = computed(() => ['VERIFIKATOR', 'KPA', 'BP'].includes(currentUser.value?.role));

const MONTH_OPTIONS = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const loading = ref(false);
const summary = ref({
    total_pagu_tahunan: 0,
    total_target_rak_periode: 0,
    total_realisasi_sp2d: 0,
    total_sisa_anggaran: 0,
    persentase_realisasi: 0,
});
const rows = ref([]);
const posOptions = ref([]);
const unitOptions = ref([]);

const filters = ref({
    master_rekap_id: '',
    unit_kerja_id: '',
    bulan_dari: 1,
    bulan_sampai: 12,
    search: '',
});

const drilldownDialogRef = ref(null);
const drilldownLoading = ref(false);
const selectedPos = ref(null);
const drilldownTab = ref('rekening');
const drilldownData = ref({ rekenings: [], kuitansi: [] });

const formatCurrency = (val) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);

function applyPeriodPreset(dari, sampai) {
    filters.value.bulan_dari = dari;
    filters.value.bulan_sampai = sampai;
    fetchReport();
}

function buildFilterParams() {
    const params = {
        bulan_dari: filters.value.bulan_dari,
        bulan_sampai: filters.value.bulan_sampai,
    };
    if (filters.value.master_rekap_id !== '') params.master_rekap_id = filters.value.master_rekap_id;
    if (isSkpdWide.value && filters.value.unit_kerja_id) params.unit_kerja_id = filters.value.unit_kerja_id;
    if (filters.value.search.trim()) params.search = filters.value.search.trim();
    return params;
}

async function fetchReport() {
    loading.value = true;
    try {
        const data = await rekapBelanjaOperasiService.getReport(buildFilterParams());
        summary.value = data.summary ?? summary.value;
        rows.value = data.rows ?? [];
        posOptions.value = data.filters?.pos_options ?? [];
        unitOptions.value = data.filters?.unit_options ?? [];
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Gagal memuat laporan Rekap Belanja Operasi.');
    } finally {
        loading.value = false;
    }
}

async function openDrilldown(row) {
    selectedPos.value = row;
    drilldownTab.value = 'rekening';
    drilldownLoading.value = true;
    drilldownData.value = { rekenings: [], kuitansi: [] };
    drilldownDialogRef.value?.showModal();

    try {
        const params = {
            ...buildFilterParams(),
            pos_key: row.pos_key,
        };
        drilldownData.value = await rekapBelanjaOperasiService.getDrilldown(params);
    } catch (err) {
        toast.error(err?.response?.data?.message || 'Gagal memuat rincian pos rekap.');
    } finally {
        drilldownLoading.value = false;
    }
}

function closeDrilldown() {
    drilldownDialogRef.value?.close();
    selectedPos.value = null;
}

function downloadExport(format) {
    const label = format === 'xlsx' ? 'Excel (.xlsx)' : 'PDF';
    toast.info(`Menyiapkan unduhan Rekap Belanja Operasi (${label})...`);
    window.location.href = rekapBelanjaOperasiService.getExportUrl(buildFilterParams(), format);
}

onMounted(() => {
    fetchReport();
});
</script>

<template>
    <Head title="Rekap Belanja Operasi" />

    <GovAppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-ink tracking-tight flex items-center gap-2">
                        <BarChart3 class="w-5 h-5 text-navy-700" />
                        Rekap & Filterisasi Belanja Operasi (5.1)
                    </h1>
                    <p class="text-sm text-ink-muted mt-1">
                        Ringkasan Pagu Tahunan, Target RAK Periode, dan Realisasi SP2D Cair berdasarkan pos Master Rekap Belanja Operasi.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <GovButton variant="secondary" @click="downloadExport('xlsx')">
                        <Download class="w-4 h-4 mr-1.5" />
                        Export Excel (.xlsx)
                    </GovButton>
                    <GovButton variant="primary" @click="downloadExport('pdf')">
                        <FileText class="w-4 h-4 mr-1.5" />
                        Export PDF
                    </GovButton>
                </div>
            </div>

            <!-- 4 KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-surface border border-hairline rounded-xl p-4">
                    <div class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Pagu Tahunan</div>
                    <div class="text-lg font-bold text-ink font-mono tabular-nums mt-1">
                        {{ formatCurrency(summary.total_pagu_tahunan) }}
                    </div>
                </div>
                <div class="bg-surface border border-hairline rounded-xl p-4">
                    <div class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Target RAK Periode</div>
                    <div class="text-lg font-bold text-navy-800 font-mono tabular-nums mt-1">
                        {{ formatCurrency(summary.total_target_rak_periode) }}
                    </div>
                </div>
                <div class="bg-surface border border-hairline rounded-xl p-4">
                    <div class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Realisasi SP2D Cair</div>
                    <div class="text-lg font-bold text-emerald-700 font-mono tabular-nums mt-1">
                        {{ formatCurrency(summary.total_realisasi_sp2d) }}
                    </div>
                    <div class="text-xs text-ink-muted font-mono tabular-nums mt-0.5">
                        Serapan: {{ summary.persentase_realisasi ?? 0 }}%
                    </div>
                </div>
                <div class="bg-surface border border-hairline rounded-xl p-4">
                    <div class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Sisa Anggaran</div>
                    <div class="text-lg font-bold text-ink font-mono tabular-nums mt-1">
                        {{ formatCurrency(summary.total_sisa_anggaran) }}
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-surface border border-hairline rounded-xl p-4 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-ink-muted mr-1">Preset Periode:</span>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border border-hairline hover:bg-canvas" @click="applyPeriodPreset(1, 12)">Setahun</button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border border-hairline hover:bg-canvas" @click="applyPeriodPreset(1, 3)">TW I</button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border border-hairline hover:bg-canvas" @click="applyPeriodPreset(4, 6)">TW II</button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border border-hairline hover:bg-canvas" @click="applyPeriodPreset(7, 9)">TW III</button>
                    <button type="button" class="px-2.5 py-1 text-xs rounded-md border border-hairline hover:bg-canvas" @click="applyPeriodPreset(10, 12)">TW IV</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <select
                        v-model="filters.master_rekap_id"
                        class="px-3 py-2 text-sm border border-hairline rounded-lg bg-surface"
                        @change="fetchReport"
                    >
                        <option value="">Semua Pos Rekap</option>
                        <option v-for="pos in posOptions" :key="pos.id" :value="pos.id">
                            {{ pos.kode }} — {{ pos.nama }}
                        </option>
                        <option value="unmapped">Belum Dipetakan</option>
                    </select>

                    <select
                        v-if="isSkpdWide"
                        v-model="filters.unit_kerja_id"
                        class="px-3 py-2 text-sm border border-hairline rounded-lg bg-surface"
                        @change="fetchReport"
                    >
                        <option value="">Semua Unit Kerja</option>
                        <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.nama_unit }}</option>
                    </select>

                    <select v-model.number="filters.bulan_dari" class="px-3 py-2 text-sm border border-hairline rounded-lg bg-surface" @change="fetchReport">
                        <option v-for="m in MONTH_OPTIONS" :key="m.value" :value="m.value">Dari: {{ m.label }}</option>
                    </select>

                    <select v-model.number="filters.bulan_sampai" class="px-3 py-2 text-sm border border-hairline rounded-lg bg-surface" @change="fetchReport">
                        <option v-for="m in MONTH_OPTIONS" :key="m.value" :value="m.value">Sampai: {{ m.label }}</option>
                    </select>

                    <div class="relative lg:col-span-2 flex gap-2">
                        <div class="relative flex-1">
                            <Search class="w-4 h-4 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="Cari pos / kode rekening..."
                                class="w-full pl-9 pr-3 py-2 text-sm border border-hairline rounded-lg"
                                @keyup.enter="fetchReport"
                            />
                        </div>
                        <GovButton variant="secondary" @click="fetchReport">Terapkan</GovButton>
                    </div>
                </div>
            </div>

            <!-- Tabel Rekap -->
            <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-hairline text-sm">
                        <thead class="bg-canvas text-ink-muted text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Kode Pos</th>
                                <th class="px-4 py-3 text-left font-semibold">Nama Pos Rekap</th>
                                <th class="px-4 py-3 text-center font-semibold">Rekening</th>
                                <th class="px-4 py-3 text-right font-semibold">Pagu Tahunan</th>
                                <th class="px-4 py-3 text-right font-semibold">Target RAK</th>
                                <th class="px-4 py-3 text-right font-semibold">Realisasi SP2D</th>
                                <th class="px-4 py-3 text-right font-semibold">Sisa Anggaran</th>
                                <th class="px-4 py-3 text-right font-semibold">Serapan</th>
                                <th class="px-4 py-3 text-right font-semibold">Rincian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            <tr v-if="loading">
                                <td colspan="9" class="px-4 py-8 text-center text-ink-muted">Memuat rekap belanja operasi...</td>
                            </tr>
                            <tr v-else-if="rows.length === 0">
                                <td colspan="9" class="px-4 py-8 text-center text-ink-muted">Tidak ada data Belanja Operasi (5.1.*) untuk filter ini.</td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                v-else
                                :key="row.pos_key"
                                class="hover:bg-canvas/60 cursor-pointer"
                                @click="openDrilldown(row)"
                            >
                                <td class="px-4 py-3 font-mono font-semibold text-navy-800">{{ row.kode }}</td>
                                <td class="px-4 py-3 font-medium text-ink">{{ row.nama }}</td>
                                <td class="px-4 py-3 text-center font-mono tabular-nums text-ink-muted">{{ row.jumlah_rekening }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ formatCurrency(row.pagu_tahunan) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums text-navy-800">{{ formatCurrency(row.target_rak_periode) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums text-emerald-700 font-semibold">{{ formatCurrency(row.realisasi_sp2d) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ formatCurrency(row.sisa_anggaran) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ row.persentase_realisasi }}%</td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-navy-700 hover:bg-navy-50 rounded-lg"
                                        @click.stop="openDrilldown(row)"
                                    >
                                        <Eye class="w-3.5 h-3.5 mr-1" />
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Drill-Down -->
        <dialog
            ref="drilldownDialogRef"
            class="rounded-xl border border-hairline p-0 w-full max-w-4xl backdrop:bg-ink/40 shadow-xl"
        >
            <div class="flex flex-col max-h-[90vh] bg-surface">
                <div class="px-5 py-4 border-b border-hairline flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-ink">
                            Rincian Pos: {{ selectedPos?.kode }} — {{ selectedPos?.nama }}
                        </h2>
                        <p class="text-xs text-ink-muted mt-0.5 font-mono tabular-nums">
                            Pagu: {{ formatCurrency(selectedPos?.pagu_tahunan) }} | Realisasi SP2D: {{ formatCurrency(selectedPos?.realisasi_sp2d) }}
                        </p>
                    </div>
                    <button type="button" class="text-ink-muted hover:text-ink" @click="closeDrilldown">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="px-5 pt-3 border-b border-hairline flex gap-4 text-sm">
                    <button
                        type="button"
                        :class="['pb-2 font-semibold border-b-2', drilldownTab === 'rekening' ? 'border-navy-700 text-navy-800' : 'border-transparent text-ink-muted']"
                        @click="drilldownTab = 'rekening'"
                    >
                        Rincian Kode Rekening ({{ drilldownData.rekenings.length }})
                    </button>
                    <button
                        type="button"
                        :class="['pb-2 font-semibold border-b-2', drilldownTab === 'kuitansi' ? 'border-navy-700 text-navy-800' : 'border-transparent text-ink-muted']"
                        @click="drilldownTab = 'kuitansi'"
                    >
                        Kuitansi SP2D Cair ({{ drilldownData.kuitansi.length }})
                    </button>
                </div>

                <div class="p-5 overflow-y-auto flex-1">
                    <div v-if="drilldownLoading" class="py-8 text-center text-sm text-ink-muted">Memuat rincian...</div>

                    <div v-else-if="drilldownTab === 'rekening'" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-hairline text-xs">
                            <thead class="bg-canvas text-ink-muted uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left">Kode Rekening</th>
                                    <th class="px-3 py-2 text-left">Nama Rekening / Sub Kegiatan</th>
                                    <th class="px-3 py-2 text-left">Unit</th>
                                    <th class="px-3 py-2 text-right">Pagu</th>
                                    <th class="px-3 py-2 text-right">Realisasi SP2D</th>
                                    <th class="px-3 py-2 text-right">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                <tr v-for="r in drilldownData.rekenings" :key="r.id">
                                    <td class="px-3 py-2 font-mono font-semibold">{{ r.kode_rekening }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-ink">{{ r.nama_rekening }}</div>
                                        <div class="text-[11px] text-ink-muted">{{ r.kode_sub_kegiatan }} — {{ r.nama_sub_kegiatan }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-ink-muted">{{ r.unit_kerja }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ formatCurrency(r.pagu_tahunan) }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums text-emerald-700">{{ formatCurrency(r.realisasi_sp2d) }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ formatCurrency(r.sisa_anggaran) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-hairline text-xs">
                            <thead class="bg-canvas text-ink-muted uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left">No. Kuitansi</th>
                                    <th class="px-3 py-2 text-left">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Rekening</th>
                                    <th class="px-3 py-2 text-left">Uraian</th>
                                    <th class="px-3 py-2 text-right">Nominal Bruto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                <tr v-if="drilldownData.kuitansi.length === 0">
                                    <td colspan="5" class="px-3 py-6 text-center text-ink-muted">Belum ada kuitansi SP2D cair pada periode ini.</td>
                                </tr>
                                <tr v-for="k in drilldownData.kuitansi" v-else :key="k.id">
                                    <td class="px-3 py-2 font-mono font-semibold">{{ k.nomor_kuitansi }}</td>
                                    <td class="px-3 py-2 font-mono tabular-nums">{{ k.tanggal_transaksi }}</td>
                                    <td class="px-3 py-2 font-mono">{{ k.kode_rekening }}</td>
                                    <td class="px-3 py-2">{{ k.uraian }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums text-emerald-700 font-semibold">{{ formatCurrency(k.nominal_bruto) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </dialog>
    </GovAppLayout>
</template>
