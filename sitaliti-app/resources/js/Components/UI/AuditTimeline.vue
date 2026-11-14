<script setup>
import { computed } from 'vue';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { formatTanggalIndo } from '@/Utils/date';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

/**
 * Mapping action → ikon, warna, dan label
 */
const ACTION_CONFIG = {
    CREATED:              { icon: '📝', label: 'Dibuat',                variant: 'neutral',  color: 'bg-slate-400' },
    DRAFT_CREATED:        { icon: '📝', label: 'Draft Dibuat',          variant: 'neutral',  color: 'bg-slate-400' },
    RECEIPT_UPDATED:      { icon: '✏️', label: 'Kuitansi Diperbarui',   variant: 'info',     color: 'bg-sky-500' },
    SUBMITTED:            { icon: '📤', label: 'Diajukan',              variant: 'info',     color: 'bg-sky-500' },
    SUBMITTED_TO_PPTK:    { icon: '📤', label: 'Diajukan ke PPTK',      variant: 'info',     color: 'bg-sky-500' },
    PPTK_APPROVED:        { icon: '✅', label: 'Disetujui PPTK',        variant: 'success',  color: 'bg-emerald-500' },
    PPTK_REJECTED:        { icon: '❌', label: 'Ditolak PPTK',          variant: 'danger',   color: 'bg-rose-500' },
    LURAH_APPROVED:       { icon: '🛡️', label: 'Diotorisasi Lurah',     variant: 'success',  color: 'bg-emerald-600' },
    LURAH_REJECTED:       { icon: '❌', label: 'Ditolak Lurah',         variant: 'danger',   color: 'bg-rose-500' },
    UNDER_REVIEW:         { icon: '🔍', label: 'Sedang Diverifikasi',   variant: 'warning',  color: 'bg-amber-500' },
    VERIFIED:             { icon: '🏆', label: 'Lolos Verifikasi',      variant: 'success',  color: 'bg-emerald-600' },
    REVISION_MINOR:       { icon: '🔄', label: 'Revisi Minor',          variant: 'warning',  color: 'bg-amber-500' },
    REVISION_MAJOR:       { icon: '⛔', label: 'Revisi Mayor',          variant: 'danger',   color: 'bg-rose-600' },
    REVISION_RESUBMITTED: { icon: '🔁', label: 'Revisi Dikirim Ulang',  variant: 'info',     color: 'bg-indigo-500' },
    BUNDLED:              { icon: '📦', label: 'Masuk Bundel SPP',      variant: 'primary',  color: 'bg-gov-navy-600' },
    UNBUNDLED:            { icon: '📤', label: 'Dikeluarkan dari Bundel', variant: 'warning', color: 'bg-amber-600' },
    SP2D_SETTLED:         { icon: '💰', label: 'SP2D Cair / Lunas',     variant: 'success',  color: 'bg-emerald-700' },
    SLA_BREACH:           { icon: '⏰', label: 'SLA Breach',            variant: 'danger',   color: 'bg-rose-500' },
    SLA_BREACHED:         { icon: '⏰', label: 'SLA Terlewati',         variant: 'danger',   color: 'bg-rose-600' },
};

function getConfig(action) {
    return ACTION_CONFIG[action] || { icon: '📌', label: action, variant: 'neutral', color: 'bg-slate-400' };
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
            <div class="text-slate-400 text-sm">Belum ada riwayat aktivitas</div>
        </div>

        <!-- Timeline -->
        <div v-else class="relative">
            <!-- Vertical line -->
            <div class="absolute left-[15px] top-3 bottom-3 w-0.5 bg-slate-200"></div>

            <div class="space-y-0">
                <div
                    v-for="(event, idx) in sortedEvents"
                    :key="event.id || idx"
                    class="relative flex items-start gap-3 py-2.5 group"
                >
                    <!-- Dot -->
                    <div
                        class="w-[31px] h-[31px] shrink-0 rounded-full flex items-center justify-center text-sm z-10 ring-2 ring-white shadow-sm"
                        :class="getConfig(event.action).color"
                    >
                        <span class="text-xs leading-none">{{ getConfig(event.action).icon }}</span>
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
                                class="text-[10px] text-slate-400 font-mono"
                            >
                                {{ event.status_lama }} → {{ event.status_baru }}
                            </span>
                        </div>

                        <!-- Actor & Time -->
                        <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500">
                            <span v-if="event.actor_name" class="font-semibold text-slate-700">
                                {{ event.actor_name }}
                            </span>
                            <span v-if="event.actor_role" class="text-slate-400">
                                ({{ event.actor_role }})
                            </span>
                            <span class="text-slate-300">·</span>
                            <span>{{ formatWaktu(event.created_at) }}</span>
                        </div>

                        <!-- Note -->
                        <div
                            v-if="event.catatan"
                            class="mt-1.5 p-2 bg-slate-50 rounded-lg border border-slate-100 text-[11px] text-slate-600 leading-relaxed"
                        >
                            <span class="font-semibold text-slate-500">Catatan:</span>
                            {{ event.catatan }}
                        </div>

                        <!-- IP Address (subtle) -->
                        <div
                            v-if="event.ip_address"
                            class="mt-1 text-[9px] text-slate-300 font-mono"
                        >
                            IP: {{ event.ip_address }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

