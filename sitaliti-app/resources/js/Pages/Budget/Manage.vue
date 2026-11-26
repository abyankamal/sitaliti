<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovCurrencyInput from '@/Components/UI/GovCurrencyInput.vue';
import { formatRupiah } from '@/Utils/currency';
import { NAMA_BULAN } from '@/Utils/date';
import { BUDGET_CATEGORIES } from '@/Constants/budgetCategories';
import { useToast } from '@/Composables/useToast';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSkpdWide = computed(() => !!currentUser.value?.can_input_cross_unit);
const userUnitId = computed(() => currentUser.value?.unit_kerja_id ?? '');

const categoryOptions = Object.values(BUDGET_CATEGORIES);

const rekenings = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const units = ref([]);
const subActivities = ref([]);
const loading = ref(false);
const loadingSubActivities = ref(false);

// Filters
const filterUnitId = ref('');
const searchQuery = ref('');

const toast = useToast();

// Create Modal
const showCreateModal = ref(false);
const saving = ref(false);
const formErrors = ref({});
const formErrorGeneral = ref('');
const subkegiatanMode = ref('existing'); // 'existing' | 'new'
const selectedExistingSubkeg = ref('');

const createDefaultForm = () => ({
    unit_kerja_id: '',
    kode_sub_kegiatan: '',
    nama_sub_kegiatan: '',
    kode_rekening: '',
    nama_rekening: '',
    kategori_belanja: 'B.1',
    alokasi: Array.from({ length: 12 }, () => 0),
});

const form = ref(createDefaultForm());

const totalAlokasi = computed(() =>
    form.value.alokasi.reduce((sum, val) => sum + Number(val || 0), 0)
);

const selectedCategoryInfo = computed(
    () => categoryOptions.find((c) => c.code === form.value.kategori_belanja) || categoryOptions[0]
);

async function fetchUnits() {
    try {
        const res = await window.axios.get('/api/v1/budget/units');
        units.value = res.data?.data ?? [];
    } catch {
        units.value = [];
    }
}

async function fetchRekenings(page = 1) {
    loading.value = true;
    try {
        const params = { page };
        if (filterUnitId.value) params.unit_kerja_id = filterUnitId.value;
        if (searchQuery.value.trim()) params.q = searchQuery.value.trim();

        const res = await window.axios.get('/api/v1/budget-management/rekening', { params });
        const payload = res.data?.data;
        rekenings.value = payload?.data ?? [];
        pagination.value = {
            current_page: payload?.current_page ?? 1,
            last_page: payload?.last_page ?? 1,
            total: payload?.total ?? 0,
        };
    } catch {
        toast.error('Gagal memuat daftar rekening anggaran.');
    } finally {
        loading.value = false;
    }
}

async function fetchSubActivities(unitId) {
    if (!unitId) {
        subActivities.value = [];
        return;
    }
    loadingSubActivities.value = true;
    try {
        const res = await window.axios.get('/api/v1/budget-management/sub-activities', {
            params: { unit_kerja_id: unitId },
        });
        subActivities.value = res.data?.data ?? [];
    } catch {
        subActivities.value = [];
    } finally {
        loadingSubActivities.value = false;
    }
}

watch(
    () => form.value.unit_kerja_id,
    async (newUnitId) => {
        selectedExistingSubkeg.value = '';
        form.value.kode_sub_kegiatan = '';
        form.value.nama_sub_kegiatan = '';
        await fetchSubActivities(newUnitId);
        if (subActivities.value.length === 0) {
            subkegiatanMode.value = 'new';
        }
    }
);

watch(selectedExistingSubkeg, (kode) => {
    if (subkegiatanMode.value !== 'existing') return;
    const found = subActivities.value.find((s) => s.kode_sub_kegiatan === kode);
    if (found) {
        form.value.kode_sub_kegiatan = found.kode_sub_kegiatan;
        form.value.nama_sub_kegiatan = found.nama_sub_kegiatan || '';
    } else {
        form.value.kode_sub_kegiatan = '';
        form.value.nama_sub_kegiatan = '';
    }
});

