<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    title: {
        type: String,
        default: 'SI-TALITI v2.0',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {
    name: 'Operator SPJ',
    email: 'operator@tarogongkidul.garutkab.go.id',
    role: 'OPERATOR'
});

const isSidebarOpen = ref(true);

const ROLE_DISPLAY_NAMES = {
    OPERATOR: 'Operator SPJ',
    PPTK: 'Pejabat Pelaksana Teknis (PPTK)',
    VERIFIKATOR: 'Kasubag Keuangan (PPK)',
    BPP: 'Bendahara Pengeluaran (BP / BPP)',
    KPA: 'Camat / KPA',
};

// Definisi item navigasi beserta peran yang diizinkan melihatnya
const allNavItems = [
    {
        name: 'Dashboard Anggaran',
        route: 'dashboard',
        icon: 'dashboard',
        roles: ['OPERATOR', 'PPTK', 'VERIFIKATOR', 'BPP', 'KPA'],
    },
    {
        name: 'Daftar Kuitansi',
        route: 'receipts.index',
        icon: 'table',
        roles: ['OPERATOR', 'PPTK', 'VERIFIKATOR', 'BPP', 'KPA'],
    },
    {
        name: 'Input Kuitansi',
        route: 'receipts.create',
        icon: 'receipt',
        roles: ['OPERATOR', 'KPA'],
    },
    {
        name: 'Workspace Verifikasi',
        route: 'verification.index',
        icon: 'verification',
        roles: ['PPTK', 'VERIFIKATOR', 'KPA'],
    },
    {
        name: 'Bundel SPP (GU/TU/LS)',
        route: 'bundles.index',
        icon: 'bundle',
        roles: ['BPP', 'KPA', 'VERIFIKATOR'],
    },
];

// Filter navigasi berdasarkan peran aktif pengguna
const navItems = computed(() => {
    const userRole = user.value?.role || 'OPERATOR';
    return allNavItems.filter(item => !item.roles || item.roles.includes(userRole));
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800 antialiased">
        <!-- Top App Bar - Deep Government Navy Theme -->
        <header class="bg-gov-navy-950 text-white border-b border-gov-navy-800 sticky top-0 z-40 shadow-md">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Left: Logo & Identity -->
                    <div class="flex items-center gap-4">
                        <button
                            @click="isSidebarOpen = !isSidebarOpen"
                            class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-gov-navy-900 focus:outline-none"
                            title="Buka/Tutup Sidebar"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                        </button>

                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gov-navy-800 border border-gov-gold-500/30 flex items-center justify-center font-bold text-gov-gold-400 tracking-wider text-sm shadow-inner">
                                TLT
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold tracking-tight text-white text-base">SI-TALITI</span>
                                    <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-gov-gold-600/20 text-gov-gold-400 border border-gov-gold-500/30">v2.0 Pre-Audit</span>
                                </div>
                                <p class="text-[11px] text-slate-400 font-normal">Kecamatan Tarogong Kidul • Kab. Garut</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Context Info & Profile -->
                    <div class="flex items-center gap-4">
                        <div class="hidden md:flex flex-col text-right">
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-xs font-semibold text-slate-200">TA 2026 (RAK Perubahan)</span>
                            </div>
                            <span class="text-[11px] text-slate-400">Total Pagu: Rp 14.252.126.458</span>
                        </div>

                        <div class="h-8 w-px bg-gov-navy-800 hidden md:block"></div>

                        <!-- User Profile Badge -->
                        <div class="flex items-center gap-3 pl-2">
                            <div class="w-8 h-8 rounded-full bg-gov-navy-800 border border-slate-600 flex items-center justify-center text-xs font-semibold text-white">
                                {{ user.name?.charAt(0) || 'U' }}
                            </div>
                            <div class="hidden sm:block text-left leading-tight">
                                <p class="text-xs font-medium text-white">{{ user.name }}</p>
                                <p class="text-[10px] text-gov-gold-400 font-medium">
                                    {{ ROLE_DISPLAY_NAMES[user.role] || user.role || 'Pegawai' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            <!-- Sidebar Navigation -->
            <aside
                :class="[isSidebarOpen ? 'w-64' : 'w-20']"
                class="bg-gov-navy-900 border-r border-gov-navy-800 transition-all duration-200 flex flex-col justify-between shrink-0"
            >
                <div class="p-3 space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="route().has(item.route) ? route(item.route) : '#'"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors group"
                        :class="[
                            route().current(item.route)
                                ? 'bg-gov-navy-800 text-white border-l-4 border-gov-gold-500 pl-2'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-gov-navy-800/50'
                        ]"
                    >
                        <!-- Simple Nav Icons -->
                        <span class="w-5 h-5 flex items-center justify-center text-gov-gold-400">
                            <svg v-if="item.icon === 'dashboard'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <svg v-else-if="item.icon === 'table'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <svg v-else-if="item.icon === 'receipt'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <svg v-else-if="item.icon === 'verification'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </span>
                        <span v-if="isSidebarOpen" class="truncate font-medium">{{ item.name }}</span>
                    </Link>
                </div>

                <!-- Gatekeeper Status Footer in Sidebar -->
                <div v-if="isSidebarOpen" class="p-4 m-3 rounded-lg bg-gov-navy-950/70 border border-gov-navy-800 text-xs">
                    <div class="flex items-center justify-between text-slate-400 mb-1">
                        <span>Status Anggaran</span>
                        <span class="text-emerald-400 font-semibold">Tersedia</span>
                    </div>
                    <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-gov-gold-500 h-full rounded-full" style="width: 42%"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2">Realisasi: 42% dari RAK berjalan</p>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <!-- Page Header & Action Bar -->
                <div v-if="$slots.header" class="bg-white border-b border-gov-surface-border px-6 py-4 shadow-sm">
                    <slot name="header" />
                </div>

                <!-- Page Body -->
                <div class="p-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

