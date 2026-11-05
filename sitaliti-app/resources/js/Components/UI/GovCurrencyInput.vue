<script setup>
import { computed } from 'vue';
import { formatRupiah, parseRupiah } from '@/Utils/currency';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    label: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: '0',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const formattedDisplay = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return '';
    return formatRupiah(props.modelValue, false);
});

function handleInput(event) {
    const rawValue = event.target.value;
    const numericValue = parseRupiah(rawValue);
    emit('update:modelValue', numericValue);
}
</script>

<template>
    <div class="w-full">
        <label v-if="label" class="block text-xs font-semibold text-gov-navy-900 uppercase tracking-wider mb-1.5">
            {{ label }} <span v-if="required" class="text-rose-500">*</span>
        </label>
        <div class="relative rounded-lg shadow-sm">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <span class="text-xs font-bold text-gov-navy-700">Rp</span>
            </div>
            <input
                type="text"
                :value="formattedDisplay"
                @input="handleInput"
                :disabled="disabled"
                :placeholder="placeholder"
                class="block w-full rounded-lg border-gov-surface-border pl-10 pr-3 py-2 text-right text-sm font-medium tabular-nums text-gov-navy-900 placeholder:text-slate-400 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed"
                :class="[error ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : '']"
            />
        </div>
        <p v-if="error" class="mt-1 text-xs text-rose-600 font-medium">{{ error }}</p>
    </div>
</template>