watch(subkegiatanMode, (mode) => {
    selectedExistingSubkeg.value = '';
    form.value.kode_sub_kegiatan = '';
    form.value.nama_sub_kegiatan = '';
    if (mode === 'existing' && subActivities.value.length > 0) {
        selectedExistingSubkeg.value = subActivities.value[0].kode_sub_kegiatan;
    }
});

function openCreateModal() {
    form.value = createDefaultForm();
    if (!isSkpdWide.value && userUnitId.value) {
        form.value.unit_kerja_id = userUnitId.value;
    } else if (units.value.length > 0) {
        form.value.unit_kerja_id = units.value[0].id;
    }
    subkegiatanMode.value = 'existing';
    selectedExistingSubkeg.value = '';
    formErrors.value = {};
    formErrorGeneral.value = '';
    showCreateModal.value = true;
}

async function submitCreate() {
    saving.value = true;
    formErrors.value = {};
    formErrorGeneral.value = '';

    try {
        const payload = {
            ...form.value,
            alokasi: form.value.alokasi.map((v) => Number(v || 0)),
        };

        await window.axios.post('/api/v1/budget-management/rekening', payload);
        showCreateModal.value = false;
        toast.success('Kode rekening & alokasi 12 bulan berhasil ditambahkan.');
        await fetchRekenings(1);
    } catch (err) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data?.errors || {};
            formErrorGeneral.value = err.response.data?.message || 'Periksa kembali isian formulir.';
        } else {
            formErrorGeneral.value = err.response?.data?.message || 'Terjadi kesalahan saat menyimpan rekening.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await Promise.all([fetchUnits(), fetchRekenings()]);
});

// Edit Allocation Modal
const showEditModal = ref(false);
const editingRekening = ref(null);
const editAlokasi = ref(Array.from({ length: 12 }, () => 0));
const editMinRequired = ref(Array.from({ length: 12 }, () => 0));
const savingEdit = ref(false);
const editErrorGeneral = ref('');

const totalEditAlokasi = computed(() =>
    editAlokasi.value.reduce((sum, val) => sum + Number(val || 0), 0)
);

const editCumulativeMin = computed(() => {
    let running = 0;
    return editMinRequired.value.map((val) => {
        running += Number(val || 0);
        return running;
    });
});

const editCumulativeAlokasi = computed(() => {
    let running = 0;
    return editAlokasi.value.map((val) => {
        running += Number(val || 0);
        return running;
    });
});

function openEditModal(row) {
    editingRekening.value = row;
    const byMonth = {};
    (row.kas_bulanan || []).forEach((k) => {
        byMonth[Number(k.bulan)] = k;
    });

    editAlokasi.value = Array.from({ length: 12 }, (_, idx) => Number(byMonth[idx + 1]?.alokasi_pagu || 0));
    editMinRequired.value = Array.from(
        { length: 12 },
        (_, idx) => Number(byMonth[idx + 1]?.terkunci_bulan || 0) + Number(byMonth[idx + 1]?.realisasi_bulan || 0)
    );
    editErrorGeneral.value = '';
    showEditModal.value = true;
}

async function submitEdit() {
    if (!editingRekening.value) return;
    savingEdit.value = true;
    editErrorGeneral.value = '';

    try {
        await window.axios.put(`/api/v1/budget-management/rekening/${editingRekening.value.id}`, {
            alokasi: editAlokasi.value.map((v) => Number(v || 0)),
        });
        showEditModal.value = false;
        toast.success('Alokasi pagu bulanan berhasil diperbarui.');
        await fetchRekenings(pagination.value.current_page);
    } catch (err) {
        const errors = err.response?.data?.errors;
        if (errors?.alokasi?.length) {
            editErrorGeneral.value = errors.alokasi[0];
        } else {
            editErrorGeneral.value = err.response?.data?.message || 'Gagal memperbarui alokasi pagu.';
        }
    } finally {
        savingEdit.value = false;
    }
}
</script>

