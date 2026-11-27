<script setup>
import { ref, watch, nextTick } from 'vue';
import { AlertTriangle, X } from 'lucide-vue-next';
import GovButton from '@/Components/UI/GovButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    receiptId: {
        type: [String, Number],
        required: true,
    },
    actionType: {
        type: String,
        default: 'REVISION_MINOR', // 'REVISION_MINOR' atau 'REVISION_MAJOR'
    },
});

const emit = defineEmits(['close', 'submit']);

const dialogRef = ref(null);
const pointerStartedOnBackdrop = ref(false);
const notes = ref('');
const errorMessage = ref('');

watch(
    () => props.show,
    async (open) => {
        await nextTick();
        const el = dialogRef.value;
        if (!el) return;
        if (open) {
            if (!el.open) el.showModal();
        } else if (el.open) {
            el.close();
        }
    }
);

function handleNativeCancel(event) {
    event.preventDefault();
    emit('close');
}

function handlePointerDown(event) {
    pointerStartedOnBackdrop.value = event.target === dialogRef.value;
}

function handleBackdropClick(event) {
    if (pointerStartedOnBackdrop.value && event.target === dialogRef.value) {
        emit('close');
    }
    pointerStartedOnBackdrop.value = false;
}

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
    <dialog
        ref="dialogRef"
        aria-labelledby="revision-drawer-title"
        class="w-[calc(100vw-2rem)] max-w-lg rounded-xl bg-surface text-ink border border-hairline p-0 shadow-floating backdrop:bg-ink/40"
        @cancel="handleNativeCancel"
        @pointerdown="handlePointerDown"
        @click="handleBackdropClick"
    >
        <div class="overflow-hidden">
            <!-- Header -->
            <div
                class="px-5 py-4 border-b border-hairline text-white flex items-center justify-between"
                :class="[actionType === 'REVISION_MAJOR' ? 'bg-semantic-danger' : 'bg-semantic-warning']"
            >
                <div class="flex items-center gap-2.5">
                    <AlertTriangle class="w-5 h-5 text-white" :stroke-width="1.5" />
                    <h3 id="revision-drawer-title" class="text-sm font-semibold text-white">
                        {{ actionType === 'REVISION_MAJOR' ? 'Tolak Berkas (Revisi Mayor)' : 'Pengembalian Berkas (Revisi Minor)' }}
                    </h3>
                </div>
                <button
                    type="button"
                    @click="$emit('close')"
                    class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 inline-flex items-center justify-center text-white/85 hover:text-white p-1 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    aria-label="Tutup"
                >
                    <X class="w-4 h-4" :stroke-width="1.5" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-3.5">
                <div class="text-xs text-ink-secondary leading-relaxed">
                    <span v-if="actionType === 'REVISION_MAJOR'">
                        <strong>Revisi Mayor:</strong> Berkas ditolak karena kesalahan mendasar (misal: kesalahan kodifikasi rekening belanja, ketidaksesuaian RAK, atau penyalahgunaan anggaran). Berkas kuitansi dibatalkan.
                    </span>
                    <span v-else>
                        <strong>Revisi Minor:</strong> Berkas dikembalikan ke operator SPJ untuk melengkapi tanda tangan, stempel, kelengkapan absen/notula tanpa membatalkan transaksi kuitansi.
                    </span>
                </div>

                <div>
                    <label for="revision-notes" class="block text-xs font-semibold text-ink mb-1.5">
                        Uraian Temuan / Instruksi Perbaikan <span class="text-semantic-danger">*</span>
                    </label>
                    <textarea
                        id="revision-notes"
                        v-model="notes"
                        rows="4"
                        placeholder="Tuliskan secara jelas dokumen yang perlu diperbaiki atau dasar penolakan..."
                        class="w-full text-xs rounded-lg border-hairline bg-surface text-ink placeholder:text-ink-muted p-3 focus:border-accent focus:ring-1 focus:ring-accent"
                    ></textarea>
                    <p v-if="errorMessage" class="mt-1.5 text-xs font-semibold text-semantic-danger">
                        {{ errorMessage }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3.5 bg-raised border-t border-hairline flex items-center justify-end gap-2.5">
                <GovButton variant="secondary" size="sm" @click="$emit('close')">
                    Batal
                </GovButton>
                <GovButton
                    :variant="actionType === 'REVISION_MAJOR' ? 'danger' : 'primary'"
                    size="sm"
                    @click="handleConfirm"
                >
                    {{ actionType === 'REVISION_MAJOR' ? 'Konfirmasi Tolak Mayor' : 'Kirim Catatan Revisi Minor' }}
                </GovButton>
            </div>
        </div>
    </dialog>
</template>

