<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { rekapBelanjaOperasiService } from '@/Services/rekapBelanjaOperasiService';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import { Plus, Search, Pencil, Trash2, X, Layers, CheckSquare } from 'lucide-vue-next';

const posList = ref([]);
const rekeningOptions = ref([]);
const loading = ref(false);
const loadingOptions = ref(false);

const searchQuery = ref('');
const filterStatus = ref('');

const toast = useToast();
const { confirm } = useConfirm();

const formDialogRef = ref(null);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formErrors = ref({});
const formErrorGeneral = ref('');
const rekeningSearch = ref('');

const form = ref({
  kode: '',
  nama: '',
  deskripsi: '',
  urutan: 0,
  is_active: true,
  kode_rekening: [],
});

const filteredRekeningOptions = computed(() => {
  const q = rekeningSearch.value.trim().toLowerCase();
  if (!q) return rekeningOptions.value;
  return rekeningOptions.value.filter(
    (opt) =>
      opt.kode_rekening.toLowerCase().includes(q) ||
      (opt.nama_rekening || '').toLowerCase().includes(q)
  );
});

const formatCurrency = (val) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);

async function fetchMasterList() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (filterStatus.value !== '') params.is_active = filterStatus.value;
    posList.value = await rekapBelanjaOperasiService.getMasterList(params);
  } catch {
    toast.error('Gagal memuat daftar Master Rekap Belanja Operasi.');
  } finally {
    loading.value = false;
  }
}

async function fetchRekeningOptions(excludeId = null) {
  loadingOptions.value = true;
  try {
    const params = {};
    if (excludeId) params.exclude_master_rekap_id = excludeId;
    rekeningOptions.value = await rekapBelanjaOperasiService.getRekeningOptions(params);
  } catch {
    rekeningOptions.value = [];
  } finally {
    loadingOptions.value = false;
  }
}

async function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  formErrors.value = {};
  formErrorGeneral.value = '';
  rekeningSearch.value = '';
  form.value = {
    kode: '',
    nama: '',
    deskripsi: '',
    urutan: posList.value.length + 1,
    is_active: true,
    kode_rekening: [],
  };
  formDialogRef.value?.showModal();
  await fetchRekeningOptions(null);
}

async function openEditModal(item) {
  isEditing.value = true;
  editingId.value = item.id;
  formErrors.value = {};
  formErrorGeneral.value = '';
  rekeningSearch.value = '';
  form.value = {
    kode: item.kode || '',
    nama: item.nama || '',
    deskripsi: item.deskripsi || '',
    urutan: item.urutan ?? 0,
    is_active: Boolean(item.is_active),
    kode_rekening: (item.rekening_mappings || []).map((m) => m.kode_rekening),
  };
  formDialogRef.value?.showModal();
  await fetchRekeningOptions(item.id);
}

function closeFormModal() {
  formDialogRef.value?.close();
}

function toggleRekeningCode(code, disabled) {
  if (disabled) return;
  const idx = form.value.kode_rekening.indexOf(code);
  if (idx >= 0) {
    form.value.kode_rekening.splice(idx, 1);
  } else {
    form.value.kode_rekening.push(code);
  }
}

async function savePos() {
  saving.value = true;
  formErrors.value = {};
  formErrorGeneral.value = '';

  try {
    if (isEditing.value && editingId.value) {
      await rekapBelanjaOperasiService.updateMasterPos(editingId.value, form.value);
      toast.success('Pos rekap belanja operasi berhasil diperbarui.');
    } else {
      await rekapBelanjaOperasiService.createMasterPos(form.value);
      toast.success('Pos rekap belanja operasi berhasil ditambahkan.');
    }
    closeFormModal();
    await fetchMasterList();
  } catch (err) {
    const res = err?.response;
    if (res?.status === 422 && res.data?.errors) {
      formErrors.value = res.data.errors;
      formErrorGeneral.value = res.data.message || 'Periksa kembali inputan Anda.';
    } else {
      formErrorGeneral.value = res?.data?.message || 'Terjadi kesalahan saat menyimpan data.';
    }
  } finally {
    saving.value = false;
  }
}

async function openDeleteModal(item) {
  await confirm({
    title: 'Hapus Pos Rekap?',
    message: `Anda yakin ingin menghapus pos ${item.kode} - ${item.nama}? Kode rekening yang terpetakan akan kembali berstatus belum dipetakan.`,
    details: `${item.kode} - ${item.nama}`,
    variant: 'danger',
    confirmText: 'Ya, Hapus',
    onConfirm: async () => {
      await rekapBelanjaOperasiService.deleteMasterPos(item.id);
      toast.success('Pos rekap belanja operasi berhasil dihapus.');
      await fetchMasterList();
    },
  });
}

