export const WORKFLOW_STATUS = {
    DRAFT: 'DRAFT',
    MENUNGGU_PPTK: 'MENUNGGU_PPTK',
    MENUNGGU_LURAH: 'MENUNGGU_LURAH',
    MENUNGGU_VERIFIKASI: 'MENUNGGU_VERIFIKASI',
    REVISI_MINOR: 'REVISI_MINOR',
    REVISI_MAYOR: 'REVISI_MAYOR',
    LOLOS_VERIFIKASI: 'LOLOS_VERIFIKASI',
    DIBUNDEL: 'DIBUNDEL',
    SELESAI_SP2D: 'SELESAI_SP2D',

    // Alias / legacy UI compatibility
    SUBMITTED: 'SUBMITTED',             // Diajukan ke PPK / Verifikator
    UNDER_REVIEW: 'UNDER_REVIEW',       // Sedang Diverifikasi di Split-Screen
    REVISION_MINOR: 'REVISION_MINOR',   // Dikembalikan untuk revisi minor kelengkapan
    REVISION_MAJOR: 'REVISION_MAJOR',   // Ditolak / revisi substansi mayor
    VERIFIED: 'VERIFIED',               // Lolos Verifikasi, Terbit Lembar Kendali QR
    BUNDLED: 'BUNDLED',                 // Telah masuk bundel SPP (GU/TU/LS)
    TERVERIFIKASI: 'TERVERIFIKASI',
    SYNCED_SIPD: 'SYNCED_SIPD',
};

export const STATUS_LABELS = {
    [WORKFLOW_STATUS.DRAFT]: 'Konsep (Draft)',
    [WORKFLOW_STATUS.MENUNGGU_PPTK]: 'Menunggu Persetujuan PPTK',
    [WORKFLOW_STATUS.MENUNGGU_LURAH]: 'Menunggu Otorisasi Lurah',
    [WORKFLOW_STATUS.MENUNGGU_VERIFIKASI]: 'Menunggu Verifikasi',
    [WORKFLOW_STATUS.REVISI_MINOR]: 'Perlu Revisi Minor',
    [WORKFLOW_STATUS.REVISI_MAYOR]: 'Ditolak (Revisi Mayor)',
    [WORKFLOW_STATUS.LOLOS_VERIFIKASI]: 'Terverifikasi Akuntabel',
    [WORKFLOW_STATUS.DIBUNDEL]: 'Dalam Bundel SPJ',
    [WORKFLOW_STATUS.SELESAI_SP2D]: 'Selesai SP2D',

    // Legacy labels
    [WORKFLOW_STATUS.SUBMITTED]: 'Diajukan',
    [WORKFLOW_STATUS.UNDER_REVIEW]: 'Sedang Diverifikasi',
    [WORKFLOW_STATUS.REVISION_MINOR]: 'Perlu Revisi Minor',
    [WORKFLOW_STATUS.REVISION_MAJOR]: 'Ditolak (Revisi Mayor)',
    [WORKFLOW_STATUS.VERIFIED]: 'Terverifikasi Akuntabel',
    [WORKFLOW_STATUS.BUNDLED]: 'Dalam Bundel SPP',
    [WORKFLOW_STATUS.TERVERIFIKASI]: 'Lolos Verifikasi',
    [WORKFLOW_STATUS.SYNCED_SIPD]: 'Cair (SIPD-RI)',
};

export const STATUS_VARIANTS = {
    [WORKFLOW_STATUS.DRAFT]: 'neutral',
    [WORKFLOW_STATUS.MENUNGGU_PPTK]: 'info',
    [WORKFLOW_STATUS.MENUNGGU_LURAH]: 'warning',
    [WORKFLOW_STATUS.MENUNGGU_VERIFIKASI]: 'warning',
    [WORKFLOW_STATUS.REVISI_MINOR]: 'warning',
    [WORKFLOW_STATUS.REVISI_MAYOR]: 'danger',
    [WORKFLOW_STATUS.LOLOS_VERIFIKASI]: 'success',
    [WORKFLOW_STATUS.DIBUNDEL]: 'primary',
    [WORKFLOW_STATUS.SELESAI_SP2D]: 'success',

    // Legacy variants
    [WORKFLOW_STATUS.SUBMITTED]: 'info',
    [WORKFLOW_STATUS.UNDER_REVIEW]: 'warning',
    [WORKFLOW_STATUS.REVISION_MINOR]: 'warning',
    [WORKFLOW_STATUS.REVISION_MAJOR]: 'danger',
    [WORKFLOW_STATUS.VERIFIED]: 'success',
    [WORKFLOW_STATUS.BUNDLED]: 'primary',
    [WORKFLOW_STATUS.TERVERIFIKASI]: 'success',
    [WORKFLOW_STATUS.SYNCED_SIPD]: 'success',
};

export function getStatusMeta(status) {
    return {
        label: STATUS_LABELS[status] || status || '-',
        variant: STATUS_VARIANTS[status] || 'neutral',
    };
}
