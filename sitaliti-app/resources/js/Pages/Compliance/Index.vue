<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { complianceService } from '@/Services/complianceService';
import { formatRupiah } from '@/Utils/currency';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const toast = useToast();
const { confirm } = useConfirm();

const activeTab = ref('settings'); // 'settings' | 'tax' | 'sbm'
const tahunAnggaran = ref(new Date().getFullYear());
const loading = ref(true);

const settings = ref([]);
const taxRates = ref([]);
const sbmItems = ref([]);

const taxForm = ref({
    kode_pajak: 'PPN_11',
    nama_pajak: '',
    tarif: 0.11,
    ambang_batas: '',
    nominal_tetap: '',
    is_active: true,
});
const isSavingTax = ref(false);

const sbmForm = ref({
    kategori_belanja: 'B1_ATK',
    kode_sbm: '',
    uraian_standar: '',
    satuan: 'Unit',
    harga_maksimal: 0,
    is_active: true,
});
const isSavingSbm = ref(false);

async function loadData() {
    loading.value = true;
    try {
        const data = await complianceService.getOverview(tahunAnggaran.value);
        settings.value = data.settings || [];
        taxRates.value = data.tax_rates || [];
        sbmItems.value = data.sbm_items || [];
    } catch (err) {
        toast.error('Gagal Memuat Data', err.response?.data?.message || 'Tidak dapat memuat pengaturan kepatuhan.');
    } finally {
        loading.value = false;
    }
}

function isSettingActive(val) {
    return val === true || val === 'true' || val === '1' || val === 1;
}

async function handleToggleSetting(setting) {
    const nextVal = !isSettingActive(setting.value);
    const label = nextVal ? 'Aktifkan' : 'Nonaktifkan';

    await confirm({
        title: `${label} Kebijakan Kepatuhan?`,
        message: `${label} pengaturan "${setting.key}"?`,
        details: setting.description || '',
        variant: nextVal ? 'primary' : 'warning',
        confirmText: `Ya, ${label}`,
        onConfirm: async () => {
            await complianceService.updateSetting(setting.key, nextVal);
            toast.success('Pengaturan Disimpan', `Kebijakan ${setting.key} berhasil diperbarui.`);
            await loadData();
        },
    });
}

function editTaxRow(row) {
    taxForm.value = {
        kode_pajak: row.kode_pajak,
        nama_pajak: row.nama_pajak,
        tarif: Number(row.tarif),
        ambang_batas: row.ambang_batas ?? '',
        nominal_tetap: row.nominal_tetap ?? '',
        is_active: Boolean(row.is_active),
    };
}

async function submitTaxRate() {
    isSavingTax.value = true;
    try {
        await complianceService.upsertTaxRate({
            tahun_anggaran: Number(tahunAnggaran.value),
            kode_pajak: taxForm.value.kode_pajak,
            nama_pajak: taxForm.value.nama_pajak,
            tarif: Number(taxForm.value.tarif),
            ambang_batas: taxForm.value.ambang_batas === '' ? null : Number(taxForm.value.ambang_batas),
            nominal_tetap: taxForm.value.nominal_tetap === '' ? null : Number(taxForm.value.nominal_tetap),
            is_active: taxForm.value.is_active,
        });
        toast.success('Tarif Pajak Disimpan', `Tarif ${taxForm.value.kode_pajak} (${tahunAnggaran.value}) berhasil disimpan.`);
        await loadData();
    } catch (err) {
        toast.error('Gagal Menyimpan Tarif', err.response?.data?.message || 'Periksa kembali input tarif pajak.');
    } finally {
        isSavingTax.value = false;
    }
}

function editSbmRow(row) {
    sbmForm.value = {
        kategori_belanja: row.kategori_belanja,
        kode_sbm: row.kode_sbm,
        uraian_standar: row.uraian_standar,
        satuan: row.satuan,
        harga_maksimal: Number(row.harga_maksimal),
        is_active: Boolean(row.is_active),
    };
}

