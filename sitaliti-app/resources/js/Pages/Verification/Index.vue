<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import GovCard from '@/Components/UI/GovCard.vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';
import { verificationService } from '@/Services/verificationService';
import { receiptService } from '@/Services/receiptService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';
import { STATUS_LABELS, STATUS_VARIANTS } from '@/Constants/workflowStatus';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const userRole = computed(() => user.value.role || 'VERIFIKATOR');
const isKelurahan = computed(() => user.value.is_kelurahan_scoped);

const queue = ref([]);
const loading = ref(true);
const activeTab = ref('ALL'); // ALL, MENUNGGU_PPTK, MENUNGGU_LURAH, MENUNGGU_VERIFIKASI, REVISI_MINOR, LOLOS_VERIFIKASI

// Notification Banner (In-App)
const notification = ref({ show: false, type: 'success', message: '' });

function showNotification(message, type = 'success') {
    notification.value = { show: true, type, message };
    setTimeout(() => {
        notification.value.show = false;
    }, 6000);
}

// Modal Otorisasi (PPTK / Lurah)
const showAuthModal = ref(false);
const authActionRole = ref('PPTK'); // 'PPTK' | 'LURAH'
const authActionType = ref('APPROVE'); // 'APPROVE' | 'REJECT'
const selectedItem = ref(null);
const authNotes = ref('');
const isSubmittingAuth = ref(false);

async function loadQueue() {
    loading.value = true;
    try {
        queue.value = await verificationService.getQueue();
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (userRole.value === 'PPTK') {
        activeTab.value = 'MENUNGGU_PPTK';
    } else if (userRole.value === 'LURAH') {
        activeTab.value = 'MENUNGGU_LURAH';
    }
    loadQueue();
});

const filteredQueue = computed(() => {
    if (activeTab.value === 'ALL') return queue.value;
    return queue.value.filter(item => item.status === activeTab.value);
});

const countPptk = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_PPTK').length);
const countLurah = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_LURAH').length);
const countVerifikasi = computed(() => queue.value.filter(item => item.status === 'MENUNGGU_VERIFIKASI').length);
const countRevisi = computed(() => queue.value.filter(item => item.status === 'REVISI_MINOR').length);
const countLolos = computed(() => queue.value.filter(item => item.status === 'LOLOS_VERIFIKASI').length);

function openAuthModal(item, role, action) {
    selectedItem.value = item;
    authActionRole.value = role;
    authActionType.value = action;
    authNotes.value = '';
    showAuthModal.value = true;
}

async function handleAuthSubmit() {
    if (!selectedItem.value) return;

    if (authActionType.value === 'REJECT' && (!authNotes.value || authNotes.value.trim().length < 3)) {
        showNotification('Mohon cantumkan alasan penolakan secara jelas (minimal 3 karakter).', 'error');
        return;
    }

    isSubmittingAuth.value = true;
    const targetId = selectedItem.value.kuitansi_id || selectedItem.value.id;

    try {
        if (authActionRole.value === 'LURAH') {
            if (authActionType.value === 'APPROVE') {
                await receiptService.approveByLurah(targetId, authNotes.value || 'Disetujui dan diotorisasi oleh Lurah.');
                showNotification('Kuitansi berhasil diotorisasi Lurah dan diteruskan ke Tim Verifikator Kecamatan.');
            } else {
                await receiptService.rejectByLurah(targetId, authNotes.value);
                showNotification('Kuitansi dikembalikan oleh Lurah ke draf Operator dan pagu kas dipulihkan.');
            }
        } else {
            // PPTK
            if (authActionType.value === 'APPROVE') {
                await receiptService.approveByPptk(targetId, authNotes.value);
                showNotification('Kuitansi berhasil disetujui PPTK dan diteruskan ke tahapan selanjutnya.');
            } else {
                await receiptService.rejectByPptk(targetId, authNotes.value);
                showNotification('Kuitansi ditolak oleh PPTK dan dikembalikan ke draf Operator.');
            }
        }
        showAuthModal.value = false;
        await loadQueue();
    } catch (err) {
        showNotification(
            err.response?.data?.error?.message || err.response?.data?.message || err.message || 'Terjadi kesalahan saat memproses otorisasi.',
            'error'
        );
    } finally {
        isSubmittingAuth.value = false;
    }
}
</script>

