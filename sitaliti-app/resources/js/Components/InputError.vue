<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-xs font-medium text-semantic-danger">
            {{ message }}
        </p>
    </div>
</template>
