<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Kata Sandi" />

        <div class="mb-5">
            <h2 class="text-lg font-semibold tracking-tight text-ink">
                Lupa Kata Sandi
            </h2>
            <p class="mt-1 text-xs text-ink-secondary leading-relaxed">
                Masukkan alamat email dinas Anda untuk menerima tautan pengaturan ulang kata sandi.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Alamat Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="pt-2 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Kirim Tautan Atur Ulang Kata Sandi
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
