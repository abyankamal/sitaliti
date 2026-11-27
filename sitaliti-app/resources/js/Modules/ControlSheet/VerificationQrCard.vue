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
const qrError = ref(false);

async function generateQr() {
    if (!props.value) return;
    qrError.value = false;
    try {
        qrDataUrl.value = await QRCode.toDataURL(props.value, {
            width: props.size,
            margin: 1,
            color: {
                dark: '#1C1917',
                light: '#ffffff',
            },
        });
    } catch (err) {
        console.error('Error generating offline QR code:', err);
        qrDataUrl.value = '';
        qrError.value = true;
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
                v-else-if="qrError"
                :style="{ width: `${size}px`, height: `${size}px` }"
                class="bg-red-50 border border-red-200 flex flex-col items-center justify-center p-2 text-center text-xs text-red-900"
            >
                <span>QR gagal dimuat</span>
                <button type="button" class="underline font-semibold mt-1" @click="generateQr">
                    Coba lagi
                </button>
            </div>
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
