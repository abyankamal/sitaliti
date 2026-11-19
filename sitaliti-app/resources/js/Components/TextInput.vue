<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="h-10 rounded-lg border-hairline bg-surface px-3 text-sm text-ink placeholder:text-ink-muted shadow-subtle transition-colors duration-150 ease-unslop focus:border-accent focus:ring-1 focus:ring-accent"
        v-model="model"
        ref="input"
    />
</template>
