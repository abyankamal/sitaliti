<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { sppdService } from '@/Services/sppdService';
import { budgetService } from '@/Services/budgetService';
import { Plus, Search, Pencil, Trash2, X, Users, Power } from 'lucide-vue-next';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSkpdWide = computed(() => ['VERIFIKATOR', 'KPA', 'BP'].includes(currentUser.value?.role));

// State Data
const pegawaiList = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const units = ref([]);
const loading = ref(false);

// State Filter
const searchQuery = ref('');
const filterUnitId = ref('');
const filterStatus = ref('');

// State Toast Non-Intrusif
const toast = ref({ show: false, type: 'success', message: '' });
let toastTimer = null;

function showToast(message, type = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { show: true, type, message };
    toastTimer = setTimeout(() => {
        toast.value.show = false;
    }, 4500);
}

// State Modal Form (<dialog>)
const formDialogRef = ref(null);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formErrors = ref({});
const formErrorGeneral = ref('');

const form = ref({
    nip: '',
    nama: '',
    jabatan: '',
    golongan: '',
    unit_kerja_id: '',
    is_active: true,
});

// State Modal Konfirmasi Hapus (<dialog>)
const deleteDialogRef = ref(null);
const pegawaiToDelete = ref(null);
const deleting = ref(false);
const deleteError = ref('');

async function loadUnits() {
    if (!isSkpdWide.value) return;
    try {
        units.value = await budgetService.getUnits();
    } catch {
        units.value = [];
    }
}

async function fetchPegawai(pageNumber = 1) {
    loading.value = true;
    try {
        const params = { page: pageNumber, per_page: 15 };
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        if (isSkpdWide.value && filterUnitId.value) params.unit_kerja_id = filterUnitId.value;
        if (filterStatus.value !== '') params.is_active = filterStatus.value;

        const res = await sppdService.getPegawaiList(params);
        pegawaiList.value = res.data ?? [];
        pagination.value = {
            current_page: res.current_page ?? 1,
            last_page: res.last_page ?? 1,
            total: res.total ?? 0,
        };
    } catch {
        showToast('Gagal memuat daftar master pegawai.', 'error');
    } finally {
        loading.value = false;
    }
}

function openCreateModal() {
    isEditing.value = false;
    editingId.value = null;
    formErrors.value = {};
    formErrorGeneral.value = '';
    form.value = {
        nip: '',
        nama: '',
        jabatan: '',
        golongan: '',
        unit_kerja_id: isSkpdWide.value ? '' : (currentUser.value?.unit_kerja_id ?? ''),
        is_active: true,
    };
    formDialogRef.value?.showModal();
}

function openEditModal(item) {
    isEditing.value = true;
    editingId.value = item.id;
    formErrors.value = {};
    formErrorGeneral.value = '';
    form.value = {
        nip: item.nip || '',
        nama: item.nama || '',
        jabatan: item.jabatan || '',
        golongan: item.golongan || '',
        unit_kerja_id: item.unit_kerja_id ?? (isSkpdWide.value ? '' : (currentUser.value?.unit_kerja_id ?? '')),
        is_active: Boolean(item.is_active),
    };
    formDialogRef.value?.showModal();
}

function closeFormModal() {
    formDialogRef.value?.close();
}

async function submitForm() {
    saving.value = true;
    formErrors.value = {};
    formErrorGeneral.value = '';

    const payload = {
        nip: form.value.nip,
        nama: form.value.nama,
        jabatan: form.value.jabatan || null,
        golongan: form.value.golongan || null,
        unit_kerja_id: isSkpdWide.value
            ? (form.value.unit_kerja_id ? Number(form.value.unit_kerja_id) : null)
            : currentUser.value?.unit_kerja_id,
        is_active: Boolean(form.value.is_active),
    };

    try {
        if (isEditing.value && editingId.value) {
            await sppdService.updatePegawai(editingId.value, payload);
            showToast('Data pegawai berhasil diperbarui.');
        } else {
            await sppdService.createPegawai(payload);
            showToast('Data pegawai berhasil ditambahkan.');
        }
        closeFormModal();
        await fetchPegawai(pagination.value.current_page);
    } catch (err) {
        const resData = err.response?.data;
        if (resData?.errors) {
            formErrors.value = resData.errors;
        }
        formErrorGeneral.value = resData?.error?.message || resData?.message || 'Gagal menyimpan data pegawai.';
    } finally {
        saving.value = false;
    }
}

