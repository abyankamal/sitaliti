<script setup>
defineProps({
    variant: {
        type: String,
        default: 'text',
        validator: (value) => ['card', 'table-row', 'text'].includes(value),
    },
    rows: {
        type: Number,
        default: 1,
    },
    columns: {
        type: Number,
        default: 4,
    },
});
</script>

<template>
    <div v-if="variant === 'card'" class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle space-y-3" aria-hidden="true">
        <div class="h-3 w-28 rounded gov-skeleton-bar"></div>
        <div class="h-6 w-40 rounded gov-skeleton-bar"></div>
        <div class="h-3 w-36 rounded gov-skeleton-bar"></div>
    </div>

    <div v-else-if="variant === 'table-row'" class="divide-y divide-hairline" aria-hidden="true">
        <div
            v-for="r in rows"
            :key="r"
            class="px-5 py-3.5 grid gap-4 items-center"
            :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
        >
            <div
                v-for="c in columns"
                :key="c"
                class="h-3.5 rounded gov-skeleton-bar"
                :class="c === 1 ? 'w-3/4' : 'w-5/6'"
            ></div>
        </div>
    </div>

    <div v-else class="space-y-2.5" aria-hidden="true">
        <div
            v-for="r in rows"
            :key="r"
            class="h-3.5 rounded gov-skeleton-bar"
            :class="r === rows && rows > 1 ? 'w-2/3' : 'w-full'"
        ></div>
    </div>
</template>
