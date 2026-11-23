<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { sppdService } from '@/Services/sppdService';
import { budgetService } from '@/Services/budgetService';
import { STATUS_LABELS } from '@/Constants/workflowStatus';
import { FileSpreadsheet, FileText, Download, Search, Camera, X, CalendarRange, ExternalLink } from 'lucide-vue-next';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSkpdWide = computed(() => ['VERIFIKATOR', 'KPA', 'BP'].includes(currentUser.value?.role));

// State Data
const rows = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const kpi = ref({
    total_perjalanan: 0,
    total_pegawai_unik: 0,
    total_hari_orang: 0,
    belum_ada_foto: 0,
});
const subKegiatanOptions = ref([]);
const pegawaiOptions = ref([]);
const units = ref([]);
const loading = ref(false);
const filterError = ref('');

// State Filter
const filters = ref({
    tanggal_dari: '',
    tanggal_sampai: '',
    kode_sub_kegiatan: '',
    pegawai_id: '',
    unit_kerja_id: '',
    status: '',
    search: '',
});

// State Lightbox Preview Foto (<dialog>)
const photoDialogRef = ref(null);
const activePhoto = ref({
    url: '',
    nama_pegawai: '',
    nip: '',
    nomor_kuitansi: '',
    tanggal_berangkat: '',
    tanggal_pulang: '',
    tujuan: '',
});

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'DRAFT', label: 'Draft' },
    { value: 'MENUNGGU_PPTK', label: 'Menunggu PPTK' },
    { value: 'MENUNGGU_LURAH', label: 'Menunggu Lurah' },
    { value: 'MENUNGGU_VERIFIKASI', label: 'Menunggu Verifikasi' },
    { value: 'REVISI_MINOR', label: 'Revisi Minor' },
    { value: 'REVISI_MAYOR', label: 'Revisi Mayor / Ditolak' },
    { value: 'LOLOS_VERIFIKASI', label: 'Lolos Verifikasi' },
    { value: 'DIBUNDEL', label: 'Dibundel' },
];

function getStatusBadgeVariant(status) {
    if (['LOLOS_VERIFIKASI', 'DIBUNDEL'].includes(status)) return 'success';
    if (['REVISI_MINOR', 'MENUNGGU_PPTK', 'MENUNGGU_LURAH', 'MENUNGGU_VERIFIKASI'].includes(status)) return 'warning';
    if (['REVISI_MAYOR', 'DITOLAK'].includes(status)) return 'danger';
    return 'neutral';
}

function buildActiveParams(extra = {}) {
    const params = { ...extra };
    Object.entries(filters.value).forEach(([key, val]) => {
        if (val !== undefined && val !== null && String(val).trim() !== '') {
            params[key] = String(val).trim();
        }
    });
    return params;
}

function validateDateRange() {
    filterError.value = '';
    if (filters.value.tanggal_dari && filters.value.tanggal_sampai && filters.value.tanggal_sampai < filters.value.tanggal_dari) {
        filterError.value = 'Tanggal Sampai tidak boleh lebih awal dari Tanggal Dari.';
        return false;
    }
    return true;
}

async function fetchReport(pageNumber = 1) {
    if (!validateDateRange()) return;

    loading.value = true;
    try {
        const res = await sppdService.getSppdReport(buildActiveParams({ page: pageNumber, per_page: 15 }));
        const report = res.report || {};
        rows.value = report.data ?? [];
        pagination.value = {
            current_page: report.current_page ?? 1,
            last_page: report.last_page ?? 1,
            total: report.total ?? 0,
        };
        kpi.value = res.kpi || kpi.value;
        subKegiatanOptions.value = res.sub_kegiatan_options || [];
    } catch (err) {
        filterError.value = err.response?.data?.message || 'Gagal memuat laporan rekap SPPD.';
    } finally {
        loading.value = false;
    }
}

function exportReport(format) {
    if (!validateDateRange()) return;
    const url = sppdService.getSppdExportUrl(buildActiveParams(), format);
    window.open(url, '_blank');
}

function resetFilters() {
    filters.value = {
        tanggal_dari: '',
        tanggal_sampai: '',
        kode_sub_kegiatan: '',
        pegawai_id: '',
        unit_kerja_id: '',
        status: '',
        search: '',
    };
    filterError.value = '';
    fetchReport(1);
}

