<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

const ROLES = [
    { value: 'BPP', label: 'BPP Kelurahan' },
    { value: 'BP', label: 'Bendahara Pengeluaran (BP Kecamatan)' },
    { value: 'PPTK', label: 'PPTK' },
    { value: 'LURAH', label: 'Lurah (Monitoring)' },
    { value: 'VERIFIKATOR', label: 'Kasubag Keuangan (Verifikator)' },
    { value: 'KPA', label: 'Camat / KPA (Monitoring)' },
];

const ROLE_BADGE_MAP = {
    BPP: 'emerald',
    BP: 'emerald',
    PPTK: 'indigo',
    LURAH: 'amber',
    VERIFIKATOR: 'navy',
    KPA: 'navy',
};

// State Data
const users = ref([]);
const units = ref([]);
const subActivities = ref([]);
const loading = ref(false);
const loadingSubActivities = ref(false);

// State Filter
const searchQuery = ref('');
const filterUnitId = ref('');
const filterRole = ref('');

const toast = useToast();
const { confirm } = useConfirm();

// State Modal Form Pengguna
const showFormModal = ref(false);
const isEditing = ref(false);
const editingUserId = ref(null);
const saving = ref(false);
const formErrors = ref({});
const formErrorGeneral = ref('');

const form = ref({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'BPP',
    unit_kerja_id: '',
    nip: '',
    jabatan: '',
    sub_kegiatan_codes: [],
});

// State Modal Hapus Pengguna
const showDeleteModal = ref(false);
const userToDelete = ref(null);
const deleting = ref(false);
const deleteError = ref('');

async function fetchUnits() {
    try {
        const res = await window.axios.get('/api/v1/budget/units');
        units.value = res.data?.data ?? [];
    } catch {
        units.value = [];
    }
}

