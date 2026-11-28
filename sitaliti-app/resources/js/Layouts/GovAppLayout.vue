<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Menu,
    LogOut,
    LayoutGrid,
    FilePlus2,
    Receipt,
    ClipboardCheck,
    FolderKanban,
    Route,
    BarChart3,
    Wallet,
    ListTree,
    Contact,
    Users,
    ShieldCheck,
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

const isSidebarOpen = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : true);

const roleDisplayName = computed(() => {
    if (user.value?.role === 'LURAH') return 'Lurah (Monitoring)';
    if (user.value?.role === 'BPP') return 'Bendahara Pengeluaran Pembantu (BPP)';
    if (user.value?.role === 'BP') return 'Bendahara Pengeluaran (BP)';
    if (user.value?.role === 'PPTK') return 'Pejabat Pelaksana Teknis (PPTK)';
    if (user.value?.role === 'VERIFIKATOR') return 'Kasubag Keuangan (PPK)';
    if (user.value?.role === 'KPA') return 'Camat / KPA (Monitoring)';
    return user.value?.role || 'Pegawai';
});

// Definisi grup navigasi beserta peran yang diizinkan melihatnya
const rawNavGroups = [
    {
        label: 'Operasional SPJ',
        items: [
            {
                name: 'Dashboard Anggaran',
                route: 'dashboard',
                activeRoutes: ['dashboard'],
                iconComponent: LayoutGrid,
                roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
            },
            {
                name: 'Input Kuitansi',
                route: 'receipts.create',
                activeRoutes: ['receipts.create'],
                iconComponent: FilePlus2,
                roles: ['BPP', 'BP', 'VERIFIKATOR'],
            },
            {
                name: 'Daftar Kuitansi',
                route: 'receipts.index',
                activeRoutes: ['receipts.index', 'receipts.edit', 'control-sheet.show'],
                iconComponent: Receipt,
                roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
            },
            {
                name: 'Workspace Verifikasi',
                route: 'verification.index',
                activeRoutes: ['verification.index', 'verification.workspace'],
                iconComponent: ClipboardCheck,
                roles: ['BP', 'VERIFIKATOR', 'PPTK'],
            },
            {
                name: 'Bundel SPP (GU/TU/LS)',
                route: 'bundles.index',
                activeRoutes: ['bundles.index'],
                iconComponent: FolderKanban,
                roles: ['BPP', 'BP', 'KPA', 'VERIFIKATOR', 'LURAH'],
            },
        ],
    },
    {
        label: 'Laporan & Rekap',
        items: [
            {
                name: 'Penugasan SPPD',
                route: 'sppd-assignments.index',
                activeRoutes: ['sppd-assignments.*'],
                iconComponent: Route,
                roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
            },
            {
                name: 'Laporan SPPD',
                route: 'sppd.report.index',
                activeRoutes: ['sppd.report.index'],
                iconComponent: Route,
                roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
            },
            {
                name: 'Rekap Belanja Operasi',
                route: 'rekap-belanja-operasi.index',
                activeRoutes: ['rekap-belanja-operasi.index'],
                iconComponent: BarChart3,
                roles: ['BPP', 'BP', 'PPTK', 'VERIFIKATOR', 'KPA', 'LURAH'],
            },
        ],
    },
    {
        label: 'Tata Kelola & Master',
        items: [
            {
                name: 'Kelola Anggaran (RAK)',
                route: 'budget-management.index',
                activeRoutes: ['budget-management.index'],
                iconComponent: Wallet,
                roles: ['VERIFIKATOR', 'BP', 'BPP'],
            },
            {
                name: 'Master Rekap Belanja Operasi',
                route: 'master-rekap-belanja-operasi.index',
                activeRoutes: ['master-rekap-belanja-operasi.index'],
                iconComponent: ListTree,
                roles: ['VERIFIKATOR', 'BP'],
                skpdOnly: true,
            },
            {
                name: 'Master Pegawai',
                route: 'pegawai.index',
                activeRoutes: ['pegawai.index'],
                iconComponent: Contact,
                roles: ['VERIFIKATOR', 'BP', 'BPP'],
            },
            {
                name: 'Kelola Pengguna & PPTK',
                route: 'users.index',
                activeRoutes: ['users.index'],
                iconComponent: Users,
                roles: ['VERIFIKATOR'],
                skpdOnly: true,
            },
            {
                name: 'Pengaturan Kepatuhan',
                route: 'compliance-settings.index',
                activeRoutes: ['compliance-settings.index'],
                iconComponent: ShieldCheck,
                roles: ['VERIFIKATOR', 'BP'],
                skpdOnly: true,
            },
        ],
    },
];

// Filter grup navigasi berdasarkan peran aktif pengguna dan sembunyikan grup kosong
const navGroups = computed(() => {
    const userRole = user.value?.role || 'BPP';
    const isSkpd = !user.value?.is_kelurahan_scoped;

    return rawNavGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                if (item.roles && !item.roles.includes(userRole)) return false;
                if (item.skpdOnly && !isSkpd) return false;
                return true;
            }),
        }))
        .filter((group) => group.items.length > 0);
});

function isNavActive(item) {
    return (item.activeRoutes || [item.route]).some((r) => route().current(r));
}
</script>

