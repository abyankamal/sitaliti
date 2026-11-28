<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { sppdService } from '@/Services/sppdService';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { UserPlus, Trash2, CalendarCheck2, AlertTriangle, Camera, X, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    groupPhoto: {
        type: [File, Object, null],
        default: null,
    },
    existingGroupPhotoUrl: {
        type: String,
        default: null,
    },
    removeGroupPhoto: {
        type: Boolean,
        default: false,
    },
    excludeKuitansiId: {
        type: Number,
        default: null,
    },
    defaultDate: {
        type: String,
        default: () => new Date().toISOString().split('T')[0],
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:groupPhoto',
    'update:removeGroupPhoto',
    'conflict-change',
]);

const pegawaiOptions = ref([]);
const pegawaiSearch = ref('');
const loadingPegawai = ref(false);

const conflicts = ref([]);
const checkingConflict = ref(false);
let conflictTimer = null;

const groupPhotoPreviewUrl = ref(null);
const participantPreviewUrls = ref({});

const filteredPegawaiOptions = computed(() => {
    const q = pegawaiSearch.value.trim().toLowerCase();
    if (!q) return pegawaiOptions.value;
    return pegawaiOptions.value.filter(
        (p) =>
            (p.nama && p.nama.toLowerCase().includes(q)) ||
            (p.nip && p.nip.toLowerCase().includes(q)) ||
            (p.jabatan && p.jabatan.toLowerCase().includes(q))
    );
});

const activeGroupPhotoPreview = computed(() => {
    if (groupPhotoPreviewUrl.value) return groupPhotoPreviewUrl.value;
    if (props.existingGroupPhotoUrl && !props.removeGroupPhoto) return props.existingGroupPhotoUrl;
    return null;
});

const isPhotoComplete = computed(() => {
    if (props.modelValue.length === 0) return false;
    if (activeGroupPhotoPreview.value) return true;
    return props.modelValue.every((p) => Boolean(p.foto) || (Boolean(p.foto_url) && !p.remove_foto));
});

async function loadPegawaiOptions() {
    loadingPegawai.value = true;
    try {
        pegawaiOptions.value = await sppdService.getPegawaiOptions({ limit: 300 });
    } catch {
        pegawaiOptions.value = [];
    } finally {
        loadingPegawai.value = false;
    }
}

function addParticipant() {
    const next = [
        ...props.modelValue,
        {
            pegawai_id: '',
            tanggal_berangkat: props.defaultDate || new Date().toISOString().split('T')[0],
            tanggal_pulang: props.defaultDate || new Date().toISOString().split('T')[0],
            tujuan: '',
            foto: null,
            foto_url: null,
            remove_foto: false,
        },
    ];
    emit('update:modelValue', next);
}

function removeParticipant(index) {
    if (participantPreviewUrls.value[index]) {
        URL.revokeObjectURL(participantPreviewUrls.value[index]);
        delete participantPreviewUrls.value[index];
    }
    const next = props.modelValue.filter((_, idx) => idx !== index);
    emit('update:modelValue', next);
}

function syncGroupDates() {
    if (props.modelValue.length <= 1) return;
    const first = props.modelValue[0];
    const next = props.modelValue.map((row, idx) =>
        idx === 0
            ? row
            : {
                  ...row,
                  tanggal_berangkat: first.tanggal_berangkat,
                  tanggal_pulang: first.tanggal_pulang,
                  tujuan: first.tujuan || row.tujuan,
              }
    );
    emit('update:modelValue', next);
}

function handleGroupPhotoChange(event) {
    const file = event.target.files?.[0] || null;
    if (groupPhotoPreviewUrl.value) {
        URL.revokeObjectURL(groupPhotoPreviewUrl.value);
        groupPhotoPreviewUrl.value = null;
    }
    if (file) {
        groupPhotoPreviewUrl.value = URL.createObjectURL(file);
        emit('update:groupPhoto', file);
        emit('update:removeGroupPhoto', false);
    }
    event.target.value = '';
}

function clearGroupPhoto() {
    if (groupPhotoPreviewUrl.value) {
        URL.revokeObjectURL(groupPhotoPreviewUrl.value);
        groupPhotoPreviewUrl.value = null;
    }
    emit('update:groupPhoto', null);
    emit('update:removeGroupPhoto', true);
}

function handleParticipantPhotoChange(index, event) {
    const file = event.target.files?.[0] || null;
    if (participantPreviewUrls.value[index]) {
        URL.revokeObjectURL(participantPreviewUrls.value[index]);
        delete participantPreviewUrls.value[index];
    }
    if (file) {
        participantPreviewUrls.value[index] = URL.createObjectURL(file);
        const next = props.modelValue.map((row, idx) =>
            idx === index ? { ...row, foto: file, remove_foto: false } : row
        );
        emit('update:modelValue', next);
    }
    event.target.value = '';
}

