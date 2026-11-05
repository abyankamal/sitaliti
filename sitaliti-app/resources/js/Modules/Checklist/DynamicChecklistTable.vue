<script setup>
import { ref } from 'vue';
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

function handleFileUpload(index, event) {
    const file = event.target.files[0];
    if (!file) return;

    // Frontend validation: Hanya PDF dan Gambar (JPG/PNG), Max 10MB
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        alert('Tipe file tidak didukung. Harap unggah file PDF atau Gambar (JPG/PNG).');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file melebihi batas maksimal 10MB.');
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
    <div class="bg-white rounded-xl border border-gov-surface-border p-5 shadow-sm space-y-4">
        <!-- Checklist Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gov-surface-border pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-gov-navy-950">
                        Checklist Kelengkapan Dokumen Wajib
                    </h3>
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-gov-navy-900 text-white font-mono">
                        {{ categoryCode }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Standar dokumen bukti pertanggungjawaban untuk {{ categoryName || 'Kategori Belanja' }}
                </p>
            </div>
            <div class="text-xs text-slate-500 font-medium">
                Kelengkapan Dokumen:
                <span class="font-bold text-gov-navy-950">
                    {{ items.filter(d => d.file).length }} / {{ items.length }}
                </span>
            </div>
        </div>

        <!-- Checklist Items List -->
        <div class="space-y-3">
            <div
                v-for="(doc, idx) in items"
                :key="doc.id"
                class="p-3.5 rounded-lg border transition-all"
                :class="[
                    doc.file
                        ? 'bg-emerald-50/40 border-emerald-200'
                        : doc.required
                            ? 'bg-slate-50 border-slate-200'
                            : 'bg-white border-dashed border-slate-200'
                ]"
            >
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <!-- Left: Document Detail -->
                    <div class="flex items-start gap-3 flex-1">
                        <!-- Status Icon -->
                        <div
                            class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold"
                            :class="[
                                doc.file
                                    ? 'bg-emerald-500 text-white'
                                    : doc.required
                                        ? 'bg-amber-100 text-amber-800 border border-amber-300'
                                        : 'bg-slate-100 text-slate-400'
                            ]"
                        >
                            <svg v-if="doc.file" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-bold text-gov-navy-900">{{ doc.name }}</h4>
                                <span
                                    v-if="doc.required"
                                    class="text-[10px] font-bold text-rose-700 bg-rose-50 px-1.5 py-0.2 rounded border border-rose-200"
                                >
                                    Wajib
                                </span>
                                <span
                                    v-else
                                    class="text-[10px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded"
                                >
                                    Kondisional / Opsional
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5 leading-tight">{{ doc.description }}</p>

                            <!-- Uploaded File Badge -->
                            <div v-if="doc.file" class="mt-2 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-800 bg-emerald-100/70 px-2.5 py-1 rounded-md">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="max-w-xs truncate">{{ doc.file_name }}</span>
                                    <span class="text-[10px] text-emerald-700 font-mono">({{ doc.file_size }})</span>
                                </span>
                                <button
                                    type="button"
                                    @click="handleRemoveFile(idx)"
                                    class="text-[11px] text-rose-600 hover:text-rose-800 hover:underline"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Upload Button Action -->
                    <div class="sm:self-center shrink-0">
                        <label
                            class="cursor-pointer inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors shadow-sm"
                            :class="[
                                doc.file
                                    ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    : 'bg-gov-navy-900 text-white border-transparent hover:bg-gov-navy-800'
                            ]"
                        >
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
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

