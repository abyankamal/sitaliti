<script setup>
import { ref, watch, onMounted } from 'vue';
import QRCode from 'qrcode';

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

const qrDataUrl = ref('');

async function generateQr() {
    if (!props.value) return;
    try {
        qrDataUrl.value = await QRCode.toDataURL(props.value, {
            width: props.size,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff'
            }
        });
    } catch (err) {
        console.error('Error generating offline QR code:', err);
        // Fallback jika terjadi error
        qrDataUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=${props.size}x${props.size}&data=${encodeURIComponent(props.value)}&margin=4`;
    }
}

onMounted(() => {
    generateQr();
});

watch(() => props.value, () => {
    generateQr();
});
</script>

<template>
    <div class="inline-flex flex-col items-center">
        <div class="p-2 bg-surface rounded-lg border border-hairline shadow-subtle inline-block">
            <img
                v-if="qrDataUrl"
                :src="qrDataUrl"
                :alt="value"
                :width="size"
                :height="size"
                class="block"
            />
            <div
                v-else
                :style="{ width: `${size}px`, height: `${size}px` }"
                class="bg-raised flex items-center justify-center text-xs text-ink-muted"
            >
                Memuat QR...
            </div>
        </div>
        <span v-if="label" class="text-xs text-ink-muted font-mono mt-1 text-center truncate max-w-[140px]">
            {{ label }}
        </span>
    </div>
</template>
