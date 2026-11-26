<script setup>
import { Loader2 } from 'lucide-vue-next';

defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'gold', 'danger', 'success', 'ghost'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center font-medium rounded-lg transition-[background-color,border-color,color,transform] duration-150 ease-unslop focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed select-none"
        :class="[
            size === 'sm' && 'h-8 px-3 text-xs gap-1.5',
            size === 'md' && 'h-10 px-4 text-sm gap-2',
            size === 'lg' && 'h-11 px-5 text-sm gap-2',

            // Variants
            (variant === 'primary' || variant === 'gold') && 'bg-accent text-white hover:bg-accent-hover focus-visible:ring-accent shadow-subtle',
            variant === 'secondary' && 'bg-surface text-ink border border-hairline hover:bg-raised focus-visible:ring-accent shadow-subtle',
            variant === 'danger' && 'bg-semantic-danger text-white hover:bg-red-800 focus-visible:ring-red-700 shadow-subtle',
            variant === 'success' && 'bg-semantic-success text-white hover:bg-emerald-800 focus-visible:ring-emerald-700 shadow-subtle',
            variant === 'ghost' && 'text-ink-secondary hover:bg-raised hover:text-ink focus-visible:ring-accent'
        ]"
    >
        <Loader2
            v-if="loading"
            class="w-4 h-4 animate-spin shrink-0"
            :stroke-width="1.5"
        />
        <slot />
    </button>
</template>

