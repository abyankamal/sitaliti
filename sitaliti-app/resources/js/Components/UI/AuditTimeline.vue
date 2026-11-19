<script setup>
import { computed } from 'vue';
import {
    FileText,
    Pencil,
    Send,
    CheckCircle2,
    XCircle,
    ShieldCheck,
    Search,
    BadgeCheck,
    RotateCcw,
    Ban,
    RefreshCw,
    Package,
    PackageOpen,
    Banknote,
    Clock,
    CircleDot,
} from 'lucide-vue-next';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { formatTanggalIndo } from '@/Utils/date';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

/**
 * Mapping action -> ikon Lucide, warna tenang, dan label
 */
const ACTION_CONFIG = {
    CREATED:              { icon: FileText,     label: 'Dibuat',                  variant: 'neutral', dotClass: 'bg-raised text-ink-secondary border-hairline' },
    DRAFT_CREATED:        { icon: FileText,     label: 'Draft Dibuat',            variant: 'neutral', dotClass: 'bg-raised text-ink-secondary border-hairline' },
    RECEIPT_UPDATED:      { icon: Pencil,       label: 'Kuitansi Diperbarui',     variant: 'info',    dotClass: 'bg-sky-50 text-semantic-info border-sky-200' },
    SUBMITTED:            { icon: Send,         label: 'Diajukan',                variant: 'info',    dotClass: 'bg-sky-50 text-semantic-info border-sky-200' },
    SUBMITTED_TO_PPTK:    { icon: Send,         label: 'Diajukan ke PPTK',        variant: 'info',    dotClass: 'bg-sky-50 text-semantic-info border-sky-200' },
    PPTK_APPROVED:        { icon: CheckCircle2, label: 'Disetujui PPTK',          variant: 'success', dotClass: 'bg-emerald-50 text-semantic-success border-emerald-200' },
    PPTK_REJECTED:        { icon: XCircle,      label: 'Ditolak PPTK',            variant: 'danger',  dotClass: 'bg-red-50 text-semantic-danger border-red-200' },
    LURAH_APPROVED:       { icon: ShieldCheck,  label: 'Diotorisasi Lurah',       variant: 'success', dotClass: 'bg-emerald-50 text-semantic-success border-emerald-200' },
    LURAH_REJECTED:       { icon: XCircle,      label: 'Ditolak Lurah',           variant: 'danger',  dotClass: 'bg-red-50 text-semantic-danger border-red-200' },
    UNDER_REVIEW:         { icon: Search,       label: 'Sedang Diverifikasi',     variant: 'warning', dotClass: 'bg-amber-50 text-semantic-warning border-amber-200' },
    VERIFIED:             { icon: BadgeCheck,   label: 'Lolos Verifikasi',        variant: 'success', dotClass: 'bg-emerald-50 text-semantic-success border-emerald-200' },
    REVISION_MINOR:       { icon: RotateCcw,    label: 'Revisi Minor',            variant: 'warning', dotClass: 'bg-amber-50 text-semantic-warning border-amber-200' },
    REVISION_MAJOR:       { icon: Ban,          label: 'Revisi Mayor',            variant: 'danger',  dotClass: 'bg-red-50 text-semantic-danger border-red-200' },
    REVISION_RESUBMITTED: { icon: RefreshCw,    label: 'Revisi Dikirim Ulang',    variant: 'info',    dotClass: 'bg-accent-soft text-accent border-accent-border' },
    BUNDLED:              { icon: Package,      label: 'Masuk Bundel SPP',        variant: 'primary', dotClass: 'bg-accent-soft text-accent border-accent-border' },
    UNBUNDLED:            { icon: PackageOpen,  label: 'Dikeluarkan dari Bundel', variant: 'warning', dotClass: 'bg-amber-50 text-semantic-warning border-amber-200' },
    SP2D_SETTLED:         { icon: Banknote,     label: 'SP2D Cair / Lunas',       variant: 'success', dotClass: 'bg-emerald-50 text-semantic-success border-emerald-200' },
    SLA_BREACH:           { icon: Clock,        label: 'SLA Breach',              variant: 'danger',  dotClass: 'bg-red-50 text-semantic-danger border-red-200' },
    SLA_BREACHED:         { icon: Clock,        label: 'SLA Terlewati',           variant: 'danger',  dotClass: 'bg-red-50 text-semantic-danger border-red-200' },
};

function getConfig(action) {
    return ACTION_CONFIG[action] || {
        icon: CircleDot,
        label: action,
        variant: 'neutral',
        dotClass: 'bg-raised text-ink-secondary border-hairline',
    };
}

function formatWaktu(dateStr) {
    return formatTanggalIndo(dateStr, true);
}

const sortedEvents = computed(() => {
    return [...props.events].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});
</script>

<template>
    <div class="relative">
        <!-- Empty state -->
        <div v-if="sortedEvents.length === 0" class="text-center py-6">
            <div class="text-ink-muted text-sm">Belum ada riwayat aktivitas</div>
        </div>

        <!-- Timeline -->
        <div v-else class="relative">
            <!-- Vertical line -->
            <div class="absolute left-4 top-4 bottom-4 w-px bg-hairline"></div>

            <div class="space-y-0">
                <div
                    v-for="(event, idx) in sortedEvents"
                    :key="event.id || idx"
                    class="relative flex items-start gap-3 py-2.5"
                >
                    <!-- Icon Dot -->
                    <div
                        class="w-8 h-8 shrink-0 rounded-[50%] border flex items-center justify-center z-10 ring-2 ring-surface"
                        :class="getConfig(event.action).dotClass"
                    >
                        <component :is="getConfig(event.action).icon" class="w-4 h-4" :stroke-width="1.5" />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0 pb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <GovBadge :variant="getConfig(event.action).variant" size="sm">
                                {{ getConfig(event.action).label }}
                            </GovBadge>

                            <!-- Status transition -->
                            <span
                                v-if="event.status_lama && event.status_baru"
                                class="text-xs text-ink-muted font-mono"
                            >
                                {{ event.status_lama }} → {{ event.status_baru }}
                            </span>
                        </div>

                        <!-- Actor & Time -->
                        <div class="flex items-center gap-2 mt-1 text-xs text-ink-secondary flex-wrap">
                            <span v-if="event.actor_name" class="font-medium text-ink">
                                {{ event.actor_name }}
                            </span>
                            <span v-if="event.actor_role" class="text-ink-muted">
                                ({{ event.actor_role }})
                            </span>
                            <span class="text-hairline">·</span>
                            <span class="text-ink-muted tabular-nums">{{ formatWaktu(event.created_at) }}</span>
                        </div>

                        <!-- Note -->
                        <div
                            v-if="event.catatan"
                            class="mt-2 p-2.5 bg-canvas rounded-lg border border-hairline text-xs text-ink-secondary leading-relaxed"
                        >
                            <span class="font-medium text-ink">Catatan:</span>
                            {{ event.catatan }}
                        </div>

                        <!-- IP Address -->
                        <div
                            v-if="event.ip_address"
                            class="mt-1 text-xs text-ink-muted font-mono tabular-nums"
                        >
                            IP: {{ event.ip_address }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