async function toggleActiveStatus(item) {
    try {
        await sppdService.updatePegawai(item.id, {
            nip: item.nip,
            nama: item.nama,
            jabatan: item.jabatan,
            golongan: item.golongan,
            unit_kerja_id: item.unit_kerja_id,
            is_active: !item.is_active,
        });
        showToast(item.is_active ? 'Pegawai dinonaktifkan.' : 'Pegawai diaktifkan kembali.');
        await fetchPegawai(pagination.value.current_page);
    } catch (err) {
        const msg = err.response?.data?.error?.message || 'Gagal mengubah status pegawai.';
        showToast(msg, 'error');
    }
}

function openDeleteModal(item) {
    pegawaiToDelete.value = item;
    deleteError.value = '';
    deleteDialogRef.value?.showModal();
}

function closeDeleteModal() {
    deleteDialogRef.value?.close();
    pegawaiToDelete.value = null;
}

async function confirmDelete() {
    if (!pegawaiToDelete.value) return;
    deleting.value = true;
    deleteError.value = '';
    try {
        await sppdService.deletePegawai(pegawaiToDelete.value.id);
        showToast('Data pegawai berhasil dihapus.');
        closeDeleteModal();
        await fetchPegawai(pagination.value.current_page);
    } catch (err) {
        deleteError.value = err.response?.data?.error?.message || err.response?.data?.message || 'Gagal menghapus data pegawai.';
    } finally {
        deleting.value = false;
    }
}

function resetFilters() {
    searchQuery.value = '';
    filterUnitId.value = '';
    filterStatus.value = '';
    fetchPegawai(1);
}

onMounted(async () => {
    await Promise.all([loadUnits(), fetchPegawai(1)]);
});
</script>