async function submitSbmItem() {
    isSavingSbm.value = true;
    try {
        await complianceService.upsertSbmItem({
            tahun_anggaran: Number(tahunAnggaran.value),
            kategori_belanja: sbmForm.value.kategori_belanja,
            kode_sbm: sbmForm.value.kode_sbm,
            uraian_standar: sbmForm.value.uraian_standar,
            satuan: sbmForm.value.satuan,
            harga_maksimal: Number(sbmForm.value.harga_maksimal),
            is_active: sbmForm.value.is_active,
        });
        toast.success('Item SBM Disimpan', `Standar ${sbmForm.value.kode_sbm} (${tahunAnggaran.value}) berhasil disimpan.`);
        sbmForm.value = {
            kategori_belanja: 'B1_ATK',
            kode_sbm: '',
            uraian_standar: '',
            satuan: 'Unit',
            harga_maksimal: 0,
            is_active: true,
        };
        await loadData();
    } catch (err) {
        toast.error('Gagal Menyimpan SBM', err.response?.data?.message || 'Periksa kembali input item SBM.');
    } finally {
        isSavingSbm.value = false;
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Pengaturan Kepatuhan & SBM" />

    <GovAppLayout title="Pengaturan Kepatuhan, Tarif Pajak & SBM">
        <template #header>
            <div>
                <h1 class="text-lg font-semibold text-ink">Pengaturan Kepatuhan, Tarif Pajak &amp; SBM</h1>
                <p class="text-xs text-ink-muted mt-0.5">Konfigurasi gerbang kepatuhan, tarif pajak per tahun anggaran, dan standar biaya masukan (SBM/SSH).</p>
            </div>
        </template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <button
                        v-for="tab in [
                            { id: 'settings', label: 'Kebijakan Gerbang' },
                            { id: 'tax', label: 'Tarif Pajak per Tahun' },
                            { id: 'sbm', label: 'Master SBM / SSH' },
                        ]"
                        :key="tab.id"
                        type="button"
                        @click="activeTab = tab.id"
                        :class="[
                            'px-4 py-2 rounded-lg text-xs font-semibold transition-colors',
                            activeTab === tab.id
                                ? 'bg-accent text-white shadow-subtle'
                                : 'bg-surface text-ink-secondary border border-hairline hover:bg-raised',
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <label class="font-semibold text-ink-secondary">Tahun Anggaran:</label>
                    <input
                        v-model.number="tahunAnggaran"
                        type="number"
                        min="2020"
                        max="2100"
                        @change="loadData"
                        class="w-24 rounded-lg border-hairline text-xs py-1.5 px-2.5"
                    />
                </div>
            </div>

            <!-- Tab 1: Kebijakan Gerbang -->
            <GovCard v-show="activeTab === 'settings'" title="Kebijakan Gerbang Kepatuhan e-Verifikatur">
                <div v-if="loading" class="text-xs text-ink-muted py-4">Memuat pengaturan...</div>
                <div v-else class="divide-y divide-hairline">
                    <div
                        v-for="setting in settings"
                        :key="setting.key"
                        class="py-3.5 flex items-center justify-between gap-4"
                    >
                        <div>
                            <div class="font-mono text-xs font-bold text-ink">{{ setting.key }}</div>
                            <p class="text-xs text-ink-secondary mt-0.5">{{ setting.description }}</p>
                        </div>
                        <GovButton
                            :variant="isSettingActive(setting.value) ? 'primary' : 'secondary'"
                            size="sm"
                            @click="handleToggleSetting(setting)"
                        >
                            {{ isSettingActive(setting.value) ? 'Aktif' : 'Nonaktif' }}
                        </GovButton>
                    </div>
                </div>
            </GovCard>

            <!-- Tab 2: Tarif Pajak per Tahun -->
            <div v-show="activeTab === 'tax'" class="space-y-4">
                <GovCard title="Tambah / Ubah Tarif Pajak">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Kode Pajak</label>
                            <input v-model="taxForm.kode_pajak" type="text" class="w-full rounded border-hairline text-xs py-1.5 px-2.5 font-mono" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-ink-secondary mb-1">Nama Pajak</label>
                            <input v-model="taxForm.nama_pajak" type="text" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Tarif Desimal (mis. 0.11)</label>
                            <input v-model.number="taxForm.tarif" type="number" step="0.0001" min="0" max="1" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Ambang Batas (Rp)</label>
                            <input v-model="taxForm.ambang_batas" type="number" min="0" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Nominal Tetap (Rp)</label>
                            <input v-model="taxForm.nominal_tetap" type="number" min="0" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <GovButton variant="primary" size="sm" :loading="isSavingTax" @click="submitTaxRate">
                            Simpan Tarif Pajak
                        </GovButton>
                    </div>
                </GovCard>

                <GovCard :title="`Daftar Tarif Pajak Tahun ${tahunAnggaran}`">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs divide-y divide-hairline">
                            <thead class="bg-raised text-ink-secondary">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Kode</th>
                                    <th class="px-3 py-2 text-left font-semibold">Nama Pajak</th>
                                    <th class="px-3 py-2 text-right font-semibold">Tarif</th>
                                    <th class="px-3 py-2 text-right font-semibold">Ambang Batas</th>
                                    <th class="px-3 py-2 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                <tr v-for="row in taxRates" :key="row.id">
                                    <td class="px-3 py-2 font-mono font-semibold text-ink">{{ row.kode_pajak }}</td>
                                    <td class="px-3 py-2">{{ row.nama_pajak }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ (Number(row.tarif) * 100).toFixed(2) }}%</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ row.ambang_batas ? formatRupiah(row.ambang_batas) : '-' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="editTaxRow(row)" class="text-accent font-semibold hover:underline">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </GovCard>
            </div>

            <!-- Tab 3: Master SBM / SSH -->
            <div v-show="activeTab === 'sbm'" class="space-y-4">
                <GovCard title="Tambah / Ubah Standar Biaya Masukan (SBM/SSH)">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Kategori Belanja</label>
                            <select v-model="sbmForm.kategori_belanja" class="w-full rounded border-hairline text-xs py-1.5 px-2.5">
                                <option value="B1_ATK">B1_ATK</option>
                                <option value="B2_MAMIN">B2_MAMIN</option>
                                <option value="B3_HONOR">B3_HONOR</option>
                                <option value="B4_PERJADIN">B4_PERJADIN</option>
                                <option value="B5_PEMELIHARAAN">B5_PEMELIHARAAN</option>
                                <option value="B6_MODAL">B6_MODAL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Kode SBM</label>
                            <input v-model="sbmForm.kode_sbm" type="text" placeholder="SBM-ATK-002" class="w-full rounded border-hairline text-xs py-1.5 px-2.5 font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Satuan</label>
                            <input v-model="sbmForm.satuan" type="text" placeholder="Rim / OK / OJ" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-ink-secondary mb-1">Uraian Standar</label>
                            <input v-model="sbmForm.uraian_standar" type="text" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                        <div>
                            <label class="block font-semibold text-ink-secondary mb-1">Harga Maksimal (Rp)</label>
                            <input v-model.number="sbmForm.harga_maksimal" type="number" min="0" class="w-full rounded border-hairline text-xs py-1.5 px-2.5" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <GovButton variant="primary" size="sm" :loading="isSavingSbm" @click="submitSbmItem">
                            Simpan Item SBM
                        </GovButton>
                    </div>
                </GovCard>

                <GovCard :title="`Daftar Master SBM/SSH Tahun ${tahunAnggaran}`">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs divide-y divide-hairline">
                            <thead class="bg-raised text-ink-secondary">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Kode</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kategori</th>
                                    <th class="px-3 py-2 text-left font-semibold">Uraian Standar</th>
                                    <th class="px-3 py-2 text-left font-semibold">Satuan</th>
                                    <th class="px-3 py-2 text-right font-semibold">Batas Maksimal</th>
                                    <th class="px-3 py-2 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                <tr v-for="row in sbmItems" :key="row.id">
                                    <td class="px-3 py-2 font-mono font-semibold text-ink">{{ row.kode_sbm }}</td>
                                    <td class="px-3 py-2">{{ row.kategori_belanja }}</td>
                                    <td class="px-3 py-2">{{ row.uraian_standar }}</td>
                                    <td class="px-3 py-2">{{ row.satuan }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatRupiah(row.harga_maksimal) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="editSbmRow(row)" class="text-accent font-semibold hover:underline">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </GovCard>
            </div>
        </div>
    </GovAppLayout>
</template>
