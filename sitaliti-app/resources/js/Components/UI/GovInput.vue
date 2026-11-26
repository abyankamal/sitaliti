<script setup>
import { computed } from 'vue';

let nextInputId = 0;

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
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

const fallbackId = `gov-input-${++nextInputId}`;
const inputId = computed(() => props.id || fallbackId);
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="inputId" class="block text-xs font-medium text-ink-secondary mb-1.5">
            {{ label }} <span v-if="required" class="text-semantic-danger">*</span>
        </label>
        <div class="relative rounded-lg">
            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                :disabled="disabled"
                :placeholder="placeholder"
                :aria-invalid="error ? 'true' : undefined"
                class="block w-full h-10 rounded-lg border-hairline bg-surface px-3.5 text-sm text-ink placeholder:text-ink-muted shadow-subtle focus:border-accent focus:ring-1 focus:ring-accent disabled:bg-raised disabled:text-ink-muted disabled:cursor-not-allowed transition-colors duration-150 ease-unslop"
                :class="[error ? 'border-semantic-danger focus:border-semantic-danger focus:ring-semantic-danger' : '']"
            />
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-semantic-danger font-medium">{{ error }}</p>
        <p v-else-if="help" class="mt-1.5 text-xs text-ink-muted">{{ help }}</p>
    </div>
</template>

