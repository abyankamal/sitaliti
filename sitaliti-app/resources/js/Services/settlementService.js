import axios from 'axios';

const API_BASE = '/api/v1/bundles';

export const settlementService = {
    /**
     * Dapatkan daftar seluruh bundel SPJ
     */
    async getBundles(filters = {}) {
        const response = await axios.get(API_BASE, { params: filters });
        const data = response.data?.data;
        return Array.isArray(data) ? data : [];
    },

    /**
     * Dapatkan detail satu bundel SPJ
     */
    async getBundleDetail(bundleId) {
        const response = await axios.get(`${API_BASE}/${bundleId}`);
        return response.data?.data;
    },

    /**
     * Buat bundel SPJ baru dari kumpulan kuitansi lolos verifikasi
     */
    async createBundle(payload) {
        const response = await axios.post(API_BASE, payload);
        return response.data;
    },

    /**
     * Keluarkan kuitansi dari bundel (Unbundle)
     */
    async unbundleReceipt(bundleId, kuitansiId, reason = '') {
        const response = await axios.post(`${API_BASE}/${bundleId}/unbundle/${kuitansiId}`, { reason });
        return response.data;
    },

    /**
     * Ajukan bundel DRAFT ke PPK untuk pengesahan
     */
    async submitPpk(bundleId) {
        const response = await axios.post(`${API_BASE}/${bundleId}/submit-ppk`);
        return response.data;
    },

    /**
     * Pengesahan bundel oleh PPK (Verifikator) sebelum pencatatan SP2D
     */
    async approvePpk(bundleId) {
        const response = await axios.post(`${API_BASE}/${bundleId}/approve-ppk`);
        return response.data;
    },

    /**
     * Catat SP2D definitif dari SIPD-RI (Settle Budget)
     */
    async recordSp2d(bundleId, sp2dPayload) {
        const response = await axios.post(`${API_BASE}/${bundleId}/sp2d`, sp2dPayload);
        return response.data;
    },
};
