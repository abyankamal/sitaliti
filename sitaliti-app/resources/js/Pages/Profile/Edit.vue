<script setup>
import GovAppLayout from '@/Layouts/GovAppLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <GovAppLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink">
                Profile
            </h2>
        </template>

        <div class="space-y-6 max-w-3xl">
            <div class="rounded-xl border border-hairline bg-surface p-6 shadow-subtle">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="rounded-xl border border-hairline bg-surface p-6 shadow-subtle">
                <UpdatePasswordForm class="max-w-xl" />
            </div>
        </div>
    </GovAppLayout>
</template>
