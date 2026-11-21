<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Lock, X, Check } from 'lucide-vue-next';
import GovButton from '@/Components/UI/GovButton.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { budgetService } from '@/Services/budgetService';
import { formatRupiah } from '@/Utils/currency';
import { NAMA_BULAN } from '@/Utils/date';
import { BUDGET_CATEGORIES } from '@/Constants/budgetCategories';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'select']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isUnitScoped = computed(() => !currentUser.value?.can_input_cross_unit);

const units = ref([]);
const subActivities = ref([]);
const accounts = ref([]);

const currentMonthFallback = new Date().getMonth() + 1;

const selectedUnitId = ref('');
const selectedSubActivityId = ref('');
const selectedAccountId = ref('');
const selectedMonth = ref(currentMonthFallback);

const rakContext = ref(null);
const loadingContext = ref(false);
const activeMonth = computed(() => Number(rakContext.value?.active_system_month || currentMonthFallback));

onMounted(async () => {
    units.value = await budgetService.getUnits();
    if (isUnitScoped.value && currentUser.value?.unit_kerja_id) {
        selectedUnitId.value = currentUser.value.unit_kerja_id;
    } else if (units.value.length === 1) {
        selectedUnitId.value = units.value[0].id;
    }
});

// Watch pergantian Unit
watch(selectedUnitId, async (newVal) => {
    selectedSubActivityId.value = '';
    selectedAccountId.value = '';
    accounts.value = [];
    rakContext.value = null;

    if (newVal) {
        subActivities.value = await budgetService.getSubActivitiesByUnit(newVal);
        if (subActivities.value.length === 1) {
            selectedSubActivityId.value = subActivities.value[0].id;
        }
    } else {
        subActivities.value = [];
    }
});

// Watch pergantian Subkegiatan
watch(selectedSubActivityId, (newVal) => {
    selectedAccountId.value = '';
    rakContext.value = null;

    if (newVal) {
        const found = subActivities.value.find(s => String(s.id) === String(newVal) || s.code === newVal);
        accounts.value = found ? (found.rekening || []) : [];
    } else {
        accounts.value = [];
    }
});

// Watch pergantian Rekening atau Bulan RAK
watch([selectedAccountId, selectedMonth], async ([accId, month]) => {
    if (accId && month) {
        loadingContext.value = true;
        try {
            rakContext.value = await budgetService.getRakContext(accId, month);
        } finally {
            loadingContext.value = false;
        }
    } else {
        rakContext.value = null;
    }
});

const selectedAccountData = computed(() => {
    return accounts.value.find(a => a.id === Number(selectedAccountId.value));
});

const categoryInfo = computed(() => {
    if (!selectedAccountData.value) return null;
    const catCode = selectedAccountData.value.kategori;
    const key = catCode.replace('.', '');
    return BUDGET_CATEGORIES[key] || { code: catCode, name: 'Belanja Barang/Jasa', description: '' };
});

const canProceed = computed(() => {
    return (
        selectedUnitId.value &&
        selectedSubActivityId.value &&
        selectedAccountId.value &&
        selectedMonth.value &&
        rakContext.value &&
        !rakContext.value.is_future_month &&
        rakContext.value.sisa_dapat_digunakan > 0
    );
});

