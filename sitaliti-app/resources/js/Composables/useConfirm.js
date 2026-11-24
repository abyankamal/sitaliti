import { reactive } from 'vue';

const confirmState = reactive({
    isOpen: false,
    title: 'Konfirmasi Tindakan',
    message: '',
    details: '',
    variant: 'primary',
    confirmText: 'Ya, Lanjutkan',
    cancelText: 'Batal',
    requireReason: false,
    reasonLabel: 'Alasan / Catatan',
    reasonPlaceholder: 'Tuliskan alasan secara jelas...',
    minReasonLength: 3,
    reason: '',
    loading: false,
    error: '',
    onConfirm: null,
});

let activeResolver = null;

function resetState() {
    confirmState.isOpen = false;
    confirmState.title = 'Konfirmasi Tindakan';
    confirmState.message = '';
    confirmState.details = '';
    confirmState.variant = 'primary';
    confirmState.confirmText = 'Ya, Lanjutkan';
    confirmState.cancelText = 'Batal';
    confirmState.requireReason = false;
    confirmState.reasonLabel = 'Alasan / Catatan';
    confirmState.reasonPlaceholder = 'Tuliskan alasan secara jelas...';
    confirmState.minReasonLength = 3;
    confirmState.reason = '';
    confirmState.loading = false;
    confirmState.error = '';
    confirmState.onConfirm = null;
}

function settle(result) {
    const resolver = activeResolver;
    activeResolver = null;
    resetState();
    if (resolver) {
        resolver(result);
    }
}

function cancel() {
    if (!confirmState.isOpen || confirmState.loading) return;
    settle({ confirmed: false });
}

function forceCancel() {
    if (!confirmState.isOpen && !activeResolver) return;
    settle({ confirmed: false });
}

async function submit() {
    if (!confirmState.isOpen || confirmState.loading) return;

    const trimmedReason = confirmState.reason.trim();
    if (confirmState.requireReason) {
        const minLen = Number(confirmState.minReasonLength) || 3;
        if (trimmedReason.length < minLen) {
            confirmState.error = `Mohon isi alasan minimal ${minLen} karakter.`;
            return;
        }
    }

    confirmState.error = '';

    if (typeof confirmState.onConfirm === 'function') {
        confirmState.loading = true;
        try {
            await confirmState.onConfirm(trimmedReason);
            settle({ confirmed: true, reason: trimmedReason });
        } catch (err) {
            confirmState.loading = false;
            confirmState.error =
                err?.response?.data?.error?.message ||
                err?.response?.data?.message ||
                err?.message ||
                'Terjadi kesalahan saat memproses tindakan.';
        }
        return;
    }

    settle({ confirmed: true, reason: trimmedReason });
}

function confirm(options = {}) {
    if (confirmState.isOpen || activeResolver) {
        return Promise.resolve({ confirmed: false });
    }

    confirmState.title = options.title || 'Konfirmasi Tindakan';
    confirmState.message = options.message || '';
    confirmState.details = options.details || '';
    confirmState.variant = options.variant || 'primary';
    confirmState.confirmText = options.confirmText || 'Ya, Lanjutkan';
    confirmState.cancelText = options.cancelText || 'Batal';
    confirmState.requireReason = Boolean(options.requireReason);
    confirmState.reasonLabel = options.reasonLabel || 'Alasan / Catatan';
    confirmState.reasonPlaceholder = options.reasonPlaceholder || 'Tuliskan alasan secara jelas...';
    confirmState.minReasonLength = options.minReasonLength ?? 3;
    confirmState.reason = '';
    confirmState.loading = false;
    confirmState.error = '';
    confirmState.onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : null;
    confirmState.isOpen = true;

    return new Promise((resolve) => {
        activeResolver = resolve;
    });
}

export function useConfirm() {
    return {
        confirmState,
        confirm,
        submit,
        cancel,
        forceCancel,
    };
}
