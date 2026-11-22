<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk — E - Verifikatur Kecamatan Tarogong Kidul" />

        <div v-if="status" class="mb-4 text-sm font-medium text-semantic-success">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="username" value="Username" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-1.5 block w-full"
                    :class="{ 'border-semantic-danger focus:border-semantic-danger focus:ring-semantic-danger': form.errors.username }"
                    v-model="form.username"
                    placeholder="bpp.pataruman"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.username" />
            </div>

            <div>
                <InputLabel for="password" value="Kata sandi" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    :class="{ 'border-semantic-danger focus:border-semantic-danger focus:ring-semantic-danger': form.errors.password }"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="block pt-0.5">
                <label class="inline-flex items-center cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-ink-secondary">Ingat saya</span>
                </label>
            </div>

            <div class="pt-2 flex items-center justify-between gap-4 border-t border-hairline">
                <span class="text-xs text-ink-muted">
                    Lupa kata sandi? Hubungi Admin SKPD
                </span>

                <PrimaryButton
                    class="shrink-0"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Masuk
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