onMounted(() => {
  fetchMasterList();
});
</script>

<template>
  <Head title="Master Rekap Belanja Operasi" />

  <GovAppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-ink tracking-tight flex items-center gap-2">
            <Layers class="w-5 h-5 text-accent" />
            Master Rekap Belanja Operasi
          </h1>
          <p class="text-sm text-ink-muted mt-1">
            Kelola kelompok pos rekap dan pemetaan eksklusif kode rekening Belanja Operasi (5.1.*).
          </p>
        </div>
        <GovButton variant="primary" @click="openCreateModal">
          <Plus class="w-4 h-4" />
          Tambah Pos Rekap
        </GovButton>
      </div>

      <!-- Filter Bar -->
      <div class="bg-surface border border-hairline rounded-xl p-4 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari kode atau nama pos rekap..."
            class="w-full pl-9 pr-3 py-2 text-sm border border-hairline rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
            @keyup.enter="fetchMasterList"
          />
        </div>
        <select
          v-model="filterStatus"
          class="px-3 py-2 text-sm border border-hairline rounded-lg bg-surface focus:outline-none focus:ring-2 focus:ring-accent"
          @change="fetchMasterList"
        >
          <option value="">Semua Status</option>
          <option value="true">Aktif</option>
          <option value="false">Nonaktif</option>
        </select>
        <GovButton variant="secondary" @click="fetchMasterList">Cari</GovButton>
      </div>

      <!-- Table -->
      <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <div class="overflow-x-auto scrollbar-thin">
          <table class="min-w-full divide-y divide-hairline text-sm">
            <thead class="bg-canvas text-ink-muted text-xs ">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Urutan</th>
                <th class="px-4 py-3 text-left font-semibold">Kode</th>
                <th class="px-4 py-3 text-left font-semibold">Nama Pos Rekap</th>
                <th class="px-4 py-3 text-left font-semibold">Kode Rekening Terpetakan</th>
                <th class="px-4 py-3 text-center font-semibold">Status</th>
                <th class="px-4 py-3 text-right font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
              <tr v-if="loading">
                <td colspan="6" class="px-4 py-8 text-center text-ink-muted">Memuat data...</td>
              </tr>
              <tr v-else-if="posList.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-ink-muted">
                  Belum ada pos Master Rekap Belanja Operasi. Klik "Tambah Pos Rekap" untuk memulai.
                </td>
              </tr>
              <tr v-for="item in posList" v-else :key="item.id" class="hover:bg-canvas/60">
                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">{{ item.urutan }}</td>
                <td class="px-4 py-3 font-mono font-semibold text-accent">{{ item.kode }}</td>
                <td class="px-4 py-3">
                  <div class="font-medium text-ink">{{ item.nama }}</div>
                  <div v-if="item.deskripsi" class="text-xs text-ink-muted mt-0.5">{{ item.deskripsi }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1.5">
                    <span
                      v-for="m in (item.rekening_mappings || []).slice(0, 4)"
                      :key="m.id"
                      class="inline-flex items-center px-2 py-0.5 rounded bg-accent-soft text-accent font-mono text-xs border border-accent-border"
                    >
                      {{ m.kode_rekening }}
                    </span>
                    <span
                      v-if="(item.rekening_mappings || []).length > 4"
                      class="inline-flex items-center px-2 py-0.5 rounded bg-canvas text-ink-muted text-xs border border-hairline"
                    >
                      +{{ item.rekening_mappings.length - 4 }} lainnya
                    </span>
                    <span
                      v-if="(item.rekening_mappings || []).length === 0"
                      class="text-xs text-ink-muted italic"
                    >
                      Belum ada rekening
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">
                  <GovBadge :variant="item.is_active ? 'success' : 'neutral'">
                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                  </GovBadge>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-accent hover:bg-accent-soft rounded-lg mr-1"
                    @click="openEditModal(item)"
                  >
                    <Pencil class="w-3.5 h-3.5 mr-1" />
                    Edit
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 rounded-lg"
                    @click="openDeleteModal(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5 mr-1" />
                    Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah/Edit -->
    <dialog
      ref="formDialogRef"
      class="rounded-xl border border-hairline p-0 w-full max-w-2xl backdrop:bg-ink/40 shadow-floating"
    >
      <form class="flex flex-col max-h-[90vh]" @submit.prevent="savePos">
        <div class="px-5 py-4 border-b border-hairline flex items-center justify-between bg-surface">
          <h2 class="text-base font-bold text-ink">
            {{ isEditing ? 'Edit Pos Rekap Belanja Operasi' : 'Tambah Pos Rekap Belanja Operasi' }}
          </h2>
          <button type="button" class="text-ink-muted hover:text-ink" @click="closeFormModal">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-4 overflow-y-auto bg-surface">
          <div v-if="formErrorGeneral" class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
            {{ formErrorGeneral }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-ink mb-1">Kode Pos *</label>
              <input
                v-model="form.kode"
                type="text"
                required
                placeholder="Mis. OP-01"
                class="w-full px-3 py-2 text-sm font-mono uppercase border border-hairline rounded-lg focus:ring-2 focus:ring-accent"
              />
              <p v-if="formErrors.kode" class="text-xs text-red-600 mt-1">{{ formErrors.kode[0] }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-ink mb-1">Nama Pos Rekap *</label>
              <input
                v-model="form.nama"
                type="text"
                required
                placeholder="Mis. Belanja Pegawai / Jasa Kantor"
                class="w-full px-3 py-2 text-sm border border-hairline rounded-lg focus:ring-2 focus:ring-accent"
              />
              <p v-if="formErrors.nama" class="text-xs text-red-600 mt-1">{{ formErrors.nama[0] }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-ink mb-1">Deskripsi</label>
              <input
                v-model="form.deskripsi"
                type="text"
                placeholder="Keterangan singkat kelompok belanja (opsional)"
                class="w-full px-3 py-2 text-sm border border-hairline rounded-lg focus:ring-2 focus:ring-accent"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-ink mb-1">Urutan Tampil</label>
              <input
                v-model.number="form.urutan"
                type="number"
                min="0"
                class="w-full px-3 py-2 text-sm font-mono tabular-nums border border-hairline rounded-lg focus:ring-2 focus:ring-accent"
              />
            </div>
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded border-hairline text-accent" />
            <span>Pos Rekap Aktif</span>
          </label>

          <!-- Pemetaan Kode Rekening 5.1.* -->
          <div class="border border-hairline rounded-lg p-3 bg-canvas/50 space-y-2.5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div class="text-xs font-semibold text-ink flex items-center gap-1.5">
                <CheckSquare class="w-4 h-4 text-accent" />
                Pemetaan Kode Rekening Belanja Operasi (5.1.*)
                <span class="font-mono text-accent">({{ form.kode_rekening.length }} dipilih)</span>
              </div>
              <input
                v-model="rekeningSearch"
                type="text"
                placeholder="Filter kode / nama rekening..."
                class="px-2.5 py-1 text-xs border border-hairline rounded-md bg-surface focus:ring-2 focus:ring-accent"
              />
            </div>
            <p v-if="formErrors.kode_rekening" class="text-xs text-red-600">
              {{ formErrors.kode_rekening[0] }}
            </p>

            <div class="max-h-56 overflow-y-auto divide-y divide-hairline border border-hairline rounded-lg bg-surface">
              <div v-if="loadingOptions" class="p-4 text-center text-xs text-ink-muted">
                Memuat daftar kode rekening...
              </div>
              <div v-else-if="filteredRekeningOptions.length === 0" class="p-4 text-center text-xs text-ink-muted">
                Tidak ada kode rekening Belanja Operasi (5.1.*) yang ditemukan.
              </div>
              <label
                v-for="opt in filteredRekeningOptions"
                v-else
                :key="opt.kode_rekening"
                :class="[
                  'flex items-start gap-2.5 px-3 py-2 text-xs',
                  opt.is_mapped && !opt.is_current_pos
                    ? 'opacity-55 bg-canvas cursor-not-allowed'
                    : 'hover:bg-accent-soft/40 cursor-pointer',
                ]"
              >
                <input
                  type="checkbox"
                  :checked="form.kode_rekening.includes(opt.kode_rekening)"
                  :disabled="opt.is_mapped && !opt.is_current_pos"
                  class="mt-0.5 rounded border-hairline text-accent"
                  @change="toggleRekeningCode(opt.kode_rekening, opt.is_mapped && !opt.is_current_pos)"
                />
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-mono font-semibold text-ink">{{ opt.kode_rekening }}</span>
                    <span class="font-mono tabular-nums text-ink-muted">{{ formatCurrency(opt.total_pagu) }}</span>
                  </div>
                  <div class="text-ink-muted truncate">{{ opt.nama_rekening }}</div>
                  <div v-if="opt.is_mapped && !opt.is_current_pos && opt.mapped_to" class="text-xs text-amber-700 mt-0.5">
                    Sudah dipetakan ke: {{ opt.mapped_to.kode }} · {{ opt.mapped_to.nama }}
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div class="px-5 py-3 border-t border-hairline flex justify-end gap-2 bg-canvas">
          <GovButton type="button" variant="secondary" @click="closeFormModal">Batal</GovButton>
          <GovButton type="submit" variant="primary" :disabled="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan Pos Rekap' }}
          </GovButton>
        </div>
      </form>
    </dialog>
  </GovAppLayout>
</template>
