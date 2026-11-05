export const WORKFLOW_STATUS = {
    DRAFT: 'DRAFT',
    SUBMITTED: 'SUBMITTED',             // Diajukan ke PPK / Verifikator
    UNDER_REVIEW: 'UNDER_REVIEW',       // Sedang Diverifikasi di Split-Screen
    REVISION_MINOR: 'REVISION_MINOR',   // Dikembalikan untuk revisi minor kelengkapan
    REVISION_MAJOR: 'REVISION_MAJOR',   // Ditolak / revisi substansi mayor
    VERIFIED: 'VERIFIED',               // Lolos Verifikasi, Terbit Lembar Kendali QR
    BUNDLED: 'BUNDLED',                 // Telah masuk bundel SPP (GU/TU/LS)
};

export const STATUS_LABELS = {
    [WORKFLOW_STATUS.DRAFT]: 'Konsep (Draft)',
    [WORKFLOW_STATUS.SUBMITTED]: 'Diajukan',
    [WORKFLOW_STATUS.UNDER_REVIEW]: 'Sedang Diverifikasi',
    [WORKFLOW_STATUS.REVISION_MINOR]: 'Perlu Revisi Minor',
    [WORKFLOW_STATUS.REVISION_MAJOR]: 'Ditolak (Revisi Mayor)',
    [WORKFLOW_STATUS.VERIFIED]: 'Terverifikasi Akuntabel',
    [WORKFLOW_STATUS.BUNDLED]: 'Dalam Bundel SPP',
};

export const STATUS_VARIANTS = {
    [WORKFLOW_STATUS.DRAFT]: 'neutral',
    [WORKFLOW_STATUS.SUBMITTED]: 'info',
    [WORKFLOW_STATUS.UNDER_REVIEW]: 'warning',
    [WORKFLOW_STATUS.REVISION_MINOR]: 'warning',
    [WORKFLOW_STATUS.REVISION_MAJOR]: 'danger',
    [WORKFLOW_STATUS.VERIFIED]: 'success',
    [WORKFLOW_STATUS.BUNDLED]: 'primary',
};

