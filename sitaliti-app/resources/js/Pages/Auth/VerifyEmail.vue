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

        <div class="mb-5">
            <h2 class="text-lg font-semibold tracking-tight text-ink">
                Verifikasi Alamat Email
            </h2>
            <p class="mt-1 text-xs text-ink-secondary leading-relaxed">
                Mohon verifikasi alamat email Anda dengan mengeklik tautan yang telah kami kirimkan. Jika belum menerima email tersebut, Anda dapat mengirimkannya ulang.
            </p>
        </div>

        <div
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900"
            v-if="verificationLinkSent"
        >
            Tautan verifikasi baru telah dikirim ke alamat email yang Anda daftarkan.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-hairline">
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
                    class="min-h-[44px] sm:min-h-0 inline-flex items-center px-2 rounded-md text-xs font-medium text-ink-secondary underline hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 transition-colors duration-150 ease-unslop"
                    >Keluar</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
