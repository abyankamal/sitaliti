import axios from 'axios';

const API_BASE = '/api/v1/verification';

/**
 * Service adapter untuk antrean verifikasi dan eksekusi workspace split-screen.
 */
export const verificationService = {
    /**
     * Dapatkan daftar antrean kuitansi verifikasi
     */
    async getQueue(filters = {}) {
        const response = await axios.get(`${API_BASE}/queue`, { params: filters });
        const data = response.data?.data;
        return Array.isArray(data) ? data : [];
    },

    /**
     * Dapatkan detail kuitansi spesifik untuk split-screen workspace
     * @param {string|number} receiptId Contoh: "1" atau "KW-2026-06-0042"
     */
    async getDetail(receiptId) {
        const response = await axios.get(`${API_BASE}/${receiptId}`);
        return response.data?.data;
    },

    /**
     * Eksekusi aksi verifikasi (Lolos, Revisi Minor, Tolak Mayor)
     * POST /api/v1/verification/{receipt_id}/action
     */
    async submitAction(receiptId, actionData) {
        const response = await axios.post(`${API_BASE}/${receiptId}/action`, actionData);
        return response.data?.data ?? response.data;
    },
};
