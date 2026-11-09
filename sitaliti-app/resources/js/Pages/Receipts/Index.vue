<script setup>
import { Head } from '@inertiajs/vue3';
import GovAppLayout from '@/Layouts/GovAppLayout.vue';

defineProps({
    receipts: {
        type: Object,
        default: () => ({ data: [] }),
    },
    tabCounts: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    unitKerjaList: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Daftar & Manajemen Kuitansi" />

    <GovAppLayout title="Daftar & Manajemen Kuitansi">
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gov-navy-900 tracking-tight">
                Daftar & Manajemen Kuitansi
            </h1>
        </div>
    </GovAppLayout>
</template>

