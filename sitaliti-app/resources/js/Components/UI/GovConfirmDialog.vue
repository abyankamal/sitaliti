<script setup>
import { ref, watch, nextTick } from 'vue';
import { AlertTriangle, ShieldAlert, HelpCircle } from 'lucide-vue-next';
import GovButton from '@/Components/UI/GovButton.vue';
import { useConfirm } from '@/Composables/useConfirm';

const { confirmState, submit, cancel } = useConfirm();
const dialogRef = ref(null);
const pointerStartedOnBackdrop = ref(false);

const VARIANT_CONFIG = {
    danger: {
        iconBg: 'bg-red-50 text-semantic-danger border-red-200',
        buttonVariant: 'danger',
        Icon: ShieldAlert,
    },
    warning: {
        iconBg: 'bg-amber-50 text-semantic-warning border-amber-200',
        buttonVariant: 'primary',
        Icon: AlertTriangle,
    },
    primary: {
        iconBg: 'bg-accent-soft text-accent border-accent-border',
        buttonVariant: 'primary',
        Icon: HelpCircle,
    },
};

watch(
    () => confirmState.isOpen,
    async (open) => {
        await nextTick();
        const el = dialogRef.value;
        if (!el) return;

        if (open) {
            if (!el.open) {
                el.showModal();
            }
        } else if (el.open) {
            el.close();
        }
    }
);

function handleNativeCancel(event) {
    event.preventDefault();
    cancel();
}

function handlePointerDown(event) {
    pointerStartedOnBackdrop.value = event.target === dialogRef.value;
}

function handleBackdropClick(event) {
    if (pointerStartedOnBackdrop.value && event.target === dialogRef.value) {
        cancel();
    }
    pointerStartedOnBackdrop.value = false;
}
</script>

<template>
    <dialog
        ref="dialogRef"
        aria-labelledby="gov-confirm-title"
        class="gov-confirm-dialog w-[calc(100vw-2rem)] max-w-md rounded-xl bg-surface text-ink border border-hairline p-0 shadow-floating backdrop:bg-ink/40"
        @cancel="handleNativeCancel"
        @pointerdown="handlePointerDown"
        @click="handleBackdropClick"
    >
        <form class="p-5 space-y-4" @submit.prevent="submit">
            <div class="flex items-start gap-3">
                <div
                    class="w-9 h-9 rounded-lg border flex items-center justify-center shrink-0"
                    :class="(VARIANT_CONFIG[confirmState.variant] || VARIANT_CONFIG.primary).iconBg"
                >
                    <component
                        :is="(VARIANT_CONFIG[confirmState.variant] || VARIANT_CONFIG.primary).Icon"
                        class="w-4 h-4"
                        :stroke-width="1.8"
                    />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="gov-confirm-title" class="text-sm font-semibold text-ink">
                        {{ confirmState.title }}
                    </h2>
                    <p v-if="confirmState.message" class="mt-1 text-xs text-ink-secondary leading-relaxed">
                        {{ confirmState.message }}
                    </p>
                </div>
            </div>

            <div
                v-if="confirmState.details"
                class="rounded-lg bg-raised border border-hairline px-3 py-2.5 text-xs text-ink-secondary font-mono"
            >
                {{ confirmState.details }}
            </div>

            <div v-if="confirmState.requireReason" class="space-y-1.5">
                <label for="gov-confirm-reason" class="block text-xs font-medium text-ink">
                    {{ confirmState.reasonLabel }} <span class="text-semantic-danger">*</span>
                </label>
                <textarea
                    id="gov-confirm-reason"
                    v-model="confirmState.reason"
                    rows="3"
                    :disabled="confirmState.loading"
                    :placeholder="confirmState.reasonPlaceholder"
                    class="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-xs text-ink placeholder:text-ink-muted focus:border-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/20 disabled:opacity-60"
                />
            </div>

            <div
                v-if="confirmState.error"
                role="alert"
                class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-900"
            >
                {{ confirmState.error }}
            </div>

            <div class="pt-1 flex items-center justify-end gap-2">
                <GovButton
                    type="button"
                    variant="secondary"
                    size="sm"
                    :disabled="confirmState.loading"
                    autofocus
                    @click="cancel"
                >
                    {{ confirmState.cancelText }}
                </GovButton>
                <GovButton
                    type="submit"
                    :variant="(VARIANT_CONFIG[confirmState.variant] || VARIANT_CONFIG.primary).buttonVariant"
                    size="sm"
                    :loading="confirmState.loading"
                >
                    {{ confirmState.confirmText }}
                </GovButton>
            </div>
        </form>
    </dialog>
</template>

<style scoped>
.gov-confirm-dialog:modal {
    overscroll-behavior: contain;
}
</style>
