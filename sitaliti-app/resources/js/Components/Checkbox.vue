<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="h-4 w-4 rounded border-hairline bg-surface text-accent shadow-subtle focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 transition-colors duration-150 ease-unslop"
    />
</template>
