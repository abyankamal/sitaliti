<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { sppdAssignmentService } from '@/Services/sppdAssignmentService';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';
import { Plus, Search, FileText, Eye, Trash2 } from 'lucide-vue-next';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isReadOnly = computed(() => ['LURAH', 'KPA'].includes(currentUser.value?.role));

const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const errorMsg = ref('');

const searchQuery = ref('');
const filterStatus = ref('');

const toast = useToast();
const { confirm } = useConfirm();

const statusVariant = (status) => {
  if (status === 'SELESAI') return 'success';
  if (status === 'TERBIT') return 'info';
  return 'neutral';
};

async function fetchAssignments(pageNumber = 1) {
  loading.value = true;
  errorMsg.value = '';
  try {
    const params = { page: pageNumber, per_page: 15 };
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (filterStatus.value) params.status = filterStatus.value;

    const res = await sppdAssignmentService.list(params);
    items.value = res.data ?? [];
    pagination.value = {
      current_page: res.current_page ?? 1,
      last_page: res.last_page ?? 1,
      total: res.total ?? 0,
    };
  } catch {
    errorMsg.value = 'Gagal memuat daftar penugasan SPPD.';
    toast.error(errorMsg.value);
  } finally {
    loading.value = false;
  }
}

async function handleDelete(item) {
  await confirm({
    title: 'Hapus Penugasan SPPD?',
    message: `Penugasan "${item.maksud_perjalanan}" akan dihapus permanen.`,
    variant: 'danger',
    confirmText: 'Hapus',
    onConfirm: async () => {
      await sppdAssignmentService.remove(item.id);
      toast.success('Penugasan SPPD berhasil dihapus.');
      await fetchAssignments(pagination.value.current_page);
    },
  });
}

onMounted(() => fetchAssignments(1));
</script>

<template>
  <Head title="Penugasan SPPD" />

  <GovAppLayout>
    <div class="space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold text-ink">Penugasan SPPD</h1>
          <p class="text-xs text-ink-muted">
            Input surat tugas pra-berangkat, cetak bundel PDF F4 siap tanda tangan basah, dan konversi ke Kuitansi SPJ.
          </p>
        </div>

        <Link v-if="!isReadOnly" :href="route('sppd-assignments.create')">
          <GovButton size="md">
            <Plus class="w-4 h-4 mr-1.5" />
            Buat Penugasan SPPD
          </GovButton>
        </Link>
      </div>

      <!-- Filter -->
      <form
        class="bg-surface border border-hairline rounded-xl p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end"
        @submit.prevent="fetchAssignments(1)"
      >
        <div class="md:col-span-2">
          <label for="sppd-assign-search" class="block text-xs font-medium text-ink-secondary mb-1">
            Cari (No. ST / Maksud / Tujuan / Nama Pegawai)
          </label>
          <div class="relative">
            <Search class="w-3.5 h-3.5 text-ink-muted absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              id="sppd-assign-search"
              v-model="searchQuery"
              type="text"
              placeholder="Kata kunci..."
              class="w-full h-9 pl-8 pr-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
            />
          </div>
        </div>

        <div>
          <label for="sppd-assign-status" class="block text-xs font-medium text-ink-secondary mb-1">Status</label>
          <select
            id="sppd-assign-status"
            v-model="filterStatus"
            class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
          >
            <option value="">Semua Status</option>
            <option value="DRAFT">DRAFT</option>
            <option value="TERBIT">TERBIT</option>
            <option value="SELESAI">SELESAI (SPJ)</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <GovButton type="submit" size="sm">Filter</GovButton>
          <GovButton
            type="button"
            variant="secondary"
            size="sm"
            @click="searchQuery = ''; filterStatus = ''; fetchAssignments(1)"
          >
            Reset
          </GovButton>
        </div>
      </form>

      <!-- Table -->
      <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <div v-if="errorMsg" class="p-6 text-center space-y-2">
          <p class="text-xs text-semantic-danger">{{ errorMsg }}</p>
          <GovButton size="sm" variant="secondary" @click="fetchAssignments(1)">Muat Ulang</GovButton>
        </div>

        <div v-else class="overflow-x-auto scrollbar-thin">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-canvas border-b border-hairline text-xs font-semibold text-ink-secondary">
                <th class="px-4 py-2.5">Tanggal &amp; Nomor ST</th>
                <th class="px-4 py-2.5">Maksud &amp; Tujuan</th>
                <th class="px-4 py-2.5">Peserta</th>
                <th class="px-4 py-2.5">Status &amp; SPJ</th>
                <th class="px-4 py-2.5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline text-xs">
              <tr v-if="loading">
                <td colspan="5" class="px-4 py-8 text-center text-ink-muted">Memuat penugasan SPPD...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-ink-muted">
                  Belum ada penugasan SPPD.
                </td>
              </tr>
              <tr v-for="item in items" v-else :key="item.id" class="hover:bg-canvas/60 transition-colors">
                <td class="px-4 py-2.5 whitespace-nowrap">
                  <div class="font-mono tabular-nums font-medium text-ink">
                    {{ item.tanggal_berangkat }} s/d {{ item.tanggal_kembali }}
                  </div>
                  <div class="text-ink-muted">{{ item.nomor_surat_tugas || 'Belum bernomor' }}</div>
                </td>
                <td class="px-4 py-2.5 max-w-xs">
                  <div class="font-medium text-ink truncate" :title="item.maksud_perjalanan">
                    {{ item.maksud_perjalanan }}
                  </div>
                  <div class="text-ink-muted">{{ item.tempat_tujuan }}</div>
                </td>
                <td class="px-4 py-2.5">
                  <div class="font-medium text-ink">{{ item.peserta_count ?? item.peserta?.length ?? 0 }} orang</div>
                  <div class="text-ink-muted truncate max-w-[200px]">
                    {{ (item.peserta || []).map((p) => p.pegawai?.nama).filter(Boolean).join(', ') }}
                  </div>
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap space-y-1">
                  <GovBadge :variant="statusVariant(item.status)" size="sm">{{ item.status }}</GovBadge>
                  <div v-if="item.kuitansi" class="text-[11px] font-mono text-ink-secondary">
                    {{ item.kuitansi.nomor_kuitansi }}
                  </div>
                </td>
                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <a
                      :href="route('sppd-assignments.pdf', { id: item.id, scope: 'pre' })"
                      target="_blank"
                      class="px-2 py-1 rounded-md border border-hairline text-xs text-ink-secondary hover:text-accent hover:border-accent"
                      title="Cetak PDF Pra-Berangkat (ST + SPD)"
                    >
                      <FileText class="w-3.5 h-3.5 inline mr-1" />PDF
                    </a>
                    <Link
                      :href="route('sppd-assignments.show', item.id)"
                      class="p-1.5 rounded-md text-ink-secondary hover:text-accent hover:bg-accent-soft"
                      title="Detail & SPJ"
                    >
                      <Eye class="w-3.5 h-3.5" />
                    </Link>
                    <button
                      v-if="!isReadOnly && !item.kuitansi_id"
                      type="button"
                      class="p-1.5 rounded-md text-ink-secondary hover:text-semantic-danger hover:bg-red-50"
                      title="Hapus"
                      @click="handleDelete(item)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </GovAppLayout>
</template>
