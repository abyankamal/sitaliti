<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-[100dvh] grid grid-cols-1 lg:grid-cols-2 bg-canvas text-ink font-sans antialiased">
        <!-- Left Institutional Panel (Desktop) -->
        <aside class="hidden lg:flex flex-col justify-between bg-accent text-white p-10 xl:p-14 border-r border-white/10">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-lg bg-surface/95 p-1.5 flex items-center justify-center shrink-0 shadow-subtle">
                    <img
                        src="/Lambang_Kabupaten_Garut.svg.webp"
                        alt="Lambang Kabupaten Garut"
                        class="w-full h-full object-contain"
                    />
                </div>
                <div class="leading-tight">
                    <p class="text-xs font-medium text-white/75">Pemerintah Kabupaten Garut</p>
                    <p class="text-sm font-semibold text-white">Kecamatan Tarogong Kidul</p>
                </div>
            </div>

            <div class="max-w-md space-y-6">
                <div class="space-y-3">
                    <h1 class="text-3xl font-semibold tracking-tight text-white">
                        E - Verifikatur
                    </h1>
                    <p class="text-sm text-white/80 leading-relaxed">
                        Sistem Informasi Tata Kelola, Asistensi, dan Pre-Audit Akuntabilitas SPJ untuk Kecamatan Tarogong Kidul dan 5 Kelurahan.
                    </p>
                </div>

                <dl class="space-y-3 pt-4 border-t border-white/15 text-xs">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="font-medium text-white">Validasi Pagu RAK</dt>
                        <dd class="text-white/75 text-right">Penguncian pagu kas secara real-time</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="font-medium text-white">Kepatuhan Pajak</dt>
                        <dd class="text-white/75 text-right">Kalkulasi PPN, PPh, PBJT, dan Meterai</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="font-medium text-white">Lembar Kendali QR</dt>
                        <dd class="text-white/75 text-right">Pengesahan digital sebelum SIPD-RI</dd>
                    </div>
                </dl>
            </div>

            <p class="text-xs text-white/65 tabular-nums">
                Kecamatan Tarogong Kidul · Kabupaten Garut · TA {{ new Date().getFullYear() }}
            </p>
        </aside>

        <!-- Right Form Panel -->
        <main class="flex flex-col items-center justify-center px-4 py-8 sm:px-8 lg:px-12">
            <!-- Mobile Header -->
            <div class="lg:hidden mb-6 text-center">
                <Link href="/" class="inline-flex flex-col items-center gap-2.5">
                    <img
                        src="/Lambang_Kabupaten_Garut.svg.webp"
                        alt="Lambang Kabupaten Garut"
                        class="h-12 w-12 object-contain"
                    />
                    <div class="space-y-0.5">
                        <h1 class="text-lg font-semibold tracking-tight text-ink">
                            E - Verifikatur
                        </h1>
                        <p class="text-xs text-ink-muted">
                            Kecamatan Tarogong Kidul · Pre-Audit Gateway SPJ
                        </p>
                    </div>
                </Link>
            </div>

            <div class="w-full max-w-md overflow-hidden rounded-xl border border-hairline bg-surface p-6 sm:p-8 shadow-subtle">
                <slot />
            </div>
        </main>
    </div>
</template>
