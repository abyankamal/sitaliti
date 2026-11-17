<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
const isUnitScoped = computed(() => !currentUser.value?.is_skpd_wide);

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
                <div class="bg-gov-navy-950 text-white px-6 py-4 flex items-center justify-between border-b border-gov-navy-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gov-navy-900 border border-gov-gold-500/30 flex items-center justify-center text-gov-gold-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                Pre-Entry Gatekeeper & RAK Selector
                                <span class="text-[10px] bg-gov-gold-500/20 text-gov-gold-300 px-2 py-0.5 rounded font-mono border border-gov-gold-500/30">
                                    Budget Locking
                                </span>
                            </h3>
                            <p class="text-xs text-slate-300 mt-0.5">
                                Kunci unit organisasi, subkegiatan, rekening belanja, dan bulan alokasi RAK sebelum memulai kuitansi
                            </p>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-white p-1 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body: 2 Columns Layout -->
                <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6 bg-slate-50/50">
                    <!-- Sisi Kiri (Kolom 1 - 7): Hierarki Anggaran & Bulan RAK -->
                    <div class="md:col-span-7 space-y-4">
                        <!-- Select 1: Unit Organisasi -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gov-navy-900 mb-1.5">
                                1. Unit Organisasi / SKPD <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="selectedUnitId"
                                :disabled="isUnitScoped"
                                class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800 text-gov-navy-950 font-medium disabled:bg-slate-100 disabled:text-slate-500"
                            >
                                <option value="" disabled>-- Pilih Unit Organisasi --</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">
                                    {{ u.code }} - {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Select 2: Subkegiatan -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gov-navy-900 mb-1.5">
                                2. Program & Subkegiatan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="selectedSubActivityId"
                                :disabled="!selectedUnitId"
                                class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800 text-gov-navy-950 font-medium disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="" disabled>-- Pilih Subkegiatan --</option>
                                <option v-for="s in subActivities" :key="s.id" :value="s.id">
                                    {{ s.code }} - {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Select 3: Rekening Belanja -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gov-navy-900 mb-1.5">
                                3. Kode Rekening Belanja <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="selectedAccountId"
                                :disabled="!selectedSubActivityId"
                                class="w-full text-xs rounded-lg border-gov-surface-border py-2 px-3 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800 text-gov-navy-950 font-medium disabled:bg-slate-100 disabled:text-slate-400"
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
                                <label class="block text-xs font-bold uppercase tracking-wider text-gov-navy-900">
                                    4. Pilih Alokasi Bulan RAK (2026) <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[11px] text-slate-500">
                                    Bulan Berjalan: <strong class="text-gov-navy-900">{{ NAMA_BULAN[activeMonth - 1] || `Bulan ${activeMonth}` }} (Bulan {{ activeMonth }})</strong>
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
                                        // Selected state
                                        selectedMonth === (index + 1)
                                            ? 'bg-gov-navy-900 text-white font-bold ring-2 ring-gov-gold-500 shadow-sm'
                                            : index + 1 > activeMonth
                                                ? 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed opacity-60'
                                                : 'bg-white text-slate-700 border border-slate-200 hover:border-gov-navy-800 hover:bg-slate-50'
                                    ]"
                                    class="py-2 px-1 text-center rounded-lg text-xs transition-all relative"
                                >
                                    <div class="text-[10px] uppercase font-mono opacity-75">Bln {{ index + 1 }}</div>
                                    <div class="font-medium truncate">{{ bulan.slice(0, 3) }}</div>
                                    <span
                                        v-if="index + 1 > activeMonth"
                                        class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-slate-300 text-slate-600 flex items-center justify-center text-[8px]"
                                        title="Bulan mendatang terkunci"
                                    >
                                        🔒
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan (Kolom 8 - 12): Kartu Status Real-Time Pagu & Kategori Belanja -->
                    <div class="md:col-span-5 flex flex-col justify-between">
                        <div class="bg-white rounded-xl border border-gov-surface-border p-4 shadow-sm space-y-4">
                            <!-- Kategori Belanja Info -->
                            <div v-if="categoryInfo" class="p-3 bg-gov-navy-50 rounded-lg border border-gov-navy-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-gov-navy-700 uppercase">Kategori Belanja</span>
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-gov-navy-900 text-white font-mono">
                                        {{ categoryInfo.code }}
                                    </span>
                                </div>
                                <h4 class="text-xs font-bold text-gov-navy-950 mt-1">{{ categoryInfo.name }}</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-tight">{{ categoryInfo.description }}</p>
                            </div>

                            <!-- Pagu Real-Time Breakdown -->
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                                    <span>Simulasi Pagu RAK</span>
                                    <span v-if="loadingContext" class="text-[10px] text-gov-navy-600 animate-pulse">Menghitung...</span>
                                </h4>

                                <div v-if="rakContext" class="space-y-2 text-xs">
                                    <div class="flex justify-between py-1 border-b border-dashed border-slate-200">
                                        <span class="text-slate-600">Alokasi Murni Bln {{ selectedMonth }}:</span>
                                        <span class="font-medium text-slate-900 tabular-nums">
                                            {{ formatRupiah(rakContext.pagu_murni_bulan) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-dashed border-slate-200 text-emerald-700">
                                        <span class="flex items-center gap-1">
                                            <span>(+) Carry-Over Bulan Lalu:</span>
                                        </span>
                                        <span class="font-semibold tabular-nums">
                                            {{ formatRupiah(rakContext.carry_over_lalu) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-slate-200 font-semibold text-gov-navy-900">
                                        <span>Total Plafon Tersedia:</span>
                                        <span class="tabular-nums">
                                            {{ formatRupiah(rakContext.total_tersedia) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between py-1 border-b border-dashed border-slate-200 text-amber-700">
                                        <span>(-) Terkunci / Komitmen:</span>
                                        <span class="font-medium tabular-nums">
                                            {{ formatRupiah(rakContext.terkunci_komitmen) }}
                                        </span>
                                    </div>

                                    <!-- Sisa Bebas Input -->
                                    <div class="p-3 rounded-lg mt-3" :class="[rakContext.is_future_month ? 'bg-rose-50 border border-rose-200' : 'bg-emerald-50 border border-emerald-200']">
                                        <div class="text-[11px] font-semibold" :class="rakContext.is_future_month ? 'text-rose-800' : 'text-emerald-800'">
                                            {{ rakContext.is_future_month ? 'STATUS PAGU TERKUNCI' : 'SISA PAGU DAPAT DIGUNAKAN' }}
                                        </div>
                                        <div class="text-lg font-black tabular-nums mt-0.5" :class="rakContext.is_future_month ? 'text-rose-700' : 'text-emerald-700'">
                                            {{ formatRupiah(rakContext.sisa_dapat_digunakan) }}
                                        </div>
                                        <p v-if="rakContext.is_future_month" class="text-[10px] text-rose-600 mt-1 leading-tight">
                                            Aturan Sistem: Pagu alokasi bulan mendatang (di atas {{ NAMA_BULAN[activeMonth - 1] || `Bulan ${activeMonth}` }}) belum dapat ditarik untuk pencairan saat ini.
                                        </p>
                                        <p v-else class="text-[10px] text-emerald-700 mt-1 leading-tight">
                                            Pagu mencukupi untuk pembuatan kuitansi belanja baru.
                                        </p>
                                    </div>
                                </div>

                                <div v-else class="text-center py-8 text-slate-400 text-xs">
                                    Pilih rekening belanja dan bulan alokasi untuk melihat ketersediaan pagu RAK
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Konfirmasi -->
                        <div class="mt-4 pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                            <GovButton variant="secondary" size="md" @click="$emit('close')">
                                Batal
                            </GovButton>
                            <GovButton
                                variant="gold"
                                size="md"
                                :disabled="!canProceed"
                                @click="handleConfirm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Lanjutkan ke Input Kuitansi
                            </GovButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

