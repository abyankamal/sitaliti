<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { sppdAssignmentService } from '@/Services/sppdAssignmentService';
import { budgetService } from '@/Services/budgetService';
import { useToast } from '@/Composables/useToast';
import { ArrowLeft, Printer, Pencil, FileCheck2, X } from 'lucide-vue-next';

const props = defineProps({
  assignmentId: { type: Number, required: true },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isReadOnly = computed(() => ['LURAH', 'KPA'].includes(currentUser.value?.role));

const toast = useToast();
const loading = ref(true);
const assignment = ref(null);
const convertDialogRef = ref(null);
const sppdRekeningOptions = ref([]);
const converting = ref(false);
const convertForm = ref({
  anggaran_rekening_id: '',
  bulan_anggaran: new Date().getMonth() + 1,
  metode_pembayaran: 'NON_TUNAI_IBC',
});

const isLocked = computed(() => {
  if (!assignment.value?.kuitansi) return false;
  return !['DRAFT', 'REVISI_MAYOR'].includes(assignment.value.kuitansi.status);
});

const totalBiayaAll = computed(() => {
  if (!assignment.value?.peserta) return 0;
  return assignment.value.peserta.reduce(
    (sum, p) => sum + (p.biaya_items || []).reduce((s, b) => s + Number(b.nominal || 0), 0),
    0
  );
});

async function fetchDetail() {
  loading.value = true;
  try {
    assignment.value = await sppdAssignmentService.get(props.assignmentId);
  } catch {
    toast.error('Gagal memuat detail penugasan SPPD.');
  } finally {
    loading.value = false;
  }
}

async function openConvertModal() {
  try {
    const subs = await budgetService.getSubActivitiesByUnit(assignment.value.unit_kerja_id, {
      include_kecamatan_sppd: 1,
    });
    const allRek = [];
    for (const sub of subs) {
      for (const rek of sub.rekening || sub.anggaran_rekening || []) {
        const kode = String(rek.code || rek.kode_rekening || '');
        const kat = String(rek.kategori || rek.kategori_belanja || '').toUpperCase();
        if (kode.startsWith('5.1.02.04') || ['B.4', 'B4', 'B4_PERJADIN'].includes(kat)) {
          allRek.push({
            id: rek.id,
            label: `${kode} - ${rek.name || rek.nama_rekening} (${sub.name || sub.nama_sub_kegiatan})`,
          });
        }
      }
    }
    sppdRekeningOptions.value = allRek;
    if (assignment.value.anggaran_rekening_id) {
      convertForm.value.anggaran_rekening_id = assignment.value.anggaran_rekening_id;
    } else if (allRek.length > 0) {
      convertForm.value.anggaran_rekening_id = allRek[0].id;
    }
    convertDialogRef.value?.showModal();
  } catch {
    toast.error('Gagal memuat daftar rekening SPPD.');
  }
}

async function handleConvert() {
  converting.value = true;
  try {
    const res = await sppdAssignmentService.convertToKuitansi(props.assignmentId, convertForm.value);
    assignment.value = res.data;
    convertDialogRef.value?.close();
    toast.success('Berhasil dikonversi menjadi Kuitansi SPJ.');
  } catch (err) {
    const msg = err.response?.data?.error?.message || 'Gagal mengonversi ke Kuitansi SPJ.';
    toast.error(msg);
  } finally {
    converting.value = false;
  }
}

onMounted(fetchDetail);
</script>

<template>
  <Head title="Detail Penugasan SPPD" />

  <GovAppLayout>
    <div v-if="loading" class="p-8 text-center text-xs text-ink-muted">Memuat detail penugasan SPPD...</div>

    <div v-else-if="assignment" class="max-w-4xl mx-auto space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
          <Link :href="route('sppd-assignments.index')" class="p-2 rounded-lg border border-hairline bg-surface text-ink-secondary hover:text-ink">
            <ArrowLeft class="w-4 h-4" />
          </Link>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-lg font-semibold text-ink">{{ assignment.nomor_surat_tugas || `Penugasan #${assignment.id}` }}</h1>
              <GovBadge :variant="assignment.status === 'SELESAI' ? 'success' : 'info'" size="sm">{{ assignment.status }}</GovBadge>
            </div>
            <p class="text-xs text-ink-muted">{{ assignment.unit_kerja?.nama_unit }} • {{ assignment.tanggal_berangkat }} s/d {{ assignment.tanggal_kembali }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Link v-if="!isReadOnly && !isLocked" :href="route('sppd-assignments.edit', assignment.id)">
            <GovButton size="sm" variant="secondary"><Pencil class="w-3.5 h-3.5 mr-1" /> Edit</GovButton>
          </Link>
          <GovButton v-if="!isReadOnly && !assignment.kuitansi_id" size="sm" @click="openConvertModal">
            <FileCheck2 class="w-3.5 h-3.5 mr-1" /> Jadikan Kuitansi SPJ
          </GovButton>
        </div>
      </div>

      <!-- Tombol Cetak PDF F4 -->
      <div class="bg-surface border border-hairline rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs">
          <div class="font-semibold text-ink">Cetak Dokumen PDF F4 (215 × 330 mm) Siap Tanda Tangan Basah</div>
          <div class="text-ink-muted">Pilih cetak tahap pra-berangkat, pasca-berangkat, atau bundel 5 dokumen sekaligus.</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a :href="route('sppd-assignments.pdf', { id: assignment.id, scope: 'pre' })" target="_blank">
            <GovButton size="sm" variant="secondary"><Printer class="w-3.5 h-3.5 mr-1" /> Pra-Berangkat (ST + SPD)</GovButton>
          </a>
          <a :href="route('sppd-assignments.pdf', { id: assignment.id, scope: 'post' })" target="_blank">
            <GovButton size="sm" variant="secondary"><Printer class="w-3.5 h-3.5 mr-1" /> Pasca-Berangkat (Kwitansi + Laporan)</GovButton>
          </a>
          <a :href="route('sppd-assignments.pdf', { id: assignment.id, scope: 'all' })" target="_blank">
            <GovButton size="sm"><Printer class="w-3.5 h-3.5 mr-1" /> Bundel Komplit (5 Dokumen)</GovButton>
          </a>
        </div>
      </div>

      <!-- Ringkasan -->
      <div class="bg-surface border border-hairline rounded-xl p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div>
          <div class="text-ink-muted">Maksud Perjalanan</div>
          <div class="font-medium text-ink mt-0.5">{{ assignment.maksud_perjalanan }}</div>
        </div>
        <div>
          <div class="text-ink-muted">Rute &amp; Alat Angkut</div>
          <div class="font-medium text-ink mt-0.5">{{ assignment.tempat_berangkat }} → {{ assignment.tempat_tujuan }} ({{ assignment.alat_angkut || '-' }})</div>
        </div>
        <div>
          <div class="text-ink-muted">Pejabat Pemberi Perintah</div>
          <div class="font-medium text-ink mt-0.5">{{ assignment.pemberi_perintah_nama }} ({{ assignment.pemberi_perintah_jabatan }})</div>
        </div>
        <div>
          <div class="text-ink-muted">Tautan Kuitansi SPJ</div>
          <div v-if="assignment.kuitansi" class="font-mono font-medium text-accent mt-0.5">
            {{ assignment.kuitansi.nomor_kuitansi }} ({{ assignment.kuitansi.status }})
          </div>
          <div v-else class="text-ink-muted mt-0.5">Belum dikonversi ke Kuitansi SPJ</div>
        </div>
      </div>

      <!-- Daftar Peserta -->
      <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-hairline flex items-center justify-between text-xs font-semibold text-ink">
          <span>Peserta &amp; Rincian Biaya</span>
          <span class="font-mono">Total: Rp {{ totalBiayaAll.toLocaleString('id-ID') }}</span>
        </div>
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-canvas border-b border-hairline text-ink-secondary">
              <th class="px-4 py-2">Nama / NIP</th>
              <th class="px-4 py-2">Jabatan</th>
              <th class="px-4 py-2">Nomor SPD</th>
              <th class="px-4 py-2 text-right">Total Biaya</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-for="p in assignment.peserta" :key="p.id">
              <td class="px-4 py-2.5">
                <div class="font-medium text-ink">{{ p.pegawai?.nama }}</div>
                <div class="text-ink-muted font-mono">{{ p.pegawai?.nip || 'Non-ASN' }}</div>
              </td>
              <td class="px-4 py-2.5 text-ink-secondary">{{ p.pegawai?.jabatan || '-' }}</td>
              <td class="px-4 py-2.5 font-mono text-ink-secondary">{{ p.nomor_spd || '-' }}</td>
              <td class="px-4 py-2.5 text-right font-mono font-medium text-ink">
                Rp {{ (p.biaya_items || []).reduce((s, b) => s + Number(b.nominal || 0), 0).toLocaleString('id-ID') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Konversi ke Kuitansi SPJ -->
    <dialog ref="convertDialogRef" class="w-full max-w-md rounded-xl bg-surface text-ink border border-hairline p-0 shadow-floating backdrop:bg-ink/40">
      <div class="px-5 py-3.5 border-b border-hairline flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink">Konversi ke Kuitansi SPJ</h2>
        <button type="button" class="p-1 text-ink-muted hover:text-ink" @click="convertDialogRef?.close()">
          <X class="w-4 h-4" />
        </button>
      </div>
      <form class="p-5 space-y-4" @submit.prevent="handleConvert">
        <div>
          <label for="conv-rekening" class="block text-xs font-medium text-ink-secondary mb-1">Rekening Perjalanan Dinas (B.4) <span class="text-semantic-danger">*</span></label>
          <select id="conv-rekening" v-model="convertForm.anggaran_rekening_id" required class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink">
            <option value="">-- Pilih Rekening --</option>
            <option v-for="r in sppdRekeningOptions" :key="r.id" :value="r.id">{{ r.label }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="conv-bulan" class="block text-xs font-medium text-ink-secondary mb-1">Bulan Anggaran</label>
            <input id="conv-bulan" v-model.number="convertForm.bulan_anggaran" type="number" min="1" max="12" required class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
          </div>
          <div>
            <label for="conv-metode" class="block text-xs font-medium text-ink-secondary mb-1">Metode Pembayaran</label>
            <select id="conv-metode" v-model="convertForm.metode_pembayaran" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink">
              <option value="NON_TUNAI_IBC">NON_TUNAI_IBC</option>
              <option value="TUNAI_UP">TUNAI_UP</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <GovButton type="button" variant="secondary" @click="convertDialogRef?.close()">Batal</GovButton>
          <GovButton type="submit" :disabled="converting">{{ converting ? 'Memproses...' : 'Buat Kuitansi SPJ' }}</GovButton>
        </div>
      </form>
    </dialog>
  </GovAppLayout>
</template>
