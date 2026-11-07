<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    fileUrl: {
        type: String,
        default: null,
    },
    fileName: {
        type: String,
        default: 'Dokumen Lampiran',
    },
});

const zoomLevel = ref(100);
const rotation = ref(0);

const isImage = computed(() => {
    if (!props.fileUrl && !props.fileName) return false;
    const target = (props.fileName || props.fileUrl).toLowerCase();
    return target.endsWith('.jpg') || target.endsWith('.jpeg') || target.endsWith('.png') || target.endsWith('.webp') || target.includes('image/');
});

function zoomIn() {
    if (zoomLevel.value < 200) zoomLevel.value += 25;
}

function zoomOut() {
    if (zoomLevel.value > 50) zoomLevel.value -= 25;
}

function rotateDoc() {
    rotation.value = (rotation.value + 90) % 360;
}

function resetView() {
    zoomLevel.value = 100;
    rotation.value = 0;
}
</script>

<template>
    <div class="h-full flex flex-col bg-slate-900 text-white rounded-xl overflow-hidden border border-slate-700 shadow-md">
        <!-- Viewer Control Bar -->
        <div class="bg-gov-navy-950 px-4 py-2.5 border-b border-gov-navy-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 overflow-hidden mr-2">
                <span class="p-1 rounded bg-gov-navy-800 text-gov-gold-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <span class="text-xs font-medium text-slate-200 truncate">{{ fileName }}</span>
                <span v-if="isImage" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-900/60 text-emerald-300 font-mono">
                    IMAGE
                </span>
                <span v-else-if="fileUrl" class="text-[10px] px-1.5 py-0.5 rounded bg-sky-900/60 text-sky-300 font-mono">
                    PDF
                </span>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <button
                    type="button"
                    @click="zoomOut"
                    class="p-1.5 rounded hover:bg-gov-navy-800 text-slate-300 hover:text-white"
                    title="Zoom Out"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </button>
                <span class="text-[11px] font-mono text-slate-400 w-10 text-center">{{ zoomLevel }}%</span>
                <button
                    type="button"
                    @click="zoomIn"
                    class="p-1.5 rounded hover:bg-gov-navy-800 text-slate-300 hover:text-white"
                    title="Zoom In"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
                <div class="h-4 w-px bg-slate-700 mx-1"></div>
                <button
                    type="button"
                    @click="rotateDoc"
                    class="p-1.5 rounded hover:bg-gov-navy-800 text-slate-300 hover:text-white"
                    title="Putar Dokumen 90 Derajat"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="resetView"
                    class="px-2 py-1 text-[10px] rounded hover:bg-gov-navy-800 text-slate-400 hover:text-white"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- Document Preview Canvas Area -->
        <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-slate-900/90 relative">
            <div
                v-if="fileUrl"
                class="transition-all duration-200 shadow-2xl origin-center bg-white rounded flex items-center justify-center overflow-hidden min-h-[500px] w-full"
                :style="{
                    transform: `scale(${zoomLevel / 100}) rotate(${rotation}deg)`
                }"
            >
                <!-- Image Viewer untuk Foto Nota/Kuitansi Fisik -->
                <img
                    v-if="isImage"
                    :src="fileUrl"
                    :alt="fileName"
                    class="max-w-full max-h-[650px] object-contain p-2"
                />

                <!-- Native Iframe Viewer untuk Dokumen PDF -->
                <iframe
                    v-else
                    :src="fileUrl"
                    class="w-full h-[650px] border-0"
                    title="PDF Viewer"
                ></iframe>
            </div>

            <div v-else class="text-center text-slate-500 text-xs py-16">
                <svg class="w-12 h-12 mx-auto text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>Pilih salah satu dokumen checklist di panel kanan untuk meneliti fisik berkas bukti.</p>
            </div>
        </div>
    </div>
</template>