<template>
    <Head title="Kelola Anggaran & Kode Rekening" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-ink">
                        Kelola Anggaran &amp; Kode Rekening (RAK)
                    </h1>
                    <p class="text-xs text-ink-muted mt-1">
                        Tambahkan kode rekening belanja baru beserta jadwal alokasi pagu kas 12 bulan untuk seluruh unit kerja.
                    </p>
                </div>
                <GovButton variant="gold" size="sm" @click="openCreateModal">
                    + Tambah Kode Rekening
                </GovButton>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-5">
            <!-- Filter Bar -->
            <div class="bg-surface rounded-xl border border-hairline p-4 shadow-subtle flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex flex-col sm:flex-row gap-3 flex-1">
                    <select
                        v-if="isSkpdWide"
                        v-model="filterUnitId"
                        @change="fetchRekenings(1)"
                        class="rounded-lg border-hairline text-sm text-ink-secondary py-2 px-3 sm:w-64"
                    >
                        <option value="">Semua Unit Kerja</option>
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name || unit.nama_unit }}
                        </option>
                    </select>

                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari kode rekening, uraian, atau subkegiatan..."
                        @keyup.enter="fetchRekenings(1)"
                        class="rounded-lg border-hairline text-sm text-ink-secondary py-2 px-3 flex-1"
                    />
                </div>
                <GovButton variant="secondary" size="sm" @click="fetchRekenings(1)">
                    Cari
                </GovButton>
            </div>

            <!-- Table -->
            <div class="bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-hairline text-sm">
                        <thead class="bg-raised text-xs font-bold  text-ink-secondary">
                            <tr>
                                <th class="px-4 py-3 text-left">Unit Kerja</th>
                                <th class="px-4 py-3 text-left">Subkegiatan</th>
                                <th class="px-4 py-3 text-left">Kode &amp; Uraian Rekening</th>
                                <th class="px-4 py-3 text-center">Kategori</th>
                                <th class="px-4 py-3 text-right">Total Pagu (12 Bln)</th>
                                <th class="px-4 py-3 text-right">Terkunci</th>
                                <th class="px-4 py-3 text-right">Realisasi</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            <tr v-if="loading">
                                <td colspan="8" class="px-4 py-8 text-center text-ink-muted">Memuat data rekening anggaran...</td>
                            </tr>
                            <tr v-else-if="rekenings.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-ink-muted">Belum ada rekening yang sesuai filter.</td>
                            </tr>
                            <tr v-for="row in rekenings" :key="row.id" class="hover:bg-raised/70">
                                <td class="px-4 py-3 text-xs font-medium text-ink-secondary">
                                    {{ row.unit_kerja?.nama_unit || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-xs font-semibold text-ink">{{ row.kode_sub_kegiatan }}</div>
                                    <div class="text-xs text-ink-muted line-clamp-1">{{ row.nama_sub_kegiatan }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-xs font-bold text-ink">{{ row.kode_rekening }}</div>
                                    <div class="text-xs text-ink-secondary">{{ row.nama_rekening }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <GovBadge variant="info">{{ row.kategori_belanja }}</GovBadge>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">
                                    {{ formatRupiah(row.total_alokasi) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-700">
                                    {{ formatRupiah(row.total_terkunci) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700">
                                    {{ formatRupiah(row.total_realisasi) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <GovButton variant="secondary" size="sm" @click="openEditModal(row)">
                                        Edit Pagu
                                    </GovButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-hairline flex items-center justify-between text-xs text-ink-secondary">
                    <span>Total {{ pagination.total }} rekening • Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                    <div class="flex gap-2">
                        <GovButton
                            variant="secondary"
                            size="sm"
                            :disabled="pagination.current_page <= 1"
                            @click="fetchRekenings(pagination.current_page - 1)"
                        >
                            Sebelumnya
                        </GovButton>
                        <GovButton
                            variant="secondary"
                            size="sm"
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="fetchRekenings(pagination.current_page + 1)"
                        >
                            Berikutnya
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Kode Rekening -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 bg-ink/40 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-surface rounded-xl shadow-floating max-w-4xl w-full max-h-[92vh] overflow-y-auto scrollbar-thin p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-hairline pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-ink">Tambah Kode Rekening &amp; Alokasi RAK 12 Bulan</h3>
                        <p class="text-xs text-ink-muted">Pastikan kode rekening dan kategori belanja sesuai ketentuan pajak &amp; SIPD.</p>
                    </div>
                    <button type="button" class="text-ink-muted hover:text-ink-secondary text-xl leading-none" @click="showCreateModal = false" aria-label="Tutup"><X class="w-4 h-4" :stroke-width="1.5" /></button>
                </div>

                <div v-if="formErrorGeneral" class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 font-medium">
                    {{ formErrorGeneral }}
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <!-- Unit Kerja & Kategori -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-ink uppercase mb-1">Unit Kerja <span class="text-rose-500">*</span></label>
                            <select v-model="form.unit_kerja_id" required :disabled="!isSkpdWide" class="w-full rounded-lg border-hairline text-sm py-2 px-3 disabled:bg-raised disabled:text-ink-secondary">
                                <option value="" disabled>Pilih Unit Kerja</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                    {{ unit.name || unit.nama_unit }}
                                </option>
                            </select>
                            <p v-if="formErrors.unit_kerja_id" class="mt-1 text-xs text-rose-600">{{ formErrors.unit_kerja_id[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink uppercase mb-1">Kategori Belanja (Penentu Pajak) <span class="text-rose-500">*</span></label>
                            <select v-model="form.kategori_belanja" required class="w-full rounded-lg border-hairline text-sm py-2 px-3">
                                <option v-for="cat in categoryOptions" :key="cat.code" :value="cat.code">
                                    {{ cat.code }} — {{ cat.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-ink-muted">{{ selectedCategoryInfo?.description }}</p>
                            <p v-if="formErrors.kategori_belanja" class="mt-1 text-xs text-rose-600">{{ formErrors.kategori_belanja[0] }}</p>
                        </div>
                    </div>

                    <!-- Subkegiatan -->
                    <div class="p-3.5 rounded-xl bg-raised border border-hairline space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase text-ink">Subkegiatan</span>
                            <div class="flex gap-3 text-xs">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" v-model="subkegiatanMode" value="existing" :disabled="subActivities.length === 0" />
                                    <span>Pilih Subkegiatan Eksisting</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" v-model="subkegiatanMode" value="new" />
                                    <span>Subkegiatan Baru</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="subkegiatanMode === 'existing'">
                            <select v-model="selectedExistingSubkeg" class="w-full rounded-lg border-hairline text-sm py-2 px-3">
                                <option value="" disabled>{{ loadingSubActivities ? 'Memuat...' : 'Pilih Subkegiatan' }}</option>
                                <option v-for="sub in subActivities" :key="sub.kode_sub_kegiatan" :value="sub.kode_sub_kegiatan">
                                    {{ sub.kode_sub_kegiatan }} — {{ sub.nama_sub_kegiatan }}
                                </option>
                            </select>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-ink-secondary mb-1">Kode Subkegiatan (7.01.x.x.x.xxxx) <span class="text-rose-500">*</span></label>
                                <input v-model="form.kode_sub_kegiatan" type="text" placeholder="7.01.01.2.01.0001" required class="w-full rounded-lg border-hairline text-sm py-2 px-3 font-mono" />
                                <p v-if="formErrors.kode_sub_kegiatan" class="mt-1 text-xs text-rose-600">{{ formErrors.kode_sub_kegiatan[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-ink-secondary mb-1">Nama Subkegiatan <span class="text-rose-500">*</span></label>
                                <input v-model="form.nama_sub_kegiatan" type="text" placeholder="Penyusunan Dokumen Perencanaan" required class="w-full rounded-lg border-hairline text-sm py-2 px-3" />
                                <p v-if="formErrors.nama_sub_kegiatan" class="mt-1 text-xs text-rose-600">{{ formErrors.nama_sub_kegiatan[0] }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Kode & Nama Rekening -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-ink uppercase mb-1">Kode Rekening (5.x.x.x.x.x) <span class="text-rose-500">*</span></label>
                            <input v-model="form.kode_rekening" type="text" placeholder="5.1.02.01.01.0099" required class="w-full rounded-lg border-hairline text-sm py-2 px-3 font-mono" />
                            <p v-if="formErrors.kode_rekening" class="mt-1 text-xs text-rose-600">{{ formErrors.kode_rekening[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink uppercase mb-1">Nama / Uraian Rekening <span class="text-rose-500">*</span></label>
                            <input v-model="form.nama_rekening" type="text" placeholder="Belanja Alat Tulis Kantor" required class="w-full rounded-lg border-hairline text-sm py-2 px-3" />
                            <p v-if="formErrors.nama_rekening" class="mt-1 text-xs text-rose-600">{{ formErrors.nama_rekening[0] }}</p>
                        </div>
                    </div>

                    <!-- Alokasi 12 Bulan -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase text-ink">Alokasi Pagu Kas 12 Bulan <span class="text-rose-500">*</span></span>
                            <span class="text-sm font-bold text-ink">
                                Total Tahunan: <span class="text-emerald-700 tabular-nums">{{ formatRupiah(totalAlokasi) }}</span>
                            </span>
                        </div>
                        <p v-if="formErrors.alokasi" class="text-xs text-rose-600">{{ formErrors.alokasi[0] }}</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <GovCurrencyInput
                                v-for="(bulan, idx) in NAMA_BULAN"
                                :key="idx"
                                v-model="form.alokasi[idx]"
                                :label="`${idx + 1}. ${bulan}`"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-hairline">
                        <GovButton type="button" variant="secondary" :disabled="saving" @click="showCreateModal = false">
                            Batal
                        </GovButton>
                        <GovButton type="submit" variant="gold" :disabled="saving">
                            {{ saving ? 'Menyimpan...' : 'Simpan Kode Rekening' }}
                        </GovButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit Pagu Bulanan -->
        <div v-if="showEditModal && editingRekening" class="fixed inset-0 z-50 bg-ink/40 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-surface rounded-xl shadow-floating max-w-4xl w-full max-h-[92vh] overflow-y-auto scrollbar-thin p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-hairline pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-ink">Edit Alokasi Pagu Bulanan</h3>
                        <p class="text-xs text-ink-muted font-mono">
                            {{ editingRekening.kode_rekening }} — {{ editingRekening.nama_rekening }}
                        </p>
                    </div>
                    <button type="button" class="text-ink-muted hover:text-ink-secondary text-xl leading-none" @click="showEditModal = false" aria-label="Tutup"><X class="w-4 h-4" :stroke-width="1.5" /></button>
                </div>

                <div v-if="editErrorGeneral" class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 font-medium">
                    {{ editErrorGeneral }}
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase text-ink">Rincian Alokasi 12 Bulan</span>
                            <p class="text-xs text-ink-muted">
                                Validasi minimum dihitung secara kumulatif dari Januari hingga bulan berjalan.
                            </p>
                        </div>
                        <span class="text-sm font-bold text-ink">
                            Total Pagu Baru: <span class="text-emerald-700 tabular-nums">{{ formatRupiah(totalEditAlokasi) }}</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div v-for="(bulan, idx) in NAMA_BULAN" :key="idx" class="space-y-1">
                            <GovCurrencyInput
                                v-model="editAlokasi[idx]"
                                :label="`${idx + 1}. ${bulan}`"
                            />
                            <p
                                class="text-xs tabular-nums"
                                :class="Math.round(editCumulativeAlokasi[idx] * 100) < Math.round(editCumulativeMin[idx] * 100) ? 'text-rose-600 font-semibold' : 'text-ink-muted'"
                            >
                                Terpakai bln ini: <span class="font-semibold">{{ formatRupiah(editMinRequired[idx]) }}</span>
                                · Min. kumulatif: <span class="font-semibold">{{ formatRupiah(editCumulativeMin[idx]) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-hairline">
                        <GovButton type="button" variant="secondary" :disabled="savingEdit" @click="showEditModal = false">
                            Batal
                        </GovButton>
                        <GovButton type="submit" variant="gold" :disabled="savingEdit">
                            {{ savingEdit ? 'Menyimpan...' : 'Simpan Perubahan Pagu' }}
                        </GovButton>
                    </div>
                </form>
            </div>
        </div>
    </GovAppLayout>
</template>
