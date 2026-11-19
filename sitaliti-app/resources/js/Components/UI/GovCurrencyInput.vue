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
        <label v-if="label" class="block text-xs font-medium text-ink-secondary mb-1.5">
            {{ label }} <span v-if="required" class="text-semantic-danger">*</span>
        </label>
        <div class="relative rounded-lg">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                <span class="text-xs font-medium text-ink-muted">Rp</span>
            </div>
            <input
                type="text"
                :value="formattedDisplay"
                @input="handleInput"
                :disabled="disabled"
                :placeholder="placeholder"
                class="block w-full h-10 rounded-lg border-hairline bg-surface pl-10 pr-3.5 text-right text-sm font-mono font-medium tabular-nums text-ink placeholder:text-ink-muted shadow-subtle focus:border-accent focus:ring-1 focus:ring-accent disabled:bg-raised disabled:text-ink-muted disabled:cursor-not-allowed transition-colors duration-150 ease-unslop"
                :class="[error ? 'border-semantic-danger focus:border-semantic-danger focus:ring-semantic-danger' : '']"
            />
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-semantic-danger font-medium">{{ error }}</p>
    </div>
</template>

