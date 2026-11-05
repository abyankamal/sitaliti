import verificationData from '@/Mocks/verificationListMock.json';

/**
 * Service adapter untuk antrean verifikasi dan eksekusi workspace split-screen (Contract-First)
 */
export const verificationService = {
    /**
     * Dapatkan daftar antrean kuitansi verifikasi
     */
    async getQueue() {
        return Promise.resolve(verificationData);
    },

    /**
     * Dapatkan detail kuitansi spesifik untuk split-screen workspace
     * @param {string} receiptId Contoh: "KW-2026-06-0042"
     */
    async getDetail(receiptId) {
        const found = verificationData.find(item => item.id === receiptId);
        return Promise.resolve(found || verificationData[0]);
    },

    /**
     * Eksekusi aksi verifikasi (Lolos, Revisi Minor, Tolak Mayor)
     * POST /api/v1/verification/{receipt_id}/action
     */
    async submitAction(receiptId, actionData) {
        // actionData: { action: 'VERIFIED' | 'REVISION_MINOR' | 'REVISION_MAJOR', notes: string, checklist: [] }
        return Promise.resolve({
            success: true,
            receipt_id: receiptId,
            new_status: actionData.action,
            message: actionData.action === 'VERIFIED'
                ? 'Berkas kuitansi dinyatakan Lolos Verifikasi Akuntabel. Lembar Kendali diterbitkan.'
                : 'Catatan pengembalian berkas revisi berhasil dikirim ke pembuat SPJ.',
            timestamp: new Date().toISOString()
        });
    }
};

