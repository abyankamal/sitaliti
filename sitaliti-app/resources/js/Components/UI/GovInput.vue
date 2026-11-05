<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        default: null,
    },
    type: {
        type: String,
        default: 'text',
    },
    error: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    help: {
        type: String,
        default: null,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="w-full">
        <label v-if="label" class="block text-xs font-semibold text-gov-navy-900 uppercase tracking-wider mb-1.5">
            {{ label }} <span v-if="required" class="text-rose-500">*</span>
        </label>
        <div class="relative rounded-lg shadow-sm">
            <input
                :type="type"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                :disabled="disabled"
                :placeholder="placeholder"
                class="block w-full rounded-lg border-gov-surface-border px-3.5 py-2 text-sm text-gov-navy-900 placeholder:text-slate-400 focus:border-gov-navy-800 focus:ring-1 focus:ring-gov-navy-800 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed transition-colors"
                :class="[error ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : '']"
            />
        </div>
        <p v-if="error" class="mt-1 text-xs text-rose-600 font-medium">{{ error }}</p>
        <p v-else-if="help" class="mt-1 text-xs text-slate-500">{{ help }}</p>
    </div>
</template>

