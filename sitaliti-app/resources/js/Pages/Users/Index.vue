<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

function handleEscapeKey(event) {
    if (event.key === 'Escape' && showFormModal.value) {
        closeFormModal();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscapeKey);
});

const ROLES = [
    { value: 'BPP', label: 'BPP Kelurahan' },
    { value: 'BP', label: 'Bendahara Pengeluaran (BP Kecamatan)' },
    { value: 'PPTK', label: 'PPTK' },
    { value: 'LURAH', label: 'Lurah (Monitoring)' },
    { value: 'VERIFIKATOR', label: 'Kasubag Keuangan (Verifikator)' },
    { value: 'KPA', label: 'Camat / KPA (Monitoring)' },
];

const ROLE_BADGE_MAP = {
    BPP: 'success',
    BP: 'success',
    PPTK: 'info',
    LURAH: 'warning',
    VERIFIKATOR: 'primary',
    KPA: 'primary',
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
                    <h1 class="text-lg font-bold tracking-tight text-ink">
                        Kelola Pengguna &amp; Penugasan PPTK
                    </h1>
                    <p class="text-xs text-ink-muted">
                        Pengaturan akun operator, PPTK, verifikator, bendahara, dan penugasan sub-kegiatan unit kerja.
                    </p>
                </div>
                <GovButton variant="primary" size="sm" @click="openCreateModal">
                    + Tambah Pengguna
                </GovButton>
            </div>

            <!-- Filter Bar -->
            <div class="rounded-lg border border-hairline bg-surface p-3 shadow-subtle">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-ink-secondary">
                            Cari Nama / Email / NIP / Jabatan
                        </label>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Ketik kata kunci pencarian..."
                            class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-ink-secondary">
                            Unit Kerja
                        </label>
                        <select
                            v-model="filterUnitId"
                            class="w-full rounded-md border border-hairline px-2.5 py-1.5 text-xs text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        >
                            <option value="">Semua Unit Kerja</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                {{ unit.name || unit.nama_unit }}
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
                                class="w-full rounded-md border border-hairline px-2.5 py-1.5 text-xs text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                            >
                                <option value="">Semua Peran</option>
                                <option v-for="r in ROLES" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </option>
                            </select>
                            <button
                                v-if="searchQuery || filterUnitId || filterRole"
                                type="button"
                                class="shrink-0 rounded-md border border-hairline px-2.5 py-1.5 text-xs font-medium text-ink-muted hover:bg-raised"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Pengguna Kompak -->
            <div class="overflow-hidden rounded-lg border border-hairline bg-surface shadow-subtle">
                <div class="overflow-x-auto scrollbar-thin">
                    <table class="min-w-full divide-y divide-hairline text-left text-xs">
                        <thead class="bg-raised text-xs font-semibold  text-ink-muted">
                            <tr>
                                <th class="px-4 py-2.5">Nama &amp; Email</th>
                                <th class="px-4 py-2.5">NIP &amp; Jabatan</th>
                                <th class="px-4 py-2.5">Unit Kerja</th>
                                <th class="px-4 py-2.5">Peran</th>
                                <th class="px-4 py-2.5">Sub-Kegiatan PPTK</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline bg-surface">
                            <tr v-if="loading">
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-ink-muted">
                                    Memuat daftar pengguna...
                                </td>
                            </tr>
                            <tr v-else-if="users.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-ink-muted">
                                    Tidak ada pengguna yang sesuai dengan kriteria pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="u in users"
                                v-else
                                :key="u.id"
                                class="hover:bg-raised/70"
                            >
                                <td class="px-4 py-2.5">
                                    <div class="font-semibold text-ink">{{ u.name }}</div>
                                    <div class="font-mono text-xs text-ink-muted">@{{ u.username }}<span v-if="u.email"> · {{ u.email }}</span></div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="font-mono text-xs tabular-nums text-ink">
                                        {{ u.nip || '-' }}
                                    </div>
                                    <div class="text-xs text-ink-muted">{{ u.jabatan || '-' }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-ink">
                                    {{ u.unit_kerja?.nama_unit || '-' }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <GovBadge :variant="ROLE_BADGE_MAP[u.role] || 'neutral'">
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
                                            class="inline-flex items-center rounded border border-hairline bg-raised px-1.5 py-0.5 font-mono text-xs tabular-nums text-ink-secondary"
                                        >
                                            {{ assign.kode_sub_kegiatan }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-ink-muted">-</span>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-hairline px-2.5 py-1 text-xs font-semibold text-ink hover:bg-raised"
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
                                                    ? 'cursor-not-allowed border-hairline text-ink-muted'
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4"
            @click.self="closeFormModal"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-lg border border-hairline bg-surface shadow-floating">
                <div class="flex items-center justify-between border-b border-hairline bg-accent px-5 py-3 text-white">
                    <h2 class="text-sm font-semibold">
                        {{ isEditing ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}
                    </h2>
                    <button
                        type="button"
                        class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 inline-flex items-center justify-center p-1 rounded text-white/80 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        @click="closeFormModal"
                        aria-label="Tutup"
                    >
                        <X class="w-4 h-4" :stroke-width="1.5" />
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
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Nama Lengkap <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
                            />
                            <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">
                                {{ formErrors.name[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Username Login <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.username"
                                type="text"
                                required
                                placeholder="Contoh: operator.pataruman"
                                class="w-full rounded-md border border-hairline px-3 py-1.5 font-mono text-xs focus:border-accent focus:outline-none"
                            />
                            <p v-if="formErrors.username" class="mt-1 text-xs text-red-600">
                                {{ formErrors.username[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Email Dinas <span class="text-xs text-ink-muted">(Opsional)</span>
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
                            />
                            <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">
                                {{ formErrors.email[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Kata Sandi
                                <span v-if="!isEditing" class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                :required="!isEditing"
                                :placeholder="isEditing ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'"
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
                            />
                            <p v-if="formErrors.password" class="mt-1 text-xs text-red-600">
                                {{ formErrors.password[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Peran Sistem <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.role"
                                required
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
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
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Unit Kerja <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.unit_kerja_id"
                                required
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
                            >
                                <option value="" disabled>Pilih Unit Kerja</option>
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                    {{ unit.name || unit.nama_unit }}
                                </option>
                            </select>
                            <p v-if="formErrors.unit_kerja_id" class="mt-1 text-xs text-red-600">
                                {{ formErrors.unit_kerja_id[0] }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                NIP
                            </label>
                            <input
                                v-model="form.nip"
                                type="text"
                                class="w-full rounded-md border border-hairline px-3 py-1.5 font-mono text-xs tabular-nums focus:border-accent focus:outline-none"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-ink-muted">
                                Jabatan
                            </label>
                            <input
                                v-model="form.jabatan"
                                type="text"
                                class="w-full rounded-md border border-hairline px-3 py-1.5 text-xs focus:border-accent focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Checklist Penugasan Sub-Kegiatan khusus Peran PPTK -->
                    <div
                        v-if="form.role === 'PPTK'"
                        class="rounded-md border border-hairline bg-raised p-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-ink">
                                Penugasan Sub-Kegiatan PPTK
                            </span>
                            <span class="font-mono text-xs tabular-nums text-ink-muted">
                                {{ form.sub_kegiatan_codes.length }} dipilih
                            </span>
                        </div>

                        <div v-if="loadingSubActivities" class="py-2 text-xs text-ink-muted">
                            Memuat daftar sub-kegiatan unit...
                        </div>
                        <div
                            v-else-if="subActivities.length === 0"
                            class="py-2 text-xs text-ink-muted"
                        >
                            Belum ada sub-kegiatan RAK pada unit kerja ini.
                        </div>
                        <div v-else class="max-h-44 space-y-1.5 overflow-y-auto pr-1">
                            <label
                                v-for="sa in subActivities"
                                :key="sa.kode_sub_kegiatan"
                                class="flex cursor-pointer items-start gap-2 rounded border border-hairline bg-surface px-2.5 py-1.5 text-xs hover:bg-raised"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.sub_kegiatan_codes.includes(sa.kode_sub_kegiatan)"
                                    class="mt-0.5 rounded border-hairline text-ink focus:ring-accent"
                                    @change="toggleSubKegiatan(sa.kode_sub_kegiatan)"
                                />
                                <div>
                                    <div class="font-mono text-xs font-semibold tabular-nums text-ink">
                                        {{ sa.kode_sub_kegiatan }}
                                    </div>
                                    <div class="text-xs text-ink">
                                        {{ sa.nama_sub_kegiatan }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-hairline pt-3">
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