async function fetchUsers() {
    loading.value = true;
    try {
        const params = {};
        if (searchQuery.value.trim()) params.q = searchQuery.value.trim();
        if (filterUnitId.value) params.unit_kerja_id = filterUnitId.value;
        if (filterRole.value) params.role = filterRole.value;

        const res = await window.axios.get('/api/v1/users', { params });
        users.value = res.data?.data ?? [];
    } catch {
        toast.error('Gagal memuat daftar pengguna.');
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
        const res = await window.axios.get('/api/v1/users/sub-activities-options', {
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
    () => [form.value.role, form.value.unit_kerja_id],
    ([role, unitId]) => {
        if (showFormModal.value && role === 'PPTK' && unitId) {
            fetchSubActivities(unitId);
        } else if (role !== 'PPTK') {
            subActivities.value = [];
        }
    }
);

let searchDebounce = null;
watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(fetchUsers, 300);
});

watch([filterUnitId, filterRole], () => {
    fetchUsers();
});

function resetFilters() {
    searchQuery.value = '';
    filterUnitId.value = '';
    filterRole.value = '';
}

function openCreateModal() {
    isEditing.value = false;
    editingUserId.value = null;
    formErrors.value = {};
    formErrorGeneral.value = '';
    form.value = {
        name: '',
        username: '',
        email: '',
        password: '',
        role: 'BPP',
        unit_kerja_id: units.value[0]?.id ?? '',
        nip: '',
        jabatan: '',
        sub_kegiatan_codes: [],
    };
    showFormModal.value = true;
}

function openEditModal(user) {
    isEditing.value = true;
    editingUserId.value = user.id;
    formErrors.value = {};
    formErrorGeneral.value = '';
    const assignedCodes = (user.pptk_subkegiatan_assignments ?? []).map(
        (item) => item.kode_sub_kegiatan
    );
    form.value = {
        name: user.name ?? '',
        username: user.username ?? '',
        email: user.email ?? '',
        password: '',
        role: user.role ?? 'BPP',
        unit_kerja_id: user.unit_kerja_id ?? '',
        nip: user.nip ?? '',
        jabatan: user.jabatan ?? '',
        sub_kegiatan_codes: assignedCodes,
    };
    showFormModal.value = true;
    if (form.value.role === 'PPTK' && form.value.unit_kerja_id) {
        fetchSubActivities(form.value.unit_kerja_id);
    }
}

function closeFormModal() {
    showFormModal.value = false;
}

function toggleSubKegiatan(code) {
    const idx = form.value.sub_kegiatan_codes.indexOf(code);
    if (idx === -1) {
        form.value.sub_kegiatan_codes.push(code);
    } else {
        form.value.sub_kegiatan_codes.splice(idx, 1);
    }
}

async function submitForm() {
    saving.value = true;
    formErrors.value = {};
    formErrorGeneral.value = '';

    const payload = {
        name: form.value.name,
        username: form.value.username,
        email: form.value.email || null,
        role: form.value.role,
        unit_kerja_id: form.value.unit_kerja_id,
        nip: form.value.nip || null,
        jabatan: form.value.jabatan || null,
        sub_kegiatan_codes: form.value.role === 'PPTK' ? form.value.sub_kegiatan_codes : [],
    };
    if (form.value.password) {
        payload.password = form.value.password;
    }

    try {
        if (isEditing.value) {
            const res = await window.axios.put(`/api/v1/users/${editingUserId.value}`, payload);
            toast.success(res.data?.message || 'Data pengguna berhasil diperbarui.');
        } else {
            const res = await window.axios.post('/api/v1/users', payload);
            toast.success(res.data?.message || 'Pengguna baru berhasil ditambahkan.');
        }
        showFormModal.value = false;
        await fetchUsers();
    } catch (err) {
        const resData = err.response?.data;
        if (err.response?.status === 422 && resData?.errors) {
            formErrors.value = resData.errors;
            formErrorGeneral.value =
                resData?.error?.message || resData?.message || 'Periksa kembali isian formulir.';
        } else {
            formErrorGeneral.value =
                resData?.error?.message || resData?.message || 'Terjadi kesalahan saat menyimpan pengguna.';
        }
    } finally {
        saving.value = false;
    }
}

async function openDeleteModal(user) {
    await confirm({
        title: 'Konfirmasi Hapus Pengguna',
        message: `Apakah Anda yakin ingin menghapus akun ${user.name}? Demi integritas jejak audit BPK, pengguna yang pernah tercatat dalam riwayat kuitansi tidak dapat dihapus.`,
        details: `@${user.username}`,
        variant: 'danger',
        confirmText: 'Ya, Hapus Pengguna',
        onConfirm: async () => {
            const res = await window.axios.delete(`/api/v1/users/${user.id}`);
            toast.success(res.data?.message || 'Pengguna berhasil dihapus.');
            await fetchUsers();
        },
    });
}

onMounted(async () => {
    await Promise.all([fetchUnits(), fetchUsers()]);
});
</script>

<template>
    <Head title="Kelola Pengguna & Penugasan PPTK" />

    <GovAppLayout>
        <div class="space-y-4">
            <!-- Header Halaman -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-gov-text-main">
                        Kelola Pengguna &amp; Penugasan PPTK
                    </h1>
                    <p class="text-xs text-gov-text-sub">
                        Pengaturan akun operator, PPTK, verifikator, bendahara, dan penugasan sub-kegiatan unit kerja.
                    </p>
                </div>
                <GovButton variant="primary" size="sm" @click="openCreateModal">
                    + Tambah Pengguna
                </GovButton>
            </div>

            <!-- Filter Bar -->
            <div class="rounded-lg border border-gov-border bg-gov-card p-3 shadow-xs">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-ink-secondary">
                            Cari Nama / Email / NIP / Jabatan
                        </label>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Ketik kata kunci pencarian..."
                            class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs text-gov-text-main focus:border-gov-navy-700 focus:outline-none focus:ring-1 focus:ring-gov-navy-700"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-ink-secondary">
                            Unit Kerja
                        </label>
                        <select
                            v-model="filterUnitId"
                            class="w-full rounded-md border border-gov-border px-2.5 py-1.5 text-xs text-gov-text-main focus:border-gov-navy-700 focus:outline-none focus:ring-1 focus:ring-gov-navy-700"
                        >
                            <option value="">Semua Unit Kerja</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                {{ unit.nama_unit }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-ink-secondary">
                            Peran
                        </label>
                        <div class="flex gap-2">
                            <select
                                v-model="filterRole"
                                class="w-full rounded-md border border-gov-border px-2.5 py-1.5 text-xs text-gov-text-main focus:border-gov-navy-700 focus:outline-none focus:ring-1 focus:ring-gov-navy-700"
                            >
                                <option value="">Semua Peran</option>
                                <option v-for="r in ROLES" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </option>
                            </select>
                            <button
                                v-if="searchQuery || filterUnitId || filterRole"
                                type="button"
                                class="shrink-0 rounded-md border border-gov-border px-2.5 py-1.5 text-xs font-medium text-gov-text-sub hover:bg-slate-50"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Pengguna Kompak -->
            <div class="overflow-hidden rounded-lg border border-gov-border bg-gov-card shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gov-border text-left text-xs">
                        <thead class="bg-slate-50 text-xs font-semibold  text-gov-text-sub">
                            <tr>
                                <th class="px-4 py-2.5">Nama &amp; Email</th>
                                <th class="px-4 py-2.5">NIP &amp; Jabatan</th>
                                <th class="px-4 py-2.5">Unit Kerja</th>
                                <th class="px-4 py-2.5">Peran</th>
                                <th class="px-4 py-2.5">Sub-Kegiatan PPTK</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gov-border bg-white">
                            <tr v-if="loading">
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-gov-text-sub">
                                    Memuat daftar pengguna...
                                </td>
                            </tr>
                            <tr v-else-if="users.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-gov-text-sub">
                                    Tidak ada pengguna yang sesuai dengan kriteria pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="u in users"
                                v-else
                                :key="u.id"
                                class="hover:bg-slate-50/70"
                            >
                                <td class="px-4 py-2.5">
                                    <div class="font-semibold text-gov-text-main">{{ u.name }}</div>
                                    <div class="font-mono text-xs text-gov-text-sub">@{{ u.username }}<span v-if="u.email"> · {{ u.email }}</span></div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="font-mono text-xs tabular-nums text-gov-text-main">
                                        {{ u.nip || '-' }}
                                    </div>
                                    <div class="text-xs text-gov-text-sub">{{ u.jabatan || '-' }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-gov-text-main">
                                    {{ u.unit_kerja?.nama_unit || '-' }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <GovBadge :color="ROLE_BADGE_MAP[u.role] || 'slate'">
                                        {{ u.role }}
                                    </GovBadge>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div
                                        v-if="u.role === 'PPTK' && u.pptk_subkegiatan_assignments?.length"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="assign in u.pptk_subkegiatan_assignments"
                                            :key="assign.id"
                                            class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-xs tabular-nums text-slate-700"
                                        >
                                            {{ assign.kode_sub_kegiatan }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-gov-border px-2.5 py-1 text-xs font-semibold text-gov-navy-900 hover:bg-slate-100"
                                            @click="openEditModal(u)"
                                        >
                                            Ubah
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="currentUser?.id === u.id"
                                            :class="[
                                                'rounded border px-2.5 py-1 text-xs font-semibold',
                                                currentUser?.id === u.id
                                                    ? 'cursor-not-allowed border-slate-200 text-slate-300'
                                                    : 'border-red-200 text-red-700 hover:bg-red-50',
                                            ]"
                                            @click="openDeleteModal(u)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Ubah Pengguna -->
        <div
            v-if="showFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            @click.self="closeFormModal"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-lg border border-gov-border bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gov-border bg-gov-navy-900 px-5 py-3 text-white">
                    <h2 class="text-sm font-semibold">
                        {{ isEditing ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}
                    </h2>
                    <button
                        type="button"
                        class="text-slate-300 hover:text-white"
                        @click="closeFormModal"
                    >
                        &times;
                    </button>
                </div>

                <form class="max-h-[80vh] space-y-4 overflow-y-auto p-5" @submit.prevent="submitForm">
                    <div
                        v-if="formErrorGeneral"
                        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
                    >
                        {{ formErrorGeneral }}
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Nama Lengkap <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            />
                            <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">
                                {{ formErrors.name[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Username Login <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.username"
                                type="text"
                                required
                                placeholder="Contoh: operator.pataruman"
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 font-mono text-xs focus:border-gov-navy-700 focus:outline-none"
                            />
                            <p v-if="formErrors.username" class="mt-1 text-xs text-red-600">
                                {{ formErrors.username[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Email Dinas <span class="text-xs text-slate-400">(Opsional)</span>
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            />
                            <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">
                                {{ formErrors.email[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Kata Sandi
                                <span v-if="!isEditing" class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                :required="!isEditing"
                                :placeholder="isEditing ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'"
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            />
                            <p v-if="formErrors.password" class="mt-1 text-xs text-red-600">
                                {{ formErrors.password[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Peran Sistem <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.role"
                                required
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            >
                                <option v-for="r in ROLES" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </option>
                            </select>
                            <p v-if="formErrors.role" class="mt-1 text-xs text-red-600">
                                {{ formErrors.role[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Unit Kerja <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.unit_kerja_id"
                                required
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            >
                                <option value="" disabled>Pilih Unit Kerja</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                    {{ unit.nama_unit }}
                                </option>
                            </select>
                            <p v-if="formErrors.unit_kerja_id" class="mt-1 text-xs text-red-600">
                                {{ formErrors.unit_kerja_id[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                NIP
                            </label>
                            <input
                                v-model="form.nip"
                                type="text"
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 font-mono text-xs tabular-nums focus:border-gov-navy-700 focus:outline-none"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gov-text-sub">
                                Jabatan
                            </label>
                            <input
                                v-model="form.jabatan"
                                type="text"
                                class="w-full rounded-md border border-gov-border px-3 py-1.5 text-xs focus:border-gov-navy-700 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Checklist Penugasan Sub-Kegiatan khusus Peran PPTK -->
                    <div
                        v-if="form.role === 'PPTK'"
                        class="rounded-md border border-gov-border bg-slate-50 p-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gov-navy-900">
                                Penugasan Sub-Kegiatan PPTK
                            </span>
                            <span class="font-mono text-xs tabular-nums text-gov-text-sub">
                                {{ form.sub_kegiatan_codes.length }} dipilih
                            </span>
                        </div>

                        <div v-if="loadingSubActivities" class="py-2 text-xs text-gov-text-sub">
                            Memuat daftar sub-kegiatan unit...
                        </div>
                        <div
                            v-else-if="subActivities.length === 0"
                            class="py-2 text-xs text-gov-text-sub"
                        >
                            Belum ada sub-kegiatan RAK pada unit kerja ini.
                        </div>
                        <div v-else class="max-h-44 space-y-1.5 overflow-y-auto pr-1">
                            <label
                                v-for="sa in subActivities"
                                :key="sa.kode_sub_kegiatan"
                                class="flex cursor-pointer items-start gap-2 rounded border border-gov-border bg-white px-2.5 py-1.5 text-xs hover:bg-slate-50"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.sub_kegiatan_codes.includes(sa.kode_sub_kegiatan)"
                                    class="mt-0.5 rounded border-slate-300 text-gov-navy-900 focus:ring-gov-navy-700"
                                    @change="toggleSubKegiatan(sa.kode_sub_kegiatan)"
                                />
                                <div>
                                    <div class="font-mono text-xs font-semibold tabular-nums text-gov-navy-900">
                                        {{ sa.kode_sub_kegiatan }}
                                    </div>
                                    <div class="text-xs text-gov-text-main">
                                        {{ sa.nama_sub_kegiatan }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gov-border pt-3">
                        <GovButton type="button" variant="secondary" size="sm" @click="closeFormModal">
                            Batal
                        </GovButton>
                        <GovButton type="submit" variant="primary" size="sm" :disabled="saving">
                            {{ saving ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
                        </GovButton>
                    </div>
                </form>
            </div>
        </div>
    </GovAppLayout>
</template>
