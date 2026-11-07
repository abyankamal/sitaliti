import axios from 'axios';
import verificationData from '@/Mocks/verificationListMock.json';

const API_BASE = '/api/v1/verification';

/**
 * Service adapter untuk antrean verifikasi dan eksekusi workspace split-screen (Contract-First)
 */
export const verificationService = {
    /**
     * Dapatkan daftar antrean kuitansi verifikasi
     */
    async getQueue(filters = {}) {
        try {
            const response = await axios.get(`${API_BASE}/queue`, { params: filters });
            if (response.data && response.data.success && response.data.data.length > 0) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to verification queue mock data:', error);
        }
        return verificationData;
    },

    /**
     * Dapatkan detail kuitansi spesifik untuk split-screen workspace
     * @param {string|number} receiptId Contoh: "1" atau "KW-2026-06-0042"
     */
    async getDetail(receiptId) {
        try {
            const response = await axios.get(`${API_BASE}/${receiptId}`);
            if (response.data && response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to verification detail mock data:', error);
        }

        const found = verificationData.find(item => item.id === receiptId || item.nomor_kuitansi === receiptId);
        return found || verificationData[0];
    },

    /**
     * Eksekusi aksi verifikasi (Lolos, Revisi Minor, Tolak Mayor)
     * POST /api/v1/verification/{receipt_id}/action
     */
    async submitAction(receiptId, actionData) {
        try {
            const response = await axios.post(`${API_BASE}/${receiptId}/action`, actionData);
            if (response.data && response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to local simulation for submitAction:', error);
        }

        return {
            success: true,
            receipt_id: receiptId,
            new_status: actionData.action,
            message: actionData.action === 'VERIFIED'
                ? 'Berkas kuitansi dinyatakan Lolos Verifikasi Akuntabel. Lembar Kendali diterbitkan.'
                : 'Catatan pengembalian berkas revisi berhasil dikirim ke pembuat SPJ.',
            timestamp: new Date().toISOString()
        };
    }
};
