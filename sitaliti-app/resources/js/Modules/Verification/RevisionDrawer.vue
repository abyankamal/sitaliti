<script setup>
import { ref } from 'vue';
import GovButton from '@/Components/UI/GovButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    receiptId: {
        type: String,
        required: true,
    },
    actionType: {
        type: String,
        default: 'REVISION_MINOR', // 'REVISION_MINOR' atau 'REVISION_MAJOR'
    },
});

const emit = defineEmits(['close', 'submit']);

const notes = ref('');
const errorMessage = ref('');
const isSubmitting = ref(false);

function handleConfirm() {
    errorMessage.value = '';
    if (!notes.value.trim()) {
        errorMessage.value = 'Mohon isi uraian catatan pengembalian berkas.';
        return;
    }

    emit('submit', {
        action: props.actionType,
        notes: notes.value,
    });
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gov-navy-950/70 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl border border-gov-surface-border overflow-hidden">
                <!-- Header -->
                <div
                    class="px-5 py-4 border-b text-white flex items-center justify-between"
                    :class="[actionType === 'REVISION_MAJOR' ? 'bg-rose-700' : 'bg-amber-600']"
                >
                    <div class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <h3 class="text-sm font-bold text-white">
                            {{ actionType === 'REVISION_MAJOR' ? 'Tolak Berkas (Revisi Mayor)' : 'Pengembalian Berkas (Revisi Minor)' }}
                        </h3>
                    </div>
                    <button @click="$emit('close')" class="text-white/80 hover:text-white">✕</button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-3.5">
                    <div class="text-xs text-slate-600 leading-relaxed">
                        <span v-if="actionType === 'REVISION_MAJOR'">
                            <strong>Revisi Mayor:</strong> Berkas ditolak karena kesalahan mendasar (misal: kesalahan kodifikasi rekening belanja, ketidaksesuaian RAK, atau penyalahgunaan anggaran). Berkas kuitansi dibatalkan.
                        </span>
                        <span v-else>
                            <strong>Revisi Minor:</strong> Berkas dikembalikan ke operator SPJ untuk melengkapi tanda tangan, stempel, kelengkapan absen/notula tanpa membatalkan transaksi kuitansi.
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gov-navy-900 uppercase tracking-wider mb-1">
                            Uraian Temuan / Instruksi Perbaikan <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="notes"
                            rows="4"
                            placeholder="Tuliskan secara jelas dokumen yang perlu diperbaiki atau dasar penolakan..."
                            class="w-full text-xs rounded-lg border-gov-surface-border p-3 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800"
                        ></textarea>
                        <p v-if="errorMessage" class="mt-1.5 text-xs font-semibold text-rose-600">
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                    <GovButton variant="secondary" size="sm" @click="$emit('close')">
                        Batal
                    </GovButton>
                    <GovButton
                        :variant="actionType === 'REVISION_MAJOR' ? 'danger' : 'gold'"
                        size="sm"
                        @click="handleConfirm"
                    >
                        {{ actionType === 'REVISION_MAJOR' ? 'Konfirmasi Tolak Mayor' : 'Kirim Catatan Revisi Minor' }}
                    </GovButton>
                </div>
            </div>
        </div>
    </div>
</template>

