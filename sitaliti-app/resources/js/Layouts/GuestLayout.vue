<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-canvas px-4 py-8 text-ink"
    >
        <div class="text-center">
            <Link href="/" class="inline-flex flex-col items-center gap-3">
                <img
                    src="/Lambang_Kabupaten_Garut.svg.webp"
                    alt="Lambang Kabupaten Garut"
                    class="h-12 w-12 object-contain"
                />
                <div class="space-y-1">
                    <h1 class="text-lg font-semibold tracking-tight text-ink">
                        SI-TALITI
                    </h1>
                    <p class="text-xs text-ink-muted">
                        Kecamatan Tarogong Kidul · Pre-Audit Gateway SPJ
                    </p>
                </div>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-xl border border-hairline bg-surface p-6 sm:p-8 shadow-subtle sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