function clearParticipantPhoto(index) {
    if (participantPreviewUrls.value[index]) {
        URL.revokeObjectURL(participantPreviewUrls.value[index]);
        delete participantPreviewUrls.value[index];
    }
    const next = props.modelValue.map((row, idx) =>
        idx === index ? { ...row, foto: null, remove_foto: true } : row
    );
    emit('update:modelValue', next);
}

async function runConflictCheck() {
    const validRows = props.modelValue
        .filter((r) => r.pegawai_id && r.tanggal_berangkat && r.tanggal_pulang)
        .map((r) => ({
            pegawai_id: Number(r.pegawai_id),
            tanggal_berangkat: r.tanggal_berangkat,
            tanggal_pulang: r.tanggal_pulang,
        }));

    if (validRows.length === 0) {
        conflicts.value = [];
        emit('conflict-change', false);
        return;
    }

    checkingConflict.value = true;
    try {
        const res = await sppdService.checkScheduleConflict({
            exclude_kuitansi_id: props.excludeKuitansiId || null,
            sppd_peserta: validRows,
        });
        conflicts.value = res.conflicts || [];
        emit('conflict-change', Boolean(res.has_conflict));
    } catch {
        conflicts.value = [];
        emit('conflict-change', false);
    } finally {
        checkingConflict.value = false;
    }
}

watch(
    () =>
        props.modelValue.map((r) => `${r.pegawai_id}|${r.tanggal_berangkat}|${r.tanggal_pulang}`).join(','),
    () => {
        if (conflictTimer) clearTimeout(conflictTimer);
        conflictTimer = setTimeout(runConflictCheck, 350);
    },
    { immediate: true }
);

onMounted(() => {
    loadPegawaiOptions();
});

