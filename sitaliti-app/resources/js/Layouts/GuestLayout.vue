<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 py-8"
    >
        <div class="text-center">
            <Link href="/" class="inline-flex flex-col items-center gap-2">
                <img
                    src="/Lambang_Kabupaten_Garut.svg.webp"
                    alt="Lambang Kabupaten Garut"
                    class="h-14 w-14 object-contain rounded-xl bg-white p-1.5 shadow-md ring-2 ring-gov-gold-500/40"
                />
                <div>
                    <h1 class="text-lg font-extrabold tracking-wide text-gov-navy-950">
                        SI-TALITI
                    </h1>
                    <p class="text-xs font-medium text-slate-600">
                        Kecamatan Tarogong Kidul • Pre-Audit Gateway SPJ
                    </p>
                </div>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-xl border border-slate-200 bg-white px-6 py-6 shadow-md sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
