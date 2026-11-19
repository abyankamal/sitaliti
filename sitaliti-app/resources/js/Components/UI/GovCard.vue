<script setup>
defineProps({
    title: {
        type: String,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="bg-surface rounded-xl border border-hairline shadow-subtle overflow-hidden">
        <div v-if="title || $slots.header || $slots.actions" class="px-5 py-4 border-b border-hairline flex items-center justify-between gap-4">
            <div>
                <slot name="header">
                    <h3 class="text-base font-medium text-ink">{{ title }}</h3>
                    <p v-if="subtitle" class="text-xs text-ink-muted mt-0.5">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div :class="[noPadding ? '' : 'p-5']">
            <slot />
        </div>

        <div v-if="$slots.footer" class="px-5 py-3.5 bg-canvas border-t border-hairline">
            <slot name="footer" />
        </div>
    </div>
</template>

