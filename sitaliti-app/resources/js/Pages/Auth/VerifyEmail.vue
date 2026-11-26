<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="mb-4 text-sm text-ink-secondary">
            Terima kasih telah mendaftar! Sebelum memulai, mohon verifikasi alamat email Anda dengan mengeklik tautan yang baru saja kami kirimkan ke email Anda. Jika Anda tidak menerima email tersebut, kami akan dengan senang hati mengirimkan ulang.
        </div>

        <div
            class="mb-4 text-sm font-medium text-semantic-success"
            v-if="verificationLinkSent"
        >
            Tautan verifikasi baru telah dikirim ke alamat email yang Anda daftarkan.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between gap-4">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Kirim Ulang Email Verifikasi
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-ink-secondary underline hover:text-ink focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-colors duration-150 ease-unslop"
                    >Keluar</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