<template>
    <div class="min-h-screen bg-canvas flex flex-col font-sans text-ink antialiased">
        <!-- Top App Bar - Deep Civic Navy (Warm Civic Ledger) -->
        <header class="bg-accent text-white border-b border-white/15 sticky top-0 z-40 print:hidden">
            <div class="px-4 sm:px-6">
                <div class="flex items-center justify-between gap-3 h-14">
                    <!-- Left: Logo & Identity -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            @click="isSidebarOpen = !isSidebarOpen"
                            class="min-w-[44px] min-h-[44px] lg:min-w-0 lg:min-h-0 lg:w-9 lg:h-9 inline-flex items-center justify-center rounded-lg text-white/80 hover:text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80 transition-colors duration-150 ease-unslop shrink-0"
                            title="Buka/Tutup Sidebar"
                        >
                            <Menu class="w-4 h-4" :stroke-width="1.5" />
                        </button>

                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-surface/95 p-1 flex items-center justify-center shrink-0 shadow-subtle">
                                <img
                                    src="/Lambang_Kabupaten_Garut.svg.webp"
                                    alt="Lambang Kabupaten Garut"
                                    class="w-full h-full object-contain"
                                />
                            </div>
                            <div class="leading-tight min-w-0">
                                <span class="font-semibold tracking-tight text-white text-base block truncate">E - Verifikatur</span>
                                <p class="text-xs text-white/75 font-normal truncate">
                                    {{ user.is_kelurahan_scoped && user.unit_kerja ? `${user.unit_kerja.nama_unit} • Kec. Tarogong Kidul` : 'Kecamatan Tarogong Kidul • Kab. Garut' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Context Info & Profile -->
                    <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                        <div class="hidden md:flex items-center gap-2 text-xs text-white/85">
                            <span class="w-1.5 h-1.5 rounded-[50%] bg-emerald-400 shrink-0"></span>
                            <span class="font-medium tabular-nums">TA {{ new Date().getFullYear() }} (RAK Perubahan)</span>
                        </div>

                        <div class="h-6 w-px bg-white/15 hidden md:block"></div>

                        <!-- User Profile & Logout -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <Link
                                :href="route().has('profile.edit') ? route('profile.edit') : '#'"
                                class="min-h-[44px] lg:min-h-0 flex items-center gap-2.5 rounded-lg px-2 py-1 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80 transition-colors duration-150 ease-unslop"
                                title="Pengaturan Profil"
                            >
                                <div class="w-8 h-8 rounded-[50%] bg-white/15 border border-white/25 flex items-center justify-center text-xs font-semibold text-white shrink-0">
                                    {{ user.name?.charAt(0) || 'U' }}
                                </div>
                                <div class="hidden sm:block text-left leading-tight">
                                    <p class="text-xs font-medium text-white">{{ user.name }}</p>
                                    <p class="text-xs text-white/75">
                                        {{ roleDisplayName }}<span v-if="user.unit_kerja?.nama_unit"> · {{ user.unit_kerja.nama_unit }}</span>
                                    </p>
                                </div>
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                type="button"
                                class="min-h-[44px] lg:min-h-0 lg:h-8 inline-flex items-center gap-1.5 px-3 rounded-lg text-xs font-medium text-white/90 bg-white/10 hover:bg-white/20 hover:text-white border border-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80 transition-colors duration-150 ease-unslop"
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

        <div class="flex-1 flex overflow-hidden print:overflow-visible">
            <!-- Mobile Backdrop -->
            <div
                v-if="isSidebarOpen"
                class="fixed inset-0 z-30 bg-ink/40 lg:hidden print:hidden"
                @click="isSidebarOpen = false"
            ></div>

            <!-- Sidebar Navigation -->
            <aside
                :class="[isSidebarOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0 lg:w-16 w-64']"
                class="fixed inset-y-0 left-0 top-14 z-30 lg:static lg:top-0 bg-surface border-r border-hairline transition-[width,transform] duration-200 ease-unslop flex flex-col justify-between shrink-0 print:hidden"
            >
                <nav class="p-3 space-y-4 overflow-y-auto scrollbar-thin flex-1">
                    <div v-for="group in navGroups" :key="group.label" class="space-y-1">
                        <p
                            v-if="isSidebarOpen"
                            class="px-2.5 pb-1 text-xs font-medium text-ink-muted"
                        >
                            {{ group.label }}
                        </p>
                        <Link
                            v-for="item in group.items"
                            :key="item.name"
                            :href="route().has(item.route) ? route(item.route) : '#'"
                            :title="!isSidebarOpen ? item.name : undefined"
                            class="flex items-center gap-2.5 min-h-[44px] lg:min-h-0 lg:h-9 px-2.5 rounded-lg text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-accent transition-colors duration-150 ease-unslop"
                            :class="[
                                isNavActive(item)
                                    ? 'bg-accent-soft text-accent font-semibold'
                                    : 'text-ink-secondary hover:text-ink hover:bg-raised font-normal'
                            ]"
                        >
                            <span
                                class="w-5 h-5 flex items-center justify-center shrink-0"
                                :class="isNavActive(item) ? 'text-accent' : 'text-ink-muted'"
                            >
                                <component :is="item.iconComponent" :size="18" :stroke-width="1.5" />
                            </span>
                            <span v-if="isSidebarOpen" class="truncate">{{ item.name }}</span>
                        </Link>
                    </div>
                </nav>

                <!-- Gatekeeper Status Footer in Sidebar -->
                <div v-if="isSidebarOpen" class="px-3 py-3 m-3 rounded-lg bg-canvas border border-hairline text-xs shrink-0">
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
            <main class="flex-1 overflow-y-auto scrollbar-thin bg-canvas print:overflow-visible">
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

