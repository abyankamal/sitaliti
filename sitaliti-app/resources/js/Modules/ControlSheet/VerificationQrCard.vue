<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
    size: {
        type: Number,
        default: 120,
    },
    label: {
        type: String,
        default: null,
    },
});

// Menggunakan API rendering QR Code standar berkecepatan tinggi tanpa dependensi eksternal berat
const qrUrl = computed(() => {
    return `https://api.qrserver.com/v1/create-qr-code/?size=${props.size}x${props.size}&data=${encodeURIComponent(props.value)}&margin=4`;
});
</script>

<template>
    <div class="inline-flex flex-col items-center">
        <div class="p-2 bg-white rounded-lg border border-slate-300 shadow-xs inline-block">
            <img
                :src="qrUrl"
                :alt="value"
                :width="size"
                :height="size"
                class="block"
                loading="lazy"
            />
        </div>
        <span v-if="label" class="text-[10px] text-slate-500 font-mono mt-1 text-center truncate max-w-[140px]">
            {{ label }}
        </span>
    </div>
</template>