function handleConfirm() {
    if (!canProceed.value) return;

    const selectedUnit = units.value.find(u => Number(u.id) === Number(selectedUnitId.value));
    const selectedSub = subActivities.value.find(s => String(s.id) === String(selectedSubActivityId.value) || s.code === selectedSubActivityId.value);

    emit('select', {
        unit: selectedUnit,
        subActivity: selectedSub,
        account: selectedAccountData.value,
        category: categoryInfo.value,
        month: selectedMonth.value,
        monthName: NAMA_BULAN[selectedMonth.value - 1],
        rakContext: rakContext.value,
    });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl border border-gov-surface-border overflow-hidden">
                <!-- Modal Header -->
                <div class="bg-surface px-6 py-4 flex items-center justify-between border-b border-hairline">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-accent-soft border border-accent-border flex items-center justify-center text-accent shrink-0">
                            <Lock class="w-4 h-4" :stroke-width="1.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-ink">
                                Pre-Entry Gatekeeper & RAK Selector
                            </h3>
                            <p class="text-xs text-ink-muted mt-0.5">
                                Kunci unit organisasi, subkegiatan, rekening belanja, dan bulan alokasi RAK sebelum memulai kuitansi
                            </p>
                        </div>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="w-8 h-8 inline-flex items-center justify-center text-ink-muted hover:text-ink hover:bg-raised rounded-lg transition-colors duration-150 ease-unslop"
                        title="Tutup"
                    >
                        <X class="w-4 h-4" :stroke-width="1.5" />
                    </button>
                </div>

                <!-- Modal Body: 2 Columns Layout -->
                <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6 bg-canvas">
                    <!-- Sisi Kiri (Kolom 1 - 7): Hierarki Anggaran & Bulan RAK -->
                    <div class="md:col-span-7 space-y-4">
                        <!-- Select 1: Unit Organisasi -->
                        <div>
                            <label class="block text-xs font-medium text-ink-secondary mb-1.5">
                                1. Unit Organisasi / SKPD <span class="text-semantic-danger">*</span>
                            </label>
                            <select
                                v-model="selectedUnitId"
                                :disabled="isUnitScoped"
                                class="w-full h-10 text-xs rounded-lg border-hairline bg-surface px-3 focus:border-accent focus:ring-1 focus:ring-accent text-ink font-medium disabled:bg-raised disabled:text-ink-muted"
                            >
                                <option value="" disabled>-- Pilih Unit Organisasi --</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">
                                    {{ u.code }} - {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Select 2: Subkegiatan -->
                        <div>
                            <label class="block text-xs font-medium text-ink-secondary mb-1.5">
                                2. Program & Subkegiatan <span class="text-semantic-danger">*</span>
                            </label>
                            <select
                                v-model="selectedSubActivityId"
                                :disabled="!selectedUnitId"
                                class="w-full h-10 text-xs rounded-lg border-hairline bg-surface px-3 focus:border-accent focus:ring-1 focus:ring-accent text-ink font-medium disabled:bg-raised disabled:text-ink-muted"
                            >
                                <option value="" disabled>-- Pilih Subkegiatan --</option>
                                <option v-for="s in subActivities" :key="s.id" :value="s.id">
                                    {{ s.code }} - {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Select 3: Rekening Belanja -->
                        <div>
                            <label class="block text-xs font-medium text-ink-secondary mb-1.5">
                                3. Kode Rekening Belanja <span class="text-semantic-danger">*</span>
                            </label>
                            <select
                                v-model="selectedAccountId"
                                :disabled="!selectedSubActivityId"
                                class="w-full h-10 text-xs rounded-lg border-hairline bg-surface px-3 focus:border-accent focus:ring-1 focus:ring-accent text-ink font-medium disabled:bg-raised disabled:text-ink-muted"
                            >
                                <option value="" disabled>-- Pilih Rekening Belanja --</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">
                                    [{{ a.kategori }}] {{ a.code }} - {{ a.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Selector 4: Bulan RAK 12 Bulan Grid -->
                        <div class="pt-2">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-medium text-ink-secondary">
                                    4. Pilih Alokasi Bulan RAK (2026) <span class="text-semantic-danger">*</span>
                                </label>
                                <span class="text-xs text-ink-muted">
                                    Bulan berjalan: <span class="font-medium text-ink">{{ NAMA_BULAN[activeMonth - 1] || `Bulan ${activeMonth}` }} ({{ activeMonth }})</span>
                                </span>
                            </div>

                            <!-- 12 Bulan Buttons Grid -->
                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                                <button
                                    v-for="(bulan, index) in NAMA_BULAN"
                                    :key="index"
                                    type="button"
                                    @click="selectedMonth = index + 1"
                                    :class="[
                                        selectedMonth === (index + 1)
                                            ? 'bg-accent text-white font-medium border-accent shadow-subtle'
                                            : index + 1 > activeMonth
                                                ? 'bg-raised text-ink-muted border-hairline cursor-not-allowed opacity-70'
                                                : 'bg-surface text-ink-secondary border-hairline hover:border-accent hover:text-ink'
                                    ]"
                                    class="py-2 px-1.5 text-center rounded-lg border text-xs transition-colors duration-150 ease-unslop relative"
                                >
                                    <div class="text-xs font-mono opacity-75">Bln {{ index + 1 }}</div>
                                    <div class="font-medium truncate">{{ bulan.slice(0, 3) }}</div>
                                    <span
                                        v-if="index + 1 > activeMonth"
                                        class="absolute top-1 right-1 text-ink-muted"
                                        title="Bulan mendatang terkunci"
                                    >
                                        <Lock class="w-3 h-3" :stroke-width="1.5" />
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan (Kolom 8 - 12): Kartu Status Real-Time Pagu & Kategori Belanja -->
                    <div class="md:col-span-5 flex flex-col justify-between">
                        <div class="bg-surface rounded-xl border border-hairline p-4 shadow-subtle space-y-4">
                            <!-- Kategori Belanja Info -->
                            <div v-if="categoryInfo" class="p-3 bg-canvas rounded-lg border border-hairline">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-ink-secondary">Kategori Belanja</span>
                                    <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-accent-soft text-accent border border-accent-border font-mono">
                                        {{ categoryInfo.code }}
                                    </span>
                                </div>
                                <h4 class="text-xs font-semibold text-ink mt-1.5">{{ categoryInfo.name }}</h4>
                                <p class="text-xs text-ink-muted mt-1 leading-relaxed">{{ categoryInfo.description }}</p>
                            </div>

                            <!-- Pagu Real-Time Breakdown -->
                            <div>
                                <h4 class="text-xs font-medium text-ink-secondary mb-3 flex items-center justify-between">
                                    <span>Simulasi Pagu RAK</span>
                                    <span v-if="loadingContext" class="text-xs text-accent">Menghitung...</span>
                                </h4>

                                <div v-if="rakContext" class="space-y-2 text-xs">
                                    <div class="flex justify-between py-1 border-b border-dashed border-hairline">
                                        <span class="text-ink-secondary">Alokasi Murni Bln {{ selectedMonth }}</span>
                                        <span class="font-mono font-medium text-ink tabular-nums">
                                            {{ formatRupiah(rakContext.pagu_murni_bulan) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-dashed border-hairline text-semantic-success">
                                        <span>(+) Carry-Over Bulan Lalu</span>
                                        <span class="font-mono font-medium tabular-nums">
                                            {{ formatRupiah(rakContext.carry_over_lalu) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-hairline font-medium text-ink">
                                        <span>Total Plafon Tersedia</span>
                                        <span class="font-mono tabular-nums">
                                            {{ formatRupiah(rakContext.total_tersedia) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-dashed border-hairline text-semantic-warning">
                                        <span>(-) Terkunci / Komitmen</span>
                                        <span class="font-mono font-medium tabular-nums">
                                            {{ formatRupiah(rakContext.terkunci_komitmen) }}
                                        </span>
                                    </div>

                                    <!-- Sisa Bebas Input -->
                                    <div class="p-3 rounded-lg mt-3 border" :class="[rakContext.is_future_month ? 'bg-red-50/70 border-red-200' : 'bg-emerald-50/70 border-emerald-200']">
                                        <div class="text-xs font-medium" :class="rakContext.is_future_month ? 'text-semantic-danger' : 'text-semantic-success'">
                                            {{ rakContext.is_future_month ? 'Status Pagu Terkunci' : 'Sisa Pagu Dapat Digunakan' }}
                                        </div>
                                        <div class="text-lg font-medium font-mono tabular-nums mt-0.5" :class="rakContext.is_future_month ? 'text-semantic-danger' : 'text-semantic-success'">
                                            {{ formatRupiah(rakContext.sisa_dapat_digunakan) }}
                                        </div>
                                        <p v-if="rakContext.is_future_month" class="text-xs text-semantic-danger mt-1 leading-relaxed">
                                            Aturan sistem: pagu alokasi bulan mendatang (di atas {{ NAMA_BULAN[activeMonth - 1] || `Bulan ${activeMonth}` }}) belum dapat ditarik untuk pencairan saat ini.
                                        </p>
                                        <p v-else class="text-xs text-semantic-success mt-1 leading-relaxed">
                                            Pagu mencukupi untuk pembuatan kuitansi belanja baru.
                                        </p>
                                    </div>
                                </div>

                                <div v-else class="text-center py-8 text-ink-muted text-xs">
                                    Pilih rekening belanja dan bulan alokasi untuk melihat ketersediaan pagu RAK
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Konfirmasi -->
                        <div class="mt-4 pt-4 border-t border-hairline flex items-center justify-end gap-3">
                            <GovButton variant="secondary" size="md" @click="$emit('close')">
                                Batal
                            </GovButton>
                            <GovButton
                                variant="primary"
                                size="md"
                                :disabled="!canProceed"
                                @click="handleConfirm"
                            >
                                <Check class="w-4 h-4" :stroke-width="1.5" />
                                Lanjutkan ke Input Kuitansi
                            </GovButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