<template>
    <Head title="Master Pegawai — E-Verifikatur" />

    <GovAppLayout title="Master Pegawai">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-ink flex items-center gap-2">
                        <Users class="w-5 h-5 text-accent" :stroke-width="1.75" />
                        <span>Master Pegawai (Pelaksana SPPD)</span>
                    </h1>
                    <p class="text-xs text-ink-muted mt-0.5">
                        Daftar referensi pegawai untuk pemilihan pelaksana perjalanan dinas dan validasi jadwal anti-bentrok.
                    </p>
                </div>

                <GovButton size="sm" @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    <span>Tambah Pegawai</span>
                </GovButton>
            </div>
        </template>

        <!-- Toast Notifikasi -->
        <div
            v-if="toast.show"
            role="status"
            class="mb-4 px-4 py-2.5 rounded-lg border text-xs font-medium flex items-center justify-between"
            :class="toast.type === 'error'
                ? 'bg-red-50 border-red-200 text-semantic-danger'
                : 'bg-emerald-50 border-emerald-200 text-semantic-success'"
        >
            <span>{{ toast.message }}</span>
            <button type="button" class="p-1 hover:opacity-75" aria-label="Tutup notifikasi" @click="toast.show = false">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="bg-surface border border-hairline rounded-xl p-4 mb-4">
            <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end" @submit.prevent="fetchPegawai(1)">
                <div>
                    <label for="pegawai-search" class="block text-xs font-medium text-ink-secondary mb-1">Cari Nama / NIP / Jabatan</label>
                    <div class="relative">
                        <Search class="w-3.5 h-3.5 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            id="pegawai-search"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Ketik nama atau NIP..."
                            class="w-full h-9 pl-8 pr-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                        />
                    </div>
                </div>

                <div v-if="isSkpdWide">
                    <label for="pegawai-filter-unit" class="block text-xs font-medium text-ink-secondary mb-1">Unit Kerja</label>
                    <select
                        id="pegawai-filter-unit"
                        v-model="filterUnitId"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                    >
                        <option value="">Semua Unit Kerja</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.nama_unit }}</option>
                    </select>
                </div>

                <div>
                    <label for="pegawai-filter-status" class="block text-xs font-medium text-ink-secondary mb-1">Status</label>
                    <select
                        id="pegawai-filter-status"
                        v-model="filterStatus"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                    >
                        <option value="">Semua Status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <GovButton type="submit" size="sm">Terapkan</GovButton>
                    <GovButton type="button" variant="secondary" size="sm" @click="resetFilters">Reset</GovButton>
                </div>
            </form>
        </div>

        <!-- Tabel Master Pegawai -->
        <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
            <div tabindex="0" role="region" aria-labelledby="pegawai-table-caption" class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption id="pegawai-table-caption" class="sr-only">Daftar Master Pegawai Pelaksana SPPD</caption>
                    <thead>
                        <tr class="bg-canvas border-b border-hairline text-[11px] font-semibold uppercase tracking-wider text-ink-secondary">
                            <th scope="col" class="px-4 py-2.5">NIP / NIK</th>
                            <th scope="col" class="px-4 py-2.5">Nama Pegawai</th>
                            <th scope="col" class="px-4 py-2.5">Jabatan</th>
                            <th scope="col" class="px-4 py-2.5">Golongan</th>
                            <th scope="col" class="px-4 py-2.5">Unit Kerja</th>
                            <th scope="col" class="px-4 py-2.5 text-center">Status</th>
                            <th scope="col" class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline text-xs">
                        <tr v-if="loading">
                            <td colspan="7" class="px-4 py-8 text-center text-ink-muted">Memuat data pegawai...</td>
                        </tr>
                        <tr v-else-if="pegawaiList.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-ink-muted">
                                Belum ada data pegawai yang cocok dengan filter.
                            </td>
                        </tr>
                        <tr v-for="item in pegawaiList" v-else :key="item.id" class="hover:bg-canvas/60 transition-colors">
                            <td class="px-4 py-2.5 font-mono tabular-nums text-ink whitespace-nowrap">{{ item.nip }}</td>
                            <td class="px-4 py-2.5 font-medium text-ink">{{ item.nama }}</td>
                            <td class="px-4 py-2.5 text-ink-secondary">{{ item.jabatan || '—' }}</td>
                            <td class="px-4 py-2.5 text-ink-secondary">{{ item.golongan || '—' }}</td>
                            <td class="px-4 py-2.5 text-ink-secondary">{{ item.unit_kerja?.nama_unit || 'Umum / Lintas Unit' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <GovBadge :variant="item.is_active ? 'success' : 'neutral'" size="sm">
                                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                </GovBadge>
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-md text-ink-secondary hover:text-accent hover:bg-accent-soft transition-colors"
                                        title="Edit Pegawai"
                                        @click="openEditModal(item)"
                                    >
                                        <Pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-md text-ink-secondary hover:text-semantic-warning hover:bg-amber-50 transition-colors"
                                        :title="item.is_active ? 'Nonaktifkan Pegawai' : 'Aktifkan Pegawai'"
                                        @click="toggleActiveStatus(item)"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        v-if="(item.sppd_peserta_count ?? 0) === 0"
                                        type="button"
                                        class="p-1.5 rounded-md text-ink-secondary hover:text-semantic-danger hover:bg-red-50 transition-colors"
                                        title="Hapus Pegawai"
                                        @click="openDeleteModal(item)"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-hairline bg-canvas flex items-center justify-between text-xs text-ink-secondary">
                <span class="tabular-nums">Total {{ pagination.total }} pegawai • Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <div class="flex items-center gap-2">
                    <GovButton
                        size="sm"
                        variant="secondary"
                        :disabled="pagination.current_page <= 1"
                        @click="fetchPegawai(pagination.current_page - 1)"
                    >
                        Sebelumnya
                    </GovButton>
                    <GovButton
                        size="sm"
                        variant="secondary"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="fetchPegawai(pagination.current_page + 1)"
                    >
                        Berikutnya
                    </GovButton>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit Pegawai (Native <dialog>) -->
        <dialog
            ref="formDialogRef"
            aria-labelledby="pegawai-modal-title"
            class="sppd-dialog w-full max-w-lg rounded-xl bg-surface text-ink border border-hairline p-0 shadow-xl backdrop:bg-slate-900/50"
        >
            <div class="px-5 py-3.5 border-b border-hairline flex items-center justify-between">
                <h2 id="pegawai-modal-title" class="text-sm font-semibold text-ink">
                    {{ isEditing ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru' }}
                </h2>
                <button
                    type="button"
                    class="p-1 rounded-md text-ink-muted hover:text-ink hover:bg-raised"
                    aria-label="Tutup modal"
                    @click="closeFormModal"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <form class="p-5 space-y-3.5" @submit.prevent="submitForm">
                <div v-if="formErrorGeneral" class="p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-semantic-danger">
                    {{ formErrorGeneral }}
                </div>

                <div>
                    <label for="form-nip" class="block text-xs font-medium text-ink-secondary mb-1">NIP / NIK <span class="text-semantic-danger">*</span></label>
                    <input
                        id="form-nip"
                        v-model="form.nip"
                        type="text"
                        required
                        autofocus
                        placeholder="Contoh: 198501012010011001"
                        class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                    />
                    <p v-if="formErrors.nip" class="text-[11px] text-semantic-danger mt-1">{{ formErrors.nip[0] }}</p>
                </div>

                <div>
                    <label for="form-nama" class="block text-xs font-medium text-ink-secondary mb-1">Nama Lengkap <span class="text-semantic-danger">*</span></label>
                    <input
                        id="form-nama"
                        v-model="form.nama"
                        type="text"
                        required
                        placeholder="Nama lengkap beserta gelar"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                    />
                    <p v-if="formErrors.nama" class="text-[11px] text-semantic-danger mt-1">{{ formErrors.nama[0] }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="form-jabatan" class="block text-xs font-medium text-ink-secondary mb-1">Jabatan</label>
                        <input
                            id="form-jabatan"
                            v-model="form.jabatan"
                            type="text"
                            placeholder="Contoh: Analis Keuangan"
                            class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                        />
                    </div>
                    <div>
                        <label for="form-golongan" class="block text-xs font-medium text-ink-secondary mb-1">Golongan / Pangkat</label>
                        <input
                            id="form-golongan"
                            v-model="form.golongan"
                            type="text"
                            placeholder="Contoh: III/c"
                            class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                        />
                    </div>
                </div>

                <div v-if="isSkpdWide">
                    <label for="form-unit" class="block text-xs font-medium text-ink-secondary mb-1">Unit Kerja</label>
                    <select
                        id="form-unit"
                        v-model="form.unit_kerja_id"
                        class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                    >
                        <option value="">Umum / Lintas Unit Kerja</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.nama_unit }}</option>
                    </select>
                </div>
                <div v-else class="text-xs text-ink-muted bg-canvas border border-hairline rounded-lg px-3 py-2">
                    Unit Kerja: <span class="font-medium text-ink">{{ currentUser.unit_kerja?.nama_unit || 'Unit Kerja Anda' }}</span>
                </div>

                <label class="flex items-center gap-2 text-xs text-ink-secondary pt-1">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-hairline text-accent focus:ring-accent" />
                    <span>Pegawai aktif (dapat dipilih pada SPPD baru)</span>
                </label>

                <div class="pt-3 border-t border-hairline flex items-center justify-end gap-2">
                    <GovButton type="button" variant="secondary" size="sm" @click="closeFormModal">Batal</GovButton>
                    <GovButton type="submit" size="sm" :loading="saving">
                        {{ isEditing ? 'Simpan Perubahan' : 'Simpan Pegawai' }}
                    </GovButton>
                </div>
            </form>
        </dialog>

        <!-- Modal Konfirmasi Hapus (<dialog>) -->
        <dialog
            ref="deleteDialogRef"
            aria-labelledby="delete-modal-title"
            class="sppd-dialog w-full max-w-md rounded-xl bg-surface text-ink border border-hairline p-0 shadow-xl backdrop:bg-slate-900/50"
        >
            <div class="p-5 space-y-3">
                <h2 id="delete-modal-title" class="text-sm font-semibold text-ink">Hapus Data Pegawai?</h2>
                <p class="text-xs text-ink-secondary">
                    Anda akan menghapus pegawai <strong class="text-ink">{{ pegawaiToDelete?.nama }}</strong>
                    (<span class="font-mono">{{ pegawaiToDelete?.nip }}</span>). Tindakan ini tidak dapat dibatalkan.
                </p>
                <div v-if="deleteError" class="p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-semantic-danger">
                    {{ deleteError }}
                </div>
                <div class="pt-2 flex items-center justify-end gap-2">
                    <GovButton type="button" variant="secondary" size="sm" autofocus @click="closeDeleteModal">Batal</GovButton>
                    <GovButton type="button" variant="danger" size="sm" :loading="deleting" @click="confirmDelete">Hapus</GovButton>
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
