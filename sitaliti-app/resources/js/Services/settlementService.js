import axios from 'axios';
import bundlesMockData from '@/Mocks/bundlesMock.json';

const API_BASE = '/api/v1/bundles';

export const settlementService = {
    /**
     * Dapatkan daftar seluruh bundel SPJ
     */
    async getBundles(filters = {}) {
        try {
            const response = await axios.get(API_BASE, { params: filters });
            if (response.data && response.data.success) {
                return Array.isArray(response.data.data) ? response.data.data : [];
            }
        } catch (error) {
            console.warn('Fallback to bundles mock data:', error);
        }
        return bundlesMockData;
    },

    /**
     * Dapatkan detail satu bundel SPJ
     */
    async getBundleDetail(bundleId) {
        try {
            const response = await axios.get(`${API_BASE}/${bundleId}`);
            if (response.data && response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to bundle detail mock data:', error);
        }
        return bundlesMockData.find(b => b.id === Number(bundleId)) || bundlesMockData[0];
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
     * Catat SP2D definitif dari SIPD-RI (Settle Budget)
     */
    async recordSp2d(bundleId, sp2dPayload) {
        const response = await axios.post(`${API_BASE}/${bundleId}/sp2d`, sp2dPayload);
        return response.data;
    }
};
