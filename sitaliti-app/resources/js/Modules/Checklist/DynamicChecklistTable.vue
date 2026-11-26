<script setup>
import { ref } from 'vue';
import { Check, FileText, Upload } from 'lucide-vue-next';
import GovBadge from '@/Components/UI/GovBadge.vue';
import GovButton from '@/Components/UI/GovButton.vue';

const props = defineProps({
    categoryCode: {
        type: String,
        required: true,
    },
    categoryName: {
        type: String,
        default: '',
    },
    items: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:items', 'file-uploaded']);
const uploadError = ref('');

function handleFileUpload(index, event) {
    uploadError.value = '';
    const file = event.target.files[0];
    if (!file) return;

    // Frontend validation: Hanya PDF dan Gambar (JPG/PNG), Max 10MB
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        uploadError.value = 'Tipe file tidak didukung. Harap unggah file PDF atau Gambar (JPG/PNG).';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        uploadError.value = 'Ukuran file melebihi batas maksimal 10MB.';
        return;
    }

    const updated = [...props.items];
    updated[index] = {
        ...updated[index],
        file: file,
        file_name: file.name,
        file_size: (file.size / (1024 * 1024)).toFixed(2) + ' MB',
        upload_date: new Date().toISOString(),
    };

    emit('update:items', updated);
    emit('file-uploaded', { index, item: updated[index] });
}

function handleRemoveFile(index) {
    const updated = [...props.items];
    updated[index] = {
        ...updated[index],
        file: null,
        file_name: null,
        file_size: null,
        upload_date: null,
    };
    emit('update:items', updated);
}
</script>

<template>
    <div class="bg-surface rounded-xl border border-hairline p-5 shadow-subtle space-y-4">
        <!-- Checklist Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-hairline pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-ink">
                        Checklist Kelengkapan Dokumen Wajib
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-accent text-white font-mono">
                        {{ categoryCode }}
                    </span>
                </div>
                <p class="text-xs text-ink-muted mt-0.5">
                    Standar dokumen bukti pertanggungjawaban untuk {{ categoryName || 'Kategori Belanja' }}
                </p>
            </div>
            <div class="text-xs text-ink-muted font-medium">
                Kelengkapan Dokumen:
                <span class="font-semibold text-ink font-mono tabular-nums">
                    {{ items.filter(d => d.file).length }} / {{ items.length }}
                </span>
            </div>
        </div>

        <!-- Upload Error Banner -->
        <div
            v-if="uploadError"
            class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center justify-between"
        >
            <span>{{ uploadError }}</span>
            <button type="button" class="font-semibold underline ml-3" @click="uploadError = ''">Tutup</button>
        </div>

        <!-- Checklist Items List -->
        <div class="space-y-3">
            <div
                v-for="(doc, idx) in items"
                :key="doc.id"
                class="p-3.5 rounded-lg border transition-colors duration-150 ease-unslop"
                :class="[
                    doc.file
                        ? 'bg-emerald-50/40 border-emerald-200'
                        : doc.required
                            ? 'bg-raised border-hairline'
                            : 'bg-surface border-dashed border-hairline'
                ]"
            >
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <!-- Left: Document Detail -->
                    <div class="flex items-start gap-3 flex-1">
                        <!-- Status Icon -->
                        <div
                            class="w-7 h-7 rounded-md flex items-center justify-center shrink-0 mt-0.5 text-xs font-semibold"
                            :class="[
                                doc.file
                                    ? 'bg-semantic-success text-white'
                                    : doc.required
                                        ? 'bg-amber-100 text-amber-800 border border-amber-300'
                                        : 'bg-raised text-ink-muted'
                            ]"
                        >
                            <Check v-if="doc.file" class="w-4 h-4" :stroke-width="1.75" />
                            <span v-else>{{ idx + 1 }}</span>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-semibold text-ink">{{ doc.name }}</h4>
                                <span
                                    v-if="doc.required"
                                    class="text-xs font-semibold text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200"
                                >
                                    Wajib
                                </span>
                                <span
                                    v-else
                                    class="text-xs font-medium text-ink-muted bg-raised px-1.5 py-0.5 rounded"
                                >
                                    Kondisional / Opsional
                                </span>
                            </div>
                            <p class="text-xs text-ink-muted mt-0.5 leading-tight">{{ doc.description }}</p>

                            <!-- Uploaded File Badge -->
                            <div v-if="doc.file" class="mt-2 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-800 bg-emerald-100/70 px-2.5 py-1 rounded-md">
                                    <FileText class="w-3.5 h-3.5 text-semantic-success" :stroke-width="1.5" />
                                    <span class="max-w-xs truncate">{{ doc.file_name }}</span>
                                    <span class="text-xs text-emerald-700 font-mono">({{ doc.file_size }})</span>
                                </span>
                                <button
                                    type="button"
                                    @click="handleRemoveFile(idx)"
                                    class="text-xs text-semantic-danger hover:underline"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Upload Button Action -->
                    <div class="sm:self-center shrink-0">
                        <label
                            class="cursor-pointer inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors shadow-subtle"
                            :class="[
                                doc.file
                                    ? 'bg-surface text-ink-secondary border-hairline hover:bg-raised'
                                    : 'bg-accent text-white border-transparent hover:bg-accent-hover'
                            ]"
                        >
                            <Upload class="w-3.5 h-3.5" :stroke-width="1.5" />
                            <span>{{ doc.file ? 'Ganti File' : 'Unggah Berkas' }}</span>
                            <input
                                type="file"
                                class="hidden"
                                accept=".pdf,image/jpeg,image/png"
                                @change="handleFileUpload(idx, $event)"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

