<script setup>
import { ref, computed } from 'vue';
import { FileText, ZoomOut, ZoomIn, RotateCw, FilePlus2 } from 'lucide-vue-next';

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
    <div class="h-full flex flex-col bg-surface text-ink rounded-xl overflow-hidden border border-hairline shadow-subtle">
        <!-- Viewer Control Bar -->
        <div class="bg-surface px-4 py-2.5 border-b border-hairline flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 overflow-hidden mr-2">
                <span class="p-1 rounded bg-accent-soft text-accent">
                    <FileText class="w-4 h-4" :stroke-width="1.5" />
                </span>
                <span class="text-xs font-medium text-ink truncate">{{ fileName }}</span>
                <span v-if="isImage" class="text-xs px-1.5 py-0.5 rounded bg-emerald-50 text-semantic-success border border-emerald-200 font-mono">
                    IMAGE
                </span>
                <span v-else-if="fileUrl" class="text-xs px-1.5 py-0.5 rounded bg-accent-soft text-accent border border-accent-border font-mono">
                    PDF
                </span>
            </div>

            <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                <button
                    type="button"
                    @click="zoomOut"
                    class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 p-1.5 inline-flex items-center justify-center rounded hover:bg-raised text-ink-secondary hover:text-ink focus-visible:ring-2 focus-visible:ring-accent"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-4 h-4" :stroke-width="1.5" />
                </button>
                <span class="text-xs font-mono text-ink-muted w-10 text-center tabular-nums">{{ zoomLevel }}%</span>
                <button
                    type="button"
                    @click="zoomIn"
                    class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 p-1.5 inline-flex items-center justify-center rounded hover:bg-raised text-ink-secondary hover:text-ink focus-visible:ring-2 focus-visible:ring-accent"
                    title="Zoom In"
                >
                    <ZoomIn class="w-4 h-4" :stroke-width="1.5" />
                </button>
                <div class="h-4 w-px bg-hairline mx-1"></div>
                <button
                    type="button"
                    @click="rotateDoc"
                    class="min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 p-1.5 inline-flex items-center justify-center rounded hover:bg-raised text-ink-secondary hover:text-ink focus-visible:ring-2 focus-visible:ring-accent"
                    title="Putar Dokumen 90 Derajat"
                >
                    <RotateCw class="w-4 h-4" :stroke-width="1.5" />
                </button>
                <button
                    type="button"
                    @click="resetView"
                    class="min-h-[44px] sm:min-h-0 px-2.5 py-1 text-xs rounded hover:bg-raised text-ink-muted hover:text-ink focus-visible:ring-2 focus-visible:ring-accent"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- Document Preview Canvas Area -->
        <div class="flex-1 min-h-0 overflow-auto scrollbar-thin p-4 bg-raised relative">
            <div
                v-if="fileUrl"
                class="min-w-full min-h-full flex items-start justify-start"
                :style="{
                    width: `${Math.max(100, zoomLevel)}%`,
                    height: rotation % 180 !== 0 ? `${Math.max(100, zoomLevel)}%` : '100%',
                }"
            >
                <div
                    class="transition-transform duration-150 ease-unslop shadow-subtle origin-top-left bg-surface rounded border border-hairline overflow-hidden h-full min-h-[420px] w-full flex flex-col"
                    :style="{
                        transform: `scale(${zoomLevel / 100}) rotate(${rotation}deg)`
                    }"
                >
                    <!-- Image Viewer untuk Foto Nota/Kuitansi Fisik -->
                    <img
                        v-if="isImage"
                        :src="fileUrl"
                        :alt="fileName"
                        class="max-w-full max-h-full object-contain m-auto p-2"
                    />

                    <!-- Native Iframe Viewer untuk Dokumen PDF -->
                    <iframe
                        v-else
                        :src="fileUrl"
                        class="w-full flex-1 min-h-[420px] border-0"
                        title="PDF Viewer"
                    ></iframe>
                </div>
            </div>

            <div v-else class="text-center text-ink-muted text-xs py-16">
                <FilePlus2 class="w-10 h-10 mx-auto text-ink-muted mb-2" :stroke-width="1.5" />
                <p>Pilih salah satu dokumen checklist di panel kanan untuk meneliti fisik berkas bukti.</p>
            </div>
        </div>
    </div>
</template>

