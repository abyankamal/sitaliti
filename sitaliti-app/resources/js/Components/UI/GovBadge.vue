<script setup>
defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'primary', 'success', 'warning', 'danger', 'info'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});
</script>

<template>
    <span
        class="inline-flex items-center font-medium rounded-full"
        :class="[
            size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs',
            variant === 'neutral' && 'bg-slate-100 text-slate-700 border border-slate-200',
            variant === 'primary' && 'bg-gov-navy-100 text-gov-navy-900 border border-gov-navy-200',
            variant === 'success' && 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            variant === 'warning' && 'bg-amber-50 text-amber-700 border border-amber-200',
            variant === 'danger' && 'bg-rose-50 text-rose-700 border border-rose-200',
            variant === 'info' && 'bg-sky-50 text-sky-700 border border-sky-200',
        ]"
    >
        <slot />
    </span>
</template>

