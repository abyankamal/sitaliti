<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next';
import { useToast } from '@/Composables/useToast';

const { toasts, dismiss, pause, resume } = useToast();
const containerRef = ref(null);

const TYPE_STYLES = {
    success: {
        container: 'bg-emerald-50 border-emerald-200 text-emerald-900',
        icon: 'text-emerald-600',
        Component: CheckCircle2,
    },
    error: {
        container: 'bg-rose-50 border-rose-200 text-rose-900',
        icon: 'text-rose-600',
        Component: AlertCircle,
    },
    warning: {
        container: 'bg-amber-50 border-amber-200 text-amber-900',
        icon: 'text-amber-600',
        Component: AlertTriangle,
    },
    info: {
        container: 'bg-surface border-hairline text-ink',
        icon: 'text-accent',
        Component: Info,
    },
};

function syncPopover(hasToasts) {
    const el = containerRef.value;
    if (!el || typeof el.showPopover !== 'function') return;

    try {
        const isOpen = el.matches(':popover-open');
        if (hasToasts && !isOpen) {
            el.showPopover();
        } else if (!hasToasts && isOpen) {
            el.hidePopover();
        }
    } catch {
        // Abaikan jika browser/state transisi menolak toggle popover
    }
}

watch(
    () => toasts.value.length > 0,
    async (hasToasts) => {
        await nextTick();
        syncPopover(hasToasts);
    }
);

onMounted(() => {
    if (toasts.value.length > 0) {
        syncPopover(true);
    }
});
</script>

<template>
    <div
        ref="containerRef"
        popover="manual"
        class="m-0 bg-transparent border-0 p-0 inset-auto fixed bottom-5 right-5 z-[100] w-[calc(100vw-2.5rem)] max-w-sm pointer-events-none overflow-visible"
    >
        <TransitionGroup
            name="gov-toast"
            tag="div"
            class="flex flex-col gap-2.5"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                :role="item.type === 'error' ? 'alert' : 'status'"
                :aria-live="item.type === 'error' ? 'assertive' : 'polite'"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border px-3.5 py-3 shadow-lg transition-all duration-200"
                :class="(TYPE_STYLES[item.type] || TYPE_STYLES.info).container"
                @mouseenter="pause(item.id)"
                @mouseleave="resume(item.id)"
                @focusin="pause(item.id)"
                @focusout="resume(item.id)"
            >
                <component
                    :is="(TYPE_STYLES[item.type] || TYPE_STYLES.info).Component"
                    class="w-4 h-4 mt-0.5 shrink-0"
                    :class="(TYPE_STYLES[item.type] || TYPE_STYLES.info).icon"
                    :stroke-width="2"
                />

                <div class="flex-1 min-w-0 text-xs leading-relaxed">
                    <p v-if="item.title" class="font-semibold">
                        {{ item.title }}
                    </p>
                    <p v-if="item.message" :class="item.title ? 'mt-0.5 opacity-90' : 'font-medium'">
                        {{ item.message }}
                    </p>
                </div>

                <button
                    type="button"
                    class="p-0.5 rounded-md opacity-60 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-accent shrink-0"
                    aria-label="Tutup notifikasi"
                    @click="dismiss(item.id)"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.gov-toast-enter-active,
.gov-toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.gov-toast-enter-from,
.gov-toast-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
</style>
