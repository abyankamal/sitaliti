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
        class="inline-flex items-center gap-1.5 font-medium rounded-md border text-xs whitespace-nowrap"
        :class="[
            size === 'sm' ? 'px-2 py-0.5' : 'px-2.5 py-1',
            variant === 'neutral' && 'bg-raised text-ink-secondary border-hairline',
            variant === 'primary' && 'bg-accent-soft text-accent border-accent-border',
            variant === 'success' && 'bg-emerald-50/70 text-semantic-success border-emerald-200',
            variant === 'warning' && 'bg-amber-50/70 text-semantic-warning border-amber-200',
            variant === 'danger' && 'bg-red-50/70 text-semantic-danger border-red-200',
            variant === 'info' && 'bg-sky-50/70 text-semantic-info border-sky-200',
        ]"
    >
        <span
            class="w-1.5 h-1.5 rounded-[50%] shrink-0"
            :class="[
                variant === 'neutral' && 'bg-ink-muted',
                variant === 'primary' && 'bg-accent',
                variant === 'success' && 'bg-semantic-success',
                variant === 'warning' && 'bg-semantic-warning',
                variant === 'danger' && 'bg-semantic-danger',
                variant === 'info' && 'bg-semantic-info',
            ]"
        ></span>
        <slot />
    </span>
</template>

