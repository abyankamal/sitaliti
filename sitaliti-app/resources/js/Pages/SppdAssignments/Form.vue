<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { sppdAssignmentService } from '@/Services/sppdAssignmentService';
import { sppdService } from '@/Services/sppdService';
import { useToast } from '@/Composables/useToast';
import { ArrowLeft, Plus, Trash2, AlertTriangle, Lock } from 'lucide-vue-next';

const props = defineProps({
  assignmentId: { type: Number, default: null },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isEditing = computed(() => props.assignmentId !== null);

const toast = useToast();
const loading = ref(false);
const saving = ref(false);
const pegawaiOptions = ref([]);
const conflicts = ref([]);
const formErrorGeneral = ref('');
const formErrors = ref({});
const linkedKuitansi = ref(null);

const isLockedByKuitansi = computed(() => {
  if (!linkedKuitansi.value) return false;
  return !['DRAFT', 'REVISI_MAYOR'].includes(linkedKuitansi.value.status);
});

const form = ref({
  nomor_surat_tugas: '',
  maksud_perjalanan: '',
  alat_angkut: 'Kendaraan Dinas / Umum',
  tempat_berangkat: 'Kecamatan Tarogong Kidul',
  tempat_tujuan: '',
  tanggal_berangkat: '',
  tanggal_kembali: '',
  pemberi_perintah_nama: '',
  pemberi_perintah_nip: '',
  pemberi_perintah_jabatan: '',
  dikeluarkan_di: 'Garut',
  tanggal_penetapan: '',
  laporan_hasil: '',
  status: 'DRAFT',
  peserta: [
    {
      pegawai_id: '',
      nomor_spd: '',
      tingkat_biaya: '',
      biaya_items: [{ uraian_biaya: 'Uang Transpor Lokal', nominal: 70000, keterangan: '' }],
    },
  ],
});

function addPeserta() {
  form.value.peserta.push({
    pegawai_id: '',
    nomor_spd: '',
    tingkat_biaya: '',
    biaya_items: [{ uraian_biaya: 'Uang Transpor Lokal', nominal: 70000, keterangan: '' }],
  });
}

function removePeserta(idx) {
  if (form.value.peserta.length > 1) {
    form.value.peserta.splice(idx, 1);
  }
}

function addBiayaItem(pesertaIdx) {
  form.value.peserta[pesertaIdx].biaya_items.push({
    uraian_biaya: '',
    nominal: 0,
    keterangan: '',
  });
}

function removeBiayaItem(pesertaIdx, biayaIdx) {
  form.value.peserta[pesertaIdx].biaya_items.splice(biayaIdx, 1);
}

async function runConflictCheck() {
  const validPeserta = form.value.peserta
    .filter((p) => p.pegawai_id)
    .map((p) => ({ pegawai_id: Number(p.pegawai_id) }));

  if (!validPeserta.length || !form.value.tanggal_berangkat || !form.value.tanggal_kembali) {
    conflicts.value = [];
    return;
  }

  try {
    const res = await sppdAssignmentService.checkConflicts({
      exclude_sppd_assignment_id: props.assignmentId,
      exclude_kuitansi_id: linkedKuitansi.value?.id ?? null,
      tanggal_berangkat: form.value.tanggal_berangkat,
      tanggal_kembali: form.value.tanggal_kembali,
      peserta: validPeserta,
    });
    conflicts.value = res.conflicts ?? [];
  } catch {
    conflicts.value = [];
  }
}

watch(
  () => [
    form.value.tanggal_berangkat,
    form.value.tanggal_kembali,
    form.value.peserta.map((p) => p.pegawai_id).join(','),
  ],
  () => {
    runConflictCheck();
  }
);

async function loadInitialData() {
  loading.value = true;
  try {
    pegawaiOptions.value = await sppdService.getPegawaiOptions({
      unit_kerja_id: currentUser.value?.unit_kerja_id || undefined,
    });

    if (isEditing.value) {
      const data = await sppdAssignmentService.get(props.assignmentId);
      linkedKuitansi.value = data.kuitansi ?? null;
      form.value = {
        nomor_surat_tugas: data.nomor_surat_tugas || '',
        maksud_perjalanan: data.maksud_perjalanan || '',
        alat_angkut: data.alat_angkut || '',
        tempat_berangkat: data.tempat_berangkat || '',
        tempat_tujuan: data.tempat_tujuan || '',
        tanggal_berangkat: data.tanggal_berangkat ? String(data.tanggal_berangkat).slice(0, 10) : '',
        tanggal_kembali: data.tanggal_kembali ? String(data.tanggal_kembali).slice(0, 10) : '',
        pemberi_perintah_nama: data.pemberi_perintah_nama || '',
        pemberi_perintah_nip: data.pemberi_perintah_nip || '',
        pemberi_perintah_jabatan: data.pemberi_perintah_jabatan || '',
        dikeluarkan_di: data.dikeluarkan_di || 'Garut',
        tanggal_penetapan: data.tanggal_penetapan ? String(data.tanggal_penetapan).slice(0, 10) : '',
        laporan_hasil: data.laporan_hasil || '',
        status: data.status || 'DRAFT',
        peserta: (data.peserta || []).map((p) => ({
          pegawai_id: p.pegawai_id,
          nomor_spd: p.nomor_spd || '',
          tingkat_biaya: p.tingkat_biaya || '',
          biaya_items: (p.biaya_items || []).map((b) => ({
            uraian_biaya: b.uraian_biaya,
            nominal: Number(b.nominal),
            keterangan: b.keterangan || '',
          })),
        })),
      };
    }
  } catch {
    toast.error('Gagal memuat data penugasan SPPD.');
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  if (isLockedByKuitansi.value) return;

  saving.value = true;
  formErrorGeneral.value = '';
  formErrors.value = {};

  const payload = {
    ...form.value,
    nomor_surat_tugas: form.value.nomor_surat_tugas || null,
    pemberi_perintah_nama: form.value.pemberi_perintah_nama || null,
    pemberi_perintah_nip: form.value.pemberi_perintah_nip || null,
    pemberi_perintah_jabatan: form.value.pemberi_perintah_jabatan || null,
    tanggal_penetapan: form.value.tanggal_penetapan || null,
    laporan_hasil: form.value.laporan_hasil || null,
    peserta: form.value.peserta.map((p, idx) => ({
      pegawai_id: Number(p.pegawai_id),
      urutan: idx + 1,
      nomor_spd: p.nomor_spd || null,
      tingkat_biaya: p.tingkat_biaya || null,
      biaya_items: (p.biaya_items || [])
        .filter((b) => b.uraian_biaya && Number(b.nominal) >= 0)
        .map((b) => ({
          uraian_biaya: b.uraian_biaya,
          nominal: Number(b.nominal),
          keterangan: b.keterangan || null,
        })),
    })),
  };

  try {
    let saved;
    if (isEditing.value) {
      const res = await sppdAssignmentService.update(props.assignmentId, payload);
      saved = res.data;
      toast.success('Penugasan SPPD berhasil diperbarui.');
    } else {
      const res = await sppdAssignmentService.create(payload);
      saved = res.data;
      toast.success('Penugasan SPPD berhasil dibuat.');
    }
    router.visit(route('sppd-assignments.show', saved.id));
  } catch (err) {
    const resData = err.response?.data;
    formErrors.value = resData?.error?.details || resData?.errors || {};
    formErrorGeneral.value = resData?.error?.message || resData?.message || 'Gagal menyimpan penugasan SPPD.';
  } finally {
    saving.value = false;
  }
}

onMounted(loadInitialData);
</script>

<template>
  <Head :title="isEditing ? 'Edit Penugasan SPPD' : 'Buat Penugasan SPPD'" />

  <GovAppLayout>
    <div class="max-w-4xl mx-auto space-y-5">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('sppd-assignments.index')" class="p-2 rounded-lg border border-hairline bg-surface text-ink-secondary hover:text-ink">
            <ArrowLeft class="w-4 h-4" />
          </Link>
          <div>
            <h1 class="text-lg font-semibold text-ink">{{ isEditing ? 'Edit Penugasan SPPD' : 'Buat Penugasan SPPD Baru' }}</h1>
            <p class="text-xs text-ink-muted">Isi data penugasan &amp; peserta (ASN / PPPK / Non-ASN) untuk cetak PDF F4.</p>
          </div>
        </div>
      </div>

      <div v-if="isLockedByKuitansi" class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center gap-2.5">
        <Lock class="w-4 h-4 shrink-0" />
        <span>
          Penugasan ini terkunci karena Kuitansi <strong>{{ linkedKuitansi.nomor_kuitansi }}</strong> sudah berstatus
          <strong>{{ linkedKuitansi.status }}</strong>.
        </span>
      </div>

      <div v-if="conflicts.length > 0" role="alert" class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-xs text-semantic-danger space-y-1">
        <div class="font-semibold flex items-center gap-1.5">
          <AlertTriangle class="w-4 h-4" /> Peringatan Bentrok Jadwal SPPD
        </div>
        <ul class="list-disc pl-5 space-y-0.5">
          <li v-for="(c, idx) in conflicts" :key="idx">{{ c.message }}</li>
        </ul>
      </div>

      <div v-if="formErrorGeneral" role="alert" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-semantic-danger">
        {{ formErrorGeneral }}
      </div>

      <form v-if="!loading" class="space-y-5" @submit.prevent="handleSubmit">
        <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
          <h2 class="text-sm font-semibold text-ink border-b border-hairline pb-2">Informasi Surat Tugas &amp; Perjalanan</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="sppd-nomor-st" class="block text-xs font-medium text-ink-secondary mb-1">Nomor Surat Tugas (Opsional)</label>
              <input id="sppd-nomor-st" v-model="form.nomor_surat_tugas" type="text" :disabled="isLockedByKuitansi" placeholder="Contoh: 001/Tarkid/SPPD/X/2026" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>

            <div>
              <label for="sppd-status" class="block text-xs font-medium text-ink-secondary mb-1">Status Penugasan</label>
              <select id="sppd-status" v-model="form.status" :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink">
                <option value="DRAFT">DRAFT</option>
                <option value="TERBIT">TERBIT (Siap Cetak ST &amp; SPD)</option>
                <option value="SELESAI">SELESAI</option>
              </select>
            </div>
          </div>

          <div>
            <label for="sppd-maksud" class="block text-xs font-medium text-ink-secondary mb-1">Maksud Perjalanan Dinas <span class="text-semantic-danger">*</span></label>
            <textarea id="sppd-maksud" v-model="form.maksud_perjalanan" required rows="2" :disabled="isLockedByKuitansi" class="w-full p-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="sppd-berangkat" class="block text-xs font-medium text-ink-secondary mb-1">Tempat Berangkat <span class="text-semantic-danger">*</span></label>
              <input id="sppd-berangkat" v-model="form.tempat_berangkat" type="text" required :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
            <div>
              <label for="sppd-tujuan" class="block text-xs font-medium text-ink-secondary mb-1">Tempat Tujuan <span class="text-semantic-danger">*</span></label>
              <input id="sppd-tujuan" v-model="form.tempat_tujuan" type="text" required :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
            <div>
              <label for="sppd-angkut" class="block text-xs font-medium text-ink-secondary mb-1">Alat Angkut</label>
              <input id="sppd-angkut" v-model="form.alat_angkut" type="text" :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="sppd-tgl-berangkat" class="block text-xs font-medium text-ink-secondary mb-1">Tanggal Berangkat <span class="text-semantic-danger">*</span></label>
              <input id="sppd-tgl-berangkat" v-model="form.tanggal_berangkat" type="date" required :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
            <div>
              <label for="sppd-tgl-kembali" class="block text-xs font-medium text-ink-secondary mb-1">Tanggal Kembali <span class="text-semantic-danger">*</span></label>
              <input id="sppd-tgl-kembali" v-model="form.tanggal_kembali" type="date" required :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
          </div>
        </div>

        <!-- Peserta & Biaya -->
        <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
          <div class="flex items-center justify-between border-b border-hairline pb-2">
            <h2 class="text-sm font-semibold text-ink">Daftar Peserta &amp; Rincian Biaya ({{ form.peserta.length }} orang)</h2>
            <GovButton v-if="!isLockedByKuitansi" type="button" size="sm" variant="secondary" @click="addPeserta">
              <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Peserta
            </GovButton>
          </div>

          <div v-for="(p, pIdx) in form.peserta" :key="pIdx" class="p-4 rounded-lg border border-hairline bg-canvas space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-ink">Peserta #{{ pIdx + 1 }}</span>
              <button
                v-if="!isLockedByKuitansi && form.peserta.length > 1"
                type="button"
                class="text-xs text-semantic-danger hover:underline inline-flex items-center gap-1"
                @click="removePeserta(pIdx)"
              >
                <Trash2 class="w-3.5 h-3.5" /> Hapus
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label :for="`peserta-pegawai-${pIdx}`" class="block text-xs font-medium text-ink-secondary mb-1">Pegawai <span class="text-semantic-danger">*</span></label>
                <select :id="`peserta-pegawai-${pIdx}`" v-model="p.pegawai_id" required :disabled="isLockedByKuitansi" class="w-full h-9 px-2.5 text-xs bg-surface border border-hairline rounded-lg text-ink">
                  <option value="">-- Pilih Pegawai --</option>
                  <option v-for="opt in pegawaiOptions" :key="opt.id" :value="opt.id">
                    {{ opt.nama }} ({{ opt.nip || (opt.status_kepegawaian === 'NON_ASN' ? 'Non-ASN' : '-') }})
                  </option>
                </select>
              </div>
              <div>
                <label :for="`peserta-spd-${pIdx}`" class="block text-xs font-medium text-ink-secondary mb-1">Nomor SPD</label>
                <input :id="`peserta-spd-${pIdx}`" v-model="p.nomor_spd" type="text" :disabled="isLockedByKuitansi" placeholder="Nomor SPD individu" class="w-full h-9 px-3 text-xs bg-surface border border-hairline rounded-lg text-ink" />
              </div>
              <div>
                <label :for="`peserta-tingkat-${pIdx}`" class="block text-xs font-medium text-ink-secondary mb-1">Tingkat Biaya</label>
                <input :id="`peserta-tingkat-${pIdx}`" v-model="p.tingkat_biaya" type="text" :disabled="isLockedByKuitansi" placeholder="Contoh: Rp 70.000 / Tingkat C" class="w-full h-9 px-3 text-xs bg-surface border border-hairline rounded-lg text-ink" />
              </div>
            </div>

            <div class="space-y-2 pt-1">
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-ink-secondary">Rincian Biaya Perjalanan</span>
                <button v-if="!isLockedByKuitansi" type="button" class="text-xs text-accent hover:underline" @click="addBiayaItem(pIdx)">
                  + Tambah Item Biaya
                </button>
              </div>
              <div v-for="(b, bIdx) in p.biaya_items" :key="bIdx" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                <input v-model="b.uraian_biaya" type="text" :disabled="isLockedByKuitansi" placeholder="Uraian biaya (misal Uang Transpor Lokal)" class="sm:col-span-5 h-8 px-2.5 text-xs bg-surface border border-hairline rounded-lg text-ink" />
                <input v-model.number="b.nominal" type="number" min="0" :disabled="isLockedByKuitansi" placeholder="Nominal (Rp)" class="sm:col-span-3 h-8 px-2.5 text-xs font-mono bg-surface border border-hairline rounded-lg text-ink" />
                <input v-model="b.keterangan" type="text" :disabled="isLockedByKuitansi" placeholder="Keterangan" class="sm:col-span-3 h-8 px-2.5 text-xs bg-surface border border-hairline rounded-lg text-ink" />
                <button v-if="!isLockedByKuitansi" type="button" class="sm:col-span-1 text-semantic-danger flex justify-center" @click="removeBiayaItem(pIdx, bIdx)">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Laporan Hasil & Penandatangan -->
        <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
          <h2 class="text-sm font-semibold text-ink border-b border-hairline pb-2">Laporan Hasil &amp; Penandatangan (Opsional)</h2>
          <div>
            <label for="sppd-laporan" class="block text-xs font-medium text-ink-secondary mb-1">Hasil Tugas / Kegiatan (untuk Lembar Laporan SPPD)</label>
            <textarea id="sppd-laporan" v-model="form.laporan_hasil" rows="3" :disabled="isLockedByKuitansi" placeholder="Ringkasan hasil perjalanan dinas..." class="w-full p-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label for="sppd-pa-nama" class="block text-xs font-medium text-ink-secondary mb-1">Override Nama Pemberi Perintah</label>
              <input id="sppd-pa-nama" v-model="form.pemberi_perintah_nama" type="text" :disabled="isLockedByKuitansi" placeholder="Default dari Unit Kerja" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
            <div>
              <label for="sppd-pa-nip" class="block text-xs font-medium text-ink-secondary mb-1">Override NIP Pemberi Perintah</label>
              <input id="sppd-pa-nip" v-model="form.pemberi_perintah_nip" type="text" :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs font-mono bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
            <div>
              <label for="sppd-pa-jabatan" class="block text-xs font-medium text-ink-secondary mb-1">Override Jabatan Pemberi Perintah</label>
              <input id="sppd-pa-jabatan" v-model="form.pemberi_perintah_jabatan" type="text" :disabled="isLockedByKuitansi" class="w-full h-9 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink" />
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3">
          <Link :href="route('sppd-assignments.index')">
            <GovButton type="button" variant="secondary">Batal</GovButton>
          </Link>
          <GovButton v-if="!isLockedByKuitansi" type="submit" :disabled="saving">
            {{ saving ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Simpan Penugasan SPPD') }}
          </GovButton>
        </div>
      </form>
    </div>
  </GovAppLayout>
</template>