<template>
    <Head title="Antrean Verifikasi Kuitansi SPJ" />

    <GovAppLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-lg font-bold text-gov-navy-950 flex items-center gap-2">
                        <span>Antrean Verifikasi & Otorisasi Kuitansi</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-md font-semibold bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200">
                            {{ isKelurahan ? (user.unit_kerja?.nama_unit || 'Kelurahan') : 'Tim Verifikator & PPK' }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Pemeriksaan bukti kepatuhan akuntabilitas dan alur pengesahan bertingkat sebelum dibundel menjadi SPJ
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <!-- In-App Notification Banner -->
            <div
                v-if="notification.show"
                class="p-3.5 rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm transition-colors duration-150 ease-unslop border"
                :class="notification.type === 'error' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
            >
                <div class="flex items-center gap-2">
                    <svg v-if="notification.type === 'error'" class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ notification.message }}</span>
                </div>
                <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 ml-4">&times;</button>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto text-xs font-medium">
                <button
                    @click="activeTab = 'ALL'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap',
                        activeTab === 'ALL'
                            ? 'bg-gov-navy-950 text-white font-bold'
                            : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                    ]"
                >
                    Semua Berkas ({{ queue.length }})
                </button>
                <button
                    @click="activeTab = 'MENUNGGU_PPTK'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_PPTK'
                            ? 'bg-amber-600 text-white font-bold'
                            : 'bg-white text-amber-700 hover:bg-amber-50 border border-amber-200'
                    ]"
                >
                    <span>Persetujuan PPTK</span>
                    <span v-if="countPptk > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-bold">
                        {{ countPptk }}
                    </span>
                </button>
                <button
                    v-if="isKelurahan || countLurah > 0 || userRole === 'LURAH'"
                    @click="activeTab = 'MENUNGGU_LURAH'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_LURAH'
                            ? 'bg-purple-700 text-white font-bold'
                            : 'bg-white text-purple-800 hover:bg-purple-50 border border-purple-200'
                    ]"
                >
                    <span>Otorisasi Lurah</span>
                    <span v-if="countLurah > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-bold">
                        {{ countLurah }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'MENUNGGU_VERIFIKASI'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'MENUNGGU_VERIFIKASI'
                            ? 'bg-blue-600 text-white font-bold'
                            : 'bg-white text-blue-700 hover:bg-blue-50 border border-blue-200'
                    ]"
                >
                    <span>Verifikasi Teknis</span>
                    <span v-if="countVerifikasi > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-raised text-ink-secondary font-bold">
                        {{ countVerifikasi }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'REVISI_MINOR'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'REVISI_MINOR'
                            ? 'bg-rose-600 text-white font-bold'
                            : 'bg-white text-rose-700 hover:bg-rose-50 border border-rose-200'
                    ]"
                >
                    <span>Revisi / SLA</span>
                    <span v-if="countRevisi > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-red-50 text-semantic-danger font-bold">
                        {{ countRevisi }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'LOLOS_VERIFIKASI'"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5',
                        activeTab === 'LOLOS_VERIFIKASI'
                            ? 'bg-emerald-600 text-white font-bold'
                            : 'bg-white text-emerald-700 hover:bg-emerald-50 border border-emerald-200'
                    ]"
                >
                    <span>Lolos Verifikasi</span>
                    <span v-if="countLolos > 0" class="px-1.5 py-0.5 rounded-md text-xs bg-emerald-50 text-semantic-success font-bold">
                        {{ countLolos }}
                    </span>
                </button>
            </div>

            <GovCard :no-padding="true">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gov-surface-border text-left">
                        <thead class="bg-gov-surface-subtle text-xs  font-semibold text-slate-600">
                            <tr>
                                <th class="py-3 px-4">No. Bukti / Tanggal</th>
                                <th class="py-3 px-4">Unit Kerja & Subkegiatan</th>
                                <th class="py-3 px-4">Uraian / Penyedia</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Aksi Pemeriksaan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gov-surface-border text-xs">
                            <tr v-if="loading">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    Memuat antrean berkas...
                                </td>
                            </tr>
                            <tr v-else-if="filteredQueue.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    Tidak ada dokumen dalam kategori antrean ini.
                                </td>
                            </tr>
                            <tr v-for="item in filteredQueue" :key="item.id" class="hover:bg-slate-50">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gov-navy-900">{{ item.id }}</div>
                                    <div class="text-xs text-slate-400">{{ formatTanggalIndo(item.tanggal) }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800">{{ item.unit }}</div>
                                    <div class="text-xs text-slate-500 max-w-xs truncate">{{ item.sub_activity }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-800 max-w-xs truncate font-medium">{{ item.uraian }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ item.vendor }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-gov-navy-900 border border-slate-200">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gov-navy-950 tabular-nums">
                                    {{ formatRupiah(item.nominal) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <GovBadge :variant="STATUS_VARIANTS[item.status]">
                                            {{ STATUS_LABELS[item.status] }}
                                        </GovBadge>

                                        <!-- Indikator SLA 2 Hari Kerja jika status REVISI_MINOR -->
                                        <template v-if="item.status === 'REVISI_MINOR'">
                                            <span
                                                v-if="item.is_sla_breached"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 "
                                                title="SLA Terlampaui (Lebih dari 2 hari kerja)"
                                            >
                                                <svg class="w-3 h-3 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                </svg>
                                                SLA Terlewat
                                            </span>
                                            <span
                                                v-else-if="item.sla_remaining_hours !== null && item.sla_remaining_hours !== undefined"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                                            >
                                                <svg class="w-3 h-3 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                </svg>
                                                Sisa {{ Math.max(0, Math.round(item.sla_remaining_hours)) }} jam
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <!-- Aksi Spesifik PPTK jika status MENUNGGU_PPTK -->
                                    <div v-if="item.status === 'MENUNGGU_PPTK'" class="flex items-center justify-center gap-1.5">
                                        <button
                                            v-if="userRole === 'PPTK'"
                                            @click="openAuthModal(item, 'PPTK', 'APPROVE')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm"
                                            title="Setujui dan teruskan ke tahapan berikutnya"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Setujui
                                        </button>
                                        <button
                                            v-if="userRole === 'PPTK'"
                                            @click="openAuthModal(item, 'PPTK', 'REJECT')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-600 text-white hover:bg-rose-700 shadow-sm"
                                            title="Tolak dan pulihkan pagu kas"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Tolak
                                        </button>
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200"
                                            title="Periksa kelengkapan berkas"
                                        >
                                            Review
                                        </Link>
                                    </div>

                                    <!-- Aksi Spesifik LURAH jika status MENUNGGU_LURAH (historis) -->
                                    <div v-else-if="item.status === 'MENUNGGU_LURAH'" class="flex items-center justify-center gap-1.5">
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200"
                                            title="Periksa kelengkapan berkas"
                                        >
                                            Review
                                        </Link>
                                    </div>

                                    <!-- Aksi Verifikator jika status sudah lewat persetujuan -->
                                    <div v-else class="flex items-center justify-center gap-1.5">
                                        <Link
                                            :href="route('verification.workspace', { id: item.id })"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gov-navy-900 text-white hover:bg-gov-navy-800 shadow-sm"
                                        >
                                            <svg class="w-3.5 h-3.5 text-gov-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Buka Workspace
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </GovCard>
        </div>

        <!-- Modal Otorisasi Bersama (PPTK & Lurah) -->
        <div
            v-if="showAuthModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden">
                <div
                    :class="[
                        'px-6 py-4 text-white font-bold flex items-center justify-between',
                        authActionType === 'APPROVE' ? (authActionRole === 'LURAH' ? 'bg-purple-800' : 'bg-emerald-700') : 'bg-rose-700'
                    ]"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <svg v-if="authActionType === 'APPROVE'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ authActionRole === 'LURAH' ? (authActionType === 'APPROVE' ? 'Otorisasi KPA Kelurahan (Lurah)' : 'Penolakan / Pengembalian oleh Lurah') : (authActionType === 'APPROVE' ? 'Persetujuan PPTK' : 'Penolakan oleh PPTK') }}
                    </span>
                    <button @click="showAuthModal = false" class="text-white/80 hover:text-white text-lg">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Nomor Bukti:</span>
                            <span class="font-bold text-slate-800">{{ selectedItem?.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Nominal Belanja:</span>
                            <span class="font-bold text-gov-navy-950">{{ formatRupiah(selectedItem?.nominal || 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Uraian:</span>
                            <span class="text-slate-700 max-w-xs truncate">{{ selectedItem?.uraian }}</span>
                        </div>
                    </div>

                    <div v-if="authActionType === 'APPROVE'">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Catatan Persetujuan (Opsional):
                        </label>
                        <textarea
                            v-model="authNotes"
                            rows="3"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-gov-navy-800 focus:ring-gov-navy-800"
                            :placeholder="authActionRole === 'LURAH' ? 'Contoh: Disetujui dan diotorisasi untuk diproses verifikasi Kecamatan.' : 'Contoh: Sesuai rencana kegiatan seksi, diteruskan.'"
                        ></textarea>
                    </div>

                    <div v-else>
                        <label class="block text-xs font-semibold text-rose-800 mb-1">
                            Alasan Penolakan / Pengembalian (Wajib):
                        </label>
                        <textarea
                            v-model="authNotes"
                            rows="3"
                            class="w-full text-xs rounded-lg border-rose-300 focus:border-rose-500 focus:ring-rose-500 placeholder-rose-300"
                            placeholder="Contoh: Bukti fisik belum lengkap, mohon perbaiki."
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <GovButton
                            variant="secondary"
                            size="sm"
                            @click="showAuthModal = false"
                        >
                            Batal
                        </GovButton>
                        <GovButton
                            :variant="authActionType === 'APPROVE' ? 'primary' : 'danger'"
                            size="sm"
                            :disabled="isSubmittingAuth"
                            @click="handleAuthSubmit"
                        >
                            {{ isSubmittingAuth ? 'Memproses...' : (authActionType === 'APPROVE' ? 'Konfirmasi Setujui' : 'Konfirmasi Tolak') }}
                        </GovButton>
                    </div>
                </div>
            </div>
        </div>
    </GovAppLayout>
</template>
