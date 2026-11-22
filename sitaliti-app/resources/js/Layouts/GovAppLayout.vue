<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Menu,
    LogOut,
    LayoutGrid,
    Table2,
    FileText,
    ClipboardCheck,
    Layers,
    Users,
} from 'lucide-vue-next';

defineProps({
    title: {
        type: String,
        default: 'E - Verifikatur Kecamatan Tarogong Kidul',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {
    name: 'Bendahara',
    email: 'bpp@tarkid.garutkab.go.id',
    role: 'BPP'
});

const isSidebarOpen = ref(true);

const roleDisplayName = computed(() => {
    if (user.value?.role === 'LURAH') return 'Lurah (Monitoring)';
    if (user.value?.role === 'BPP') return 'Bendahara Pengeluaran Pembantu (BPP)';
    if (user.value?.role === 'BP') return 'Bendahara Pengeluaran (BP)';
    if (user.value?.role === 'PPTK') return 'Pejabat Pelaksana Teknis (PPTK)';
    if (user.value?.role === 'VERIFIKATOR') return 'Kasubag Keuangan (PPK)';
    if (user.value?.role === 'KPA') return 'Camat / KPA (Monitoring)';
    return user.value?.role || 'Pegawai';
});

// Definisi item navigasi beserta peran yang diizinkan melihatnya
const allNavItems = [
    {
        name: 'Dashboard Anggaran',
        route: 'dashboard',
        icon: 'dashboard',
        roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
    },
    {
        name: 'Daftar Kuitansi',
        route: 'receipts.index',
        icon: 'table',
        roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
    },
    {
        name: 'Input Kuitansi',
        route: 'receipts.create',
        icon: 'receipt',
        roles: ['BPP', 'BP', 'VERIFIKATOR'],
    },
    {
        name: 'Workspace Verifikasi',
        route: 'verification.index',
        icon: 'verification',
        roles: ['BP', 'VERIFIKATOR', 'PPTK'],
    },
    {
        name: 'Bundel SPP (GU/TU/LS)',
        route: 'bundles.index',
        icon: 'bundle',
        roles: ['BPP', 'BP', 'KPA', 'VERIFIKATOR', 'LURAH'],
    },
    {
        name: 'Kelola Pengguna & PPTK',
        route: 'users.index',
        icon: 'users',
        roles: ['VERIFIKATOR'],
        skpdOnly: true,
    },
    {
        name: 'Kelola Anggaran (RAK)',
        route: 'budget-management.index',
        icon: 'table',
        roles: ['VERIFIKATOR', 'BP', 'BPP'],
    },
];

// Filter navigasi berdasarkan peran aktif pengguna
const navItems = computed(() => {
    const userRole = user.value?.role || 'BPP';
    const isSkpd = !user.value?.is_kelurahan_scoped;
    return allNavItems.filter(item => {
        if (item.roles && !item.roles.includes(userRole)) return false;
        if (item.skpdOnly && !isSkpd) return false;
        return true;
    });
});
</script>

<template>
    <div class="min-h-screen bg-canvas flex flex-col font-sans text-ink antialiased">
        <!-- Top App Bar - Light Graphite & Institutional Navy -->
        <header class="bg-surface text-ink border-b border-hairline sticky top-0 z-40">
            <div class="px-4 sm:px-6">
                <div class="flex items-center justify-between h-14">
                    <!-- Left: Logo & Identity -->
                    <div class="flex items-center gap-3">
                        <button
                            @click="isSidebarOpen = !isSidebarOpen"
                            class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-ink-muted hover:text-ink hover:bg-raised focus:outline-none focus:ring-2 focus:ring-accent transition-colors duration-150 ease-unslop"
                            title="Buka/Tutup Sidebar"
                        >
                            <Menu class="w-4 h-4" :stroke-width="1.5" />
                        </button>

                        <div class="flex items-center gap-3">
                            <img
                                src="/Lambang_Kabupaten_Garut.svg.webp"
                                alt="Lambang Kabupaten Garut"
                                class="w-8 h-8 object-contain"
                            />
                            <div class="leading-tight">
                                <span class="font-semibold tracking-tight text-ink text-base block">E - Verifikatur</span>
                                <p class="text-xs text-ink-muted font-normal">
                                    {{ user.is_kelurahan_scoped && user.unit_kerja ? `${user.unit_kerja.nama_unit} • Kec. Tarogong Kidul` : 'Kecamatan Tarogong Kidul • Kab. Garut' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Context Info & Profile -->
                    <div class="flex items-center gap-4">
                        <div class="hidden md:flex items-center gap-2 text-xs text-ink-secondary">
                            <span class="w-1.5 h-1.5 rounded-[50%] bg-semantic-success shrink-0"></span>
                            <span class="font-medium tabular-nums">TA {{ new Date().getFullYear() }} (RAK Perubahan)</span>
                        </div>

                        <div class="h-6 w-px bg-hairline hidden md:block"></div>

                        <!-- User Profile & Logout -->
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-[50%] bg-accent-soft border border-accent-border flex items-center justify-center text-xs font-semibold text-accent shrink-0">
                                {{ user.name?.charAt(0) || 'U' }}
                            </div>
                            <div class="hidden sm:block text-left leading-tight">
                                <p class="text-xs font-medium text-ink">{{ user.name }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ roleDisplayName }}<span v-if="user.unit_kerja?.nama_unit"> · {{ user.unit_kerja.nama_unit }}</span>
                                </p>
                            </div>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                type="button"
                                class="ml-1 inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-medium text-ink-secondary bg-surface hover:bg-raised hover:text-semantic-danger border border-hairline transition-colors duration-150 ease-unslop"
                                title="Keluar dari aplikasi"
                            >
                                <LogOut class="w-4 h-4" :stroke-width="1.5" />
                                <span class="hidden sm:inline">Keluar</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            <!-- Sidebar Navigation -->
            <aside
                :class="[isSidebarOpen ? 'w-64' : 'w-16']"
                class="bg-canvas border-r border-hairline transition-[width] duration-200 ease-unslop flex flex-col justify-between shrink-0"
            >
                <nav class="p-3 space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="route().has(item.route) ? route(item.route) : '#'"
                        class="flex items-center gap-2.5 h-9 px-2.5 rounded-lg text-sm transition-colors duration-150 ease-unslop"
                        :class="[
                            route().current(item.route)
                                ? 'bg-accent-soft text-accent font-medium'
                                : 'text-ink-secondary hover:text-ink hover:bg-raised font-normal'
                        ]"
                    >
                        <span
                            class="w-5 h-5 flex items-center justify-center shrink-0"
                            :class="route().current(item.route) ? 'text-accent' : 'text-ink-muted'"
                        >
                            <LayoutGrid v-if="item.icon === 'dashboard'" :size="18" :stroke-width="1.5" />
                            <Table2 v-else-if="item.icon === 'table'" :size="18" :stroke-width="1.5" />
                            <FileText v-else-if="item.icon === 'receipt'" :size="18" :stroke-width="1.5" />
                            <ClipboardCheck v-else-if="item.icon === 'verification'" :size="18" :stroke-width="1.5" />
                            <Users v-else-if="item.icon === 'users'" :size="18" :stroke-width="1.5" />
                            <Layers v-else :size="18" :stroke-width="1.5" />
                        </span>
                        <span v-if="isSidebarOpen" class="truncate">{{ item.name }}</span>
                    </Link>
                </nav>

                <!-- Gatekeeper Status Footer in Sidebar -->
                <div v-if="isSidebarOpen" class="px-3 py-3 m-3 rounded-lg bg-surface border border-hairline text-xs">
                    <div class="flex items-center justify-between text-ink-secondary">
                        <span>Pre-Audit Gateway</span>
                        <span class="inline-flex items-center gap-1.5 text-semantic-success font-medium">
                            <span class="w-1.5 h-1.5 rounded-[50%] bg-semantic-success"></span>
                            Aktif
                        </span>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto bg-canvas">
                <!-- Page Header & Action Bar -->
                <div v-if="$slots.header" class="bg-surface border-b border-hairline px-6 py-4">
                    <div class="max-w-[1440px] mx-auto">
                        <slot name="header" />
                    </div>
                </div>

                <!-- Page Body -->
                <div class="p-6">
                    <div class="max-w-[1440px] mx-auto">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

