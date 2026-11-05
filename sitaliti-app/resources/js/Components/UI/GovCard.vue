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
    <div class="bg-white rounded-xl border border-gov-surface-border shadow-sm overflow-hidden">
        <div v-if="title || $slots.header || $slots.actions" class="px-5 py-4 border-b border-gov-surface-border flex items-center justify-between">
            <div>
                <slot name="header">
                    <h3 class="text-base font-semibold text-gov-navy-900">{{ title }}</h3>
                    <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div :class="[noPadding ? '' : 'p-5']">
            <slot />
        </div>

        <div v-if="$slots.footer" class="px-5 py-3.5 bg-gov-surface-subtle border-t border-gov-surface-border">
            <slot name="footer" />
        </div>
    </div>
</template>

