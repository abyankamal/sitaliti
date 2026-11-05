<script setup>
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
        class="inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed select-none"
        :class="[
            size === 'sm' && 'px-3 py-1.5 text-xs gap-1.5',
            size === 'md' && 'px-4 py-2 text-sm gap-2',
            size === 'lg' && 'px-5 py-2.5 text-base gap-2.5',
            
            // Variants
            variant === 'primary' && 'bg-gov-navy-900 text-white hover:bg-gov-navy-800 focus:ring-gov-navy-700 shadow-sm active:bg-gov-navy-950',
            variant === 'secondary' && 'bg-white text-gov-navy-900 border border-gov-surface-border hover:bg-slate-50 focus:ring-gov-navy-500 shadow-sm',
            variant === 'gold' && 'bg-gov-gold-600 text-white hover:bg-gov-gold-700 focus:ring-gov-gold-500 shadow-sm font-semibold',
            variant === 'danger' && 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 shadow-sm',
            variant === 'success' && 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 shadow-sm',
            variant === 'ghost' && 'text-gov-navy-700 hover:bg-gov-navy-50 hover:text-gov-navy-900 focus:ring-gov-navy-300'
        ]"
    >
        <svg
            v-if="loading"
            class="animate-spin -ml-0.5 h-4 w-4 text-current"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <slot />
    </button>
</template>