function openPhotoLightbox(row) {
    if (!row.effective_foto_url) return;
    activePhoto.value = {
        url: row.effective_foto_url,
        nama_pegawai: row.nama_pegawai,
        nip: row.nip,
        nomor_kuitansi: row.nomor_kuitansi,
        tanggal_berangkat: row.tanggal_berangkat,
        tanggal_pulang: row.tanggal_pulang,
        tujuan: row.tujuan,
    };
    photoDialogRef.value?.showModal();
}

function closePhotoLightbox() {
    photoDialogRef.value?.close();
}

onMounted(async () => {
    const [pegawaiRes, unitsRes] = await Promise.all([
        sppdService.getPegawaiOptions({ include_inactive: 1, limit: 300 }).catch(() => []),
        isSkpdWide.value ? budgetService.getUnits().catch(() => []) : Promise.resolve([]),
    ]);
    pegawaiOptions.value = pegawaiRes;
    units.value = unitsRes;
    await fetchReport(1);
});
</script>

<template>
    <Head title="Laporan Rekap SPPD — E-Verifikatur" />

    <GovAppLayout title="Laporan Rekap SPPD">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-ink flex items-center gap-2">
                        <CalendarRange class="w-5 h-5 text-accent" :stroke-width="1.75" />
                        <span>Laporan Rekap Perjalanan Dinas (SPPD)</span>
                    </h1>
                    <p class="text-xs text-ink-muted mt-0.5">
                        Rekapitulasi pelaksana SPPD berdasarkan tanggal keberangkatan, sub kegiatan, dan bukti foto keberangkatan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <GovButton size="sm" variant="secondary" @click="exportReport('csv')">
                        <Download class="w-3.5 h-3.5" />
                        <span>CSV</span>
                    </GovButton>
                    <GovButton size="sm" variant="secondary" @click="exportReport('pdf')">
                        <FileText class="w-3.5 h-3.5" />
                        <span>Cetak PDF</span>
                    </GovButton>
                    <GovButton size="sm" @click="exportReport('xlsx')">
                        <FileSpreadsheet class="w-3.5 h-3.5" />
                        <span>Ekspor Excel (.xlsx)</span>
                    </GovButton>
                </div>
            </div>
        </template>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-surface border border-hairline rounded-xl p-4">
                <p class="text-xs font-medium text-ink-secondary">Total Perjalanan Peserta</p>
                <p class="text-xl font-bold font-mono tabular-nums text-ink mt-1">{{ kpi.total_perjalanan }}</p>
                <p class="text-[11px] text-ink-muted mt-0.5">Entri pelaksana sesuai filter</p>
            </div>
            <div class="bg-surface border border-hairline rounded-xl p-4">
                <p class="text-xs font-medium text-ink-secondary">Pegawai Unik Berangkat</p>
                <p class="text-xl font-bold font-mono tabular-nums text-ink mt-1">{{ kpi.total_pegawai_unik }}</p>
                <p class="text-[11px] text-ink-muted mt-0.5">Personel terlibat SPPD</p>
            </div>
            <div class="bg-surface border border-hairline rounded-xl p-4">
                <p class="text-xs font-medium text-ink-secondary">Akumulasi Hari-Orang</p>
                <p class="text-xl font-bold font-mono tabular-nums text-ink mt-1">{{ kpi.total_hari_orang }} hari</p>
                <p class="text-[11px] text-ink-muted mt-0.5">Total durasi perjalanan dinas</p>
            </div>
            <div class="bg-surface border border-hairline rounded-xl p-4">
                <p class="text-xs font-medium text-ink-secondary">Belum Melampirkan Foto</p>
                <p
                    class="text-xl font-bold font-mono tabular-nums mt-1"
                    :class="kpi.belum_ada_foto > 0 ? 'text-semantic-warning' : 'text-semantic-success'"
                >
                    {{ kpi.belum_ada_foto }}
                </p>
                <p class="text-[11px] text-ink-muted mt-0.5">Peserta tanpa foto keberangkatan</p>
            </div>
        </div>

        <!-- Filter Bar Multi-Dimensi -->
        <div class="bg-surface border border-hairline rounded-xl p-4 mb-4">
            <div v-if="filterError" class="mb-3 p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-semantic-danger">
                {{ filterError }}
            </div>

            <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end" @submit.prevent="fetchReport(1)">
                <div>
                    <label for="sppd-tgl-dari" class="block text-xs font-medium text-ink-secondary mb-1">Tanggal Berangkat Dari</label>
                    <input
                        id="sppd-tgl-dari"
                        v-model="filters.tanggal_dari"
                        type="date"
                        class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    />
                </div>

                <div>
                    <label for="sppd-tgl-sampai" class="block text-xs font-medium text-ink-secondary mb-1">Tanggal Sampai</label>
                    <input
                        id="sppd-tgl-sampai"
                        v-model="filters.tanggal_sampai"
                        type="date"
                        class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    />
                </div>

                <div>
                    <label for="sppd-sub-kegiatan" class="block text-xs font-medium text-ink-secondary mb-1">Sub Kegiatan</label>
                    <select
                        id="sppd-sub-kegiatan"
                        v-model="filters.kode_sub_kegiatan"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    >
                        <option value="">Semua Sub Kegiatan</option>
                        <option
                            v-for="sub in subKegiatanOptions"
                            :key="`${sub.unit_kerja_id}-${sub.kode_sub_kegiatan}`"
                            :value="sub.kode_sub_kegiatan"
                        >
                            {{ sub.kode_sub_kegiatan }} — {{ sub.nama_sub_kegiatan }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="sppd-pegawai" class="block text-xs font-medium text-ink-secondary mb-1">Pegawai</label>
                    <select
                        id="sppd-pegawai"
                        v-model="filters.pegawai_id"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    >
                        <option value="">Semua Pegawai</option>
                        <option v-for="p in pegawaiOptions" :key="p.id" :value="p.id">
                            {{ p.nama }} ({{ p.nip }})
                        </option>
                    </select>
                </div>

                <div v-if="isSkpdWide">
                    <label for="sppd-unit" class="block text-xs font-medium text-ink-secondary mb-1">Unit Kerja</label>
                    <select
                        id="sppd-unit"
                        v-model="filters.unit_kerja_id"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    >
                        <option value="">Semua Unit Kerja</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.nama_unit }}</option>
                    </select>
                </div>

                <div>
                    <label for="sppd-status" class="block text-xs font-medium text-ink-secondary mb-1">Status Kuitansi</label>
                    <select
                        id="sppd-status"
                        v-model="filters.status"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                    >
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="sppd-search" class="block text-xs font-medium text-ink-secondary mb-1">Cari (Nama / NIP / No. Kuitansi / Tujuan)</label>
                    <div class="relative">
                        <Search class="w-3.5 h-3.5 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            id="sppd-search"
                            v-model="filters.search"
                            type="text"
                            placeholder="Kata kunci..."
                            class="w-full h-9 pl-8 pr-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <GovButton type="submit" size="sm">Terapkan Filter</GovButton>
                    <GovButton type="button" variant="secondary" size="sm" @click="resetFilters">Reset</GovButton>
                </div>
            </form>
        </div>

        <!-- Tabel Rekap SPPD -->
        <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
            <div tabindex="0" role="region" aria-labelledby="sppd-report-caption" class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption id="sppd-report-caption" class="sr-only">Tabel Rekapitulasi Laporan Perjalanan Dinas (SPPD)</caption>
                    <thead>
                        <tr class="bg-canvas border-b border-hairline text-[11px] font-semibold uppercase tracking-wider text-ink-secondary">
                            <th scope="col" class="px-4 py-2.5">Tanggal Perjalanan</th>
                            <th scope="col" class="px-4 py-2.5">Pegawai (NIP &amp; Nama)</th>
                            <th scope="col" class="px-4 py-2.5">Sub Kegiatan &amp; Unit</th>
                            <th scope="col" class="px-4 py-2.5">Tujuan</th>
                            <th scope="col" class="px-4 py-2.5">Kuitansi &amp; Status</th>
                            <th scope="col" class="px-4 py-2.5 text-center">Foto Keberangkatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline text-xs">
                        <tr v-if="loading">
                            <td colspan="6" class="px-4 py-8 text-center text-ink-muted">Memuat data laporan SPPD...</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-ink-muted">
                                Tidak ada data perjalanan dinas (SPPD) sesuai filter aktif.
                            </td>
                        </tr>
                        <tr v-for="row in rows" v-else :key="row.id" class="hover:bg-canvas/60 transition-colors">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="font-mono tabular-nums font-medium text-ink">
                                    {{ row.tanggal_berangkat }} s/d {{ row.tanggal_pulang }}
                                </div>
                                <div class="text-[11px] text-ink-muted tabular-nums">{{ row.durasi_hari }} hari</div>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-ink">{{ row.nama_pegawai }}</div>
                                <div class="text-[11px] font-mono tabular-nums text-ink-muted">
                                    NIP {{ row.nip }}<span v-if="row.jabatan"> • {{ row.jabatan }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 max-w-xs">
                                <div class="font-mono text-[11px] text-ink-secondary">{{ row.kode_sub_kegiatan }}</div>
                                <div class="text-ink truncate" :title="row.nama_sub_kegiatan">{{ row.nama_sub_kegiatan }}</div>
                                <div class="text-[11px] text-ink-muted">{{ row.unit_kerja }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-ink-secondary">{{ row.tujuan || '—' }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="font-mono text-[11px] font-medium text-ink mb-1">
                                    <Link :href="route('receipts.index', { search: row.nomor_kuitansi })" class="text-accent hover:underline inline-flex items-center gap-1">
                                        <span>{{ row.nomor_kuitansi }}</span>
                                        <ExternalLink class="w-3 h-3" />
                                    </Link>
                                </div>
                                <GovBadge :variant="getStatusBadgeVariant(row.status_kuitansi)" size="sm">
                                    {{ STATUS_LABELS[row.status_kuitansi] || row.status_kuitansi }}
                                </GovBadge>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <button
                                    v-if="row.effective_foto_url"
                                    type="button"
                                    class="inline-flex flex-col items-center gap-1 group"
                                    title="Klik untuk memperbesar foto keberangkatan"
                                    @click="openPhotoLightbox(row)"
                                >
                                    <img
                                        :src="row.effective_foto_url"
                                        alt="Foto Keberangkatan"
                                        class="w-11 h-11 rounded-md object-cover border border-hairline group-hover:ring-2 group-hover:ring-accent"
                                    />
                                </button>
                                <span v-else class="inline-flex items-center gap-1 text-[11px] text-ink-muted">
                                    <Camera class="w-3.5 h-3.5" />
                                    <span>Belum ada</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-hairline bg-canvas flex items-center justify-between text-xs text-ink-secondary">
                <span class="tabular-nums">Total {{ pagination.total }} entri • Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <div class="flex items-center gap-2">
                    <GovButton
                        size="sm"
                        variant="secondary"
                        :disabled="pagination.current_page <= 1"
                        @click="fetchReport(pagination.current_page - 1)"
                    >
                        Sebelumnya
                    </GovButton>
                    <GovButton
                        size="sm"
                        variant="secondary"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="fetchReport(pagination.current_page + 1)"
                    >
                        Berikutnya
                    </GovButton>
                </div>
            </div>
        </div>

        <!-- Lightbox Preview Foto (<dialog>) -->
        <dialog
            ref="photoDialogRef"
            aria-labelledby="sppd-photo-modal-title"
            class="sppd-dialog w-full max-w-2xl rounded-xl bg-surface text-ink border border-hairline p-0 shadow-xl backdrop:bg-slate-900/70"
        >
            <div class="px-5 py-3 border-b border-hairline flex items-center justify-between">
                <div>
                    <h2 id="sppd-photo-modal-title" class="text-sm font-semibold text-ink">
                        Foto Keberangkatan — {{ activePhoto.nama_pegawai }}
                    </h2>
                    <p class="text-[11px] text-ink-muted font-mono tabular-nums">
                        NIP {{ activePhoto.nip }} • {{ activePhoto.nomor_kuitansi }} • {{ activePhoto.tanggal_berangkat }} s/d {{ activePhoto.tanggal_pulang }}
                    </p>
                </div>
                <button
                    type="button"
                    class="p-1.5 rounded-md text-ink-muted hover:text-ink hover:bg-raised"
                    aria-label="Tutup preview foto"
                    autofocus
                    @click="closePhotoLightbox"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
            <div class="p-4 bg-canvas flex flex-col items-center gap-3">
                <img
                    v-if="activePhoto.url"
                    :src="activePhoto.url"
                    alt="Foto Keberangkatan SPPD"
                    class="max-h-[70vh] w-auto rounded-lg border border-hairline object-contain"
                />
                <div class="w-full flex items-center justify-between text-xs text-ink-secondary">
                    <span>Tujuan: {{ activePhoto.tujuan || '—' }}</span>
                    <a :href="activePhoto.url" target="_blank" class="text-accent font-medium underline">Buka di Tab Baru</a>
                </div>
            </div>
        </dialog>
    </GovAppLayout>
</template>

<style scoped>
.sppd-dialog:modal {
    overscroll-behavior: contain;
}

.sppd-dialog::backdrop {
    overflow: hidden;
    overscroll-behavior: contain;
}
</style>
