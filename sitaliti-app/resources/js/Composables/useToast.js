import { ref } from 'vue';

const MAX_TOASTS = 4;
const DEFAULT_DURATION = 4500;
const ERROR_DURATION = 6000;

const toasts = ref([]);
const timers = new Map();
const consumedFlashIds = new Set();

let sequence = 0;

function clearTimer(id) {
    const entry = timers.get(id);
    if (entry?.timerId) {
        clearTimeout(entry.timerId);
    }
    timers.delete(id);
}

function startTimer(id, duration) {
    if (!duration || duration <= 0) return;

    const existing = timers.get(id);
    if (existing?.timerId) {
        clearTimeout(existing.timerId);
    }

    const timerId = setTimeout(() => {
        dismiss(id);
    }, duration);

    timers.set(id, {
        timerId,
        remaining: duration,
        startedAt: Date.now(),
    });
}

function pause(id) {
    const entry = timers.get(id);
    if (!entry || !entry.timerId) return;

    clearTimeout(entry.timerId);
    const elapsed = Date.now() - entry.startedAt;
    timers.set(id, {
        timerId: null,
        remaining: Math.max(0, entry.remaining - elapsed),
        startedAt: entry.startedAt,
    });
}

function resume(id) {
    const entry = timers.get(id);
    if (!entry || entry.timerId !== null) return;

    if (entry.remaining <= 0) {
        dismiss(id);
        return;
    }

    startTimer(id, entry.remaining);
}

function dismiss(id) {
    clearTimer(id);
    toasts.value = toasts.value.filter((item) => item.id !== id);
}

function clear() {
    for (const id of timers.keys()) {
        clearTimer(id);
    }
    toasts.value = [];
}

function normalizeArgs(type, messageOrOptions, maybeMessage) {
    if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
        return {
            type,
            title: messageOrOptions.title || '',
            message: messageOrOptions.message || '',
            duration: messageOrOptions.duration,
        };
    }

    if (typeof maybeMessage === 'string') {
        return {
            type,
            title: messageOrOptions || '',
            message: maybeMessage,
            duration: undefined,
        };
    }

    return {
        type,
        title: '',
        message: messageOrOptions || '',
        duration: undefined,
    };
}

function show(options) {
    const type = options.type || 'info';
    const message = String(options.message || '').trim();
    const title = String(options.title || '').trim();

    if (!message && !title) return null;

    const duration =
        options.duration !== undefined
            ? options.duration
            : type === 'error'
              ? ERROR_DURATION
              : DEFAULT_DURATION;

    while (toasts.value.length >= MAX_TOASTS) {
        const oldest = toasts.value[0];
        if (oldest) {
            dismiss(oldest.id);
        } else {
            break;
        }
    }

    const id = `toast-${Date.now()}-${++sequence}`;
    toasts.value.push({
        id,
        type,
        title,
        message,
    });

    startTimer(id, duration);
    return id;
}

function consumeFlash(flash) {
    if (!flash || typeof flash !== 'object') return;

    const mapping = [
        ['success', 'success'],
        ['error', 'error'],
        ['warning', 'warning'],
        ['info', 'info'],
        ['status', 'info'],
    ];

    for (const [key, toastType] of mapping) {
        const item = flash[key];
        if (!item || !item.id || !item.message) continue;
        if (consumedFlashIds.has(item.id)) continue;

        consumedFlashIds.add(item.id);
        show({
            type: toastType,
            message: item.message,
        });
    }
}

export function useToast() {
    return {
        toasts,
        show,
        dismiss,
        clear,
        pause,
        resume,
        consumeFlash,
        success: (msgOrOpts, maybeMsg) => show(normalizeArgs('success', msgOrOpts, maybeMsg)),
        error: (msgOrOpts, maybeMsg) => show(normalizeArgs('error', msgOrOpts, maybeMsg)),
        warning: (msgOrOpts, maybeMsg) => show(normalizeArgs('warning', msgOrOpts, maybeMsg)),
        info: (msgOrOpts, maybeMsg) => show(normalizeArgs('info', msgOrOpts, maybeMsg)),
    };
}