onBeforeUnmount(() => {
    if (conflictTimer) clearTimeout(conflictTimer);
    if (groupPhotoPreviewUrl.value) URL.revokeObjectURL(groupPhotoPreviewUrl.value);
    Object.values(participantPreviewUrls.value).forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <div class="bg-surface border border-hairline rounded-xl p-4 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-hairline pb-3">
            <div>
                <h3 class="text-sm font-semibold text-ink">Pelaksana Perjalanan Dinas (SPPD) &amp; Foto Keberangkatan</h3>
                <p class="text-xs text-ink-muted">
                    Tambahkan 1 atau lebih pelaksana. Sistem otomatis menolak jadwal bentrok lintas Sub Kegiatan &amp; Unit Kerja.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <GovBadge :variant="isPhotoComplete ? 'success' : 'warning'" size="sm">
                    <CheckCircle2 v-if="isPhotoComplete" class="w-3 h-3" />
                    <span>{{ isPhotoComplete ? 'Foto Keberangkatan Lengkap' : 'Foto Opsional di DRAFT (Wajib Submit)' }}</span>
                </GovBadge>
            </div>
        </div>

        <!-- Banner Peringatan Bentrok Jadwal -->
        <div v-if="conflicts.length > 0" class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-semantic-danger space-y-1">
            <div class="flex items-center gap-1.5 font-semibold">
                <AlertTriangle class="w-4 h-4 shrink-0" />
                <span>Terdeteksi Bentrok Jadwal Keberangkatan ({{ conflicts.length }})</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                <li v-for="(c, idx) in conflicts" :key="idx">{{ c.message }}</li>
            </ul>
        </div>

        <!-- Toolbar Pencarian & Aksi Peserta -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <input
                v-model="pegawaiSearch"
                type="text"
                placeholder="Filter dropdown pegawai (ketik Nama / NIP)..."
                class="w-full sm:w-72 h-8 px-3 text-xs bg-canvas border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
            />
            <div class="flex items-center gap-2">
                <GovButton
                    v-if="modelValue.length > 1"
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="syncGroupDates"
                >
                    <CalendarCheck2 class="w-3.5 h-3.5" />
                    <span>Samakan Tanggal Rombongan</span>
                </GovButton>
                <GovButton type="button" size="sm" @click="addParticipant">
                    <UserPlus class="w-3.5 h-3.5" />
                    <span>Tambah Pelaksana</span>
                </GovButton>
            </div>
        </div>

        <!-- Daftar Peserta -->
        <div v-if="modelValue.length === 0" class="p-6 text-center bg-canvas border border-dashed border-hairline rounded-lg text-xs text-ink-muted">
            Belum ada pelaksana SPPD ditambahkan. Klik <strong>Tambah Pelaksana</strong> untuk memilih pegawai.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(row, index) in modelValue"
                :key="index"
                class="p-3 bg-canvas border border-hairline rounded-lg space-y-2.5"
            >
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end">
                    <div class="md:col-span-4">
                        <label class="block text-xs font-medium text-ink-secondary mb-1">
                            Pegawai #{{ index + 1 }} <span class="text-semantic-danger">*</span>
                        </label>
                        <select
                            v-model="row.pegawai_id"
                            class="w-full h-8 px-2.5 text-xs bg-surface border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent focus:border-accent"
                        >
                            <option value="">{{ loadingPegawai ? 'Memuat...' : '-- Pilih Pegawai --' }}</option>
                            <option v-for="p in filteredPegawaiOptions" :key="p.id" :value="p.id">
                                {{ p.nama }} ({{ p.nip || (p.status_kepegawaian === 'NON_ASN' ? 'Non-ASN' : '-') }})
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-ink-secondary mb-1">Tgl Berangkat</label>
                        <input
                            v-model="row.tanggal_berangkat"
                            type="date"
                            class="w-full h-8 px-2 text-xs font-mono bg-surface border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-ink-secondary mb-1">Tgl Pulang</label>
                        <input
                            v-model="row.tanggal_pulang"
                            type="date"
                            :min="row.tanggal_berangkat"
                            class="w-full h-8 px-2 text-xs font-mono bg-surface border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-ink-secondary mb-1">Tujuan / Lokasi</label>
                        <input
                            v-model="row.tujuan"
                            type="text"
                            placeholder="Contoh: BPKAD Kab. Garut"
                            class="w-full h-8 px-2.5 text-xs bg-surface border border-hairline rounded-lg text-ink focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <div class="md:col-span-1 flex justify-end">
                        <button
                            type="button"
                            class="h-8 px-2 rounded-lg text-ink-muted hover:text-semantic-danger hover:bg-red-50 transition-colors"
                            title="Hapus Pelaksana"
                            @click="removeParticipant(index)"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Foto Opsional Per Peserta -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-hairline/70 text-xs">
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-surface border border-hairline text-ink-secondary hover:text-ink cursor-pointer">
                            <Camera class="w-3.5 h-3.5" />
                            <span>{{ row.foto || (row.foto_url && !row.remove_foto) ? 'Ganti Foto Peserta' : 'Upload Foto Peserta (Opsional)' }}</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png"
                                class="hidden"
                                @change="handleParticipantPhotoChange(index, $event)"
                            />
                        </label>
                        <span v-if="row.foto" class="text-semantic-success font-medium truncate max-w-[200px]">
                            {{ row.foto.name }}
                        </span>
                        <a
                            v-else-if="row.foto_url && !row.remove_foto"
                            :href="row.foto_url"
                            target="_blank"
                            class="text-accent underline"
                        >
                            Lihat Foto Tersimpan
                        </a>
                    </div>
                    <button
                        v-if="row.foto || (row.foto_url && !row.remove_foto)"
                        type="button"
                        class="text-semantic-danger hover:underline inline-flex items-center gap-1"
                        @click="clearParticipantPhoto(index)"
                    >
                        <X class="w-3 h-3" />
                        <span>Hapus Foto</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Foto Keberangkatan Bersama Rombongan -->
        <div class="p-3 bg-canvas border border-hairline rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-0.5">
                <p class="text-xs font-semibold text-ink">Foto Keberangkatan Bersama (Rombongan)</p>
                <p class="text-xs text-ink-muted">
                    Format JPG/PNG, maks 10MB. Jika diunggah, berlaku untuk seluruh peserta pada kuitansi ini.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <img
                    v-if="activeGroupPhotoPreview"
                    :src="activeGroupPhotoPreview"
                    alt="Preview Foto Keberangkatan"
                    class="w-12 h-12 rounded-lg object-cover border border-hairline shrink-0"
                />
                <label class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-medium bg-surface border border-hairline text-ink hover:bg-raised cursor-pointer">
                    <Camera class="w-3.5 h-3.5" />
                    <span>{{ activeGroupPhotoPreview ? 'Ganti Foto Bersama' : 'Pilih Foto Bersama' }}</span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png"
                        class="hidden"
                        @change="handleGroupPhotoChange"
                    />
                </label>
                <button
                    v-if="activeGroupPhotoPreview"
                    type="button"
                    class="p-1.5 rounded-lg text-semantic-danger hover:bg-red-50"
                    title="Hapus Foto Bersama"
                    @click="clearGroupPhoto"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>
