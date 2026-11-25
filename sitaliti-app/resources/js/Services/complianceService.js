import axios from 'axios';

const API_BASE = '/api/v1/compliance';

export const complianceService = {
    async getOverview(tahunAnggaran) {
        const response = await axios.get(API_BASE, {
            params: { tahun_anggaran: tahunAnggaran },
        });
        return response.data?.data || {};
    },

    async updateSetting(key, value) {
        const response = await axios.post(`${API_BASE}/settings`, { key, value });
        return response.data;
    },

    async upsertTaxRate(payload) {
        const response = await axios.post(`${API_BASE}/tax-rates`, payload);
        return response.data;
    },

    async upsertSbmItem(payload) {
        const response = await axios.post(`${API_BASE}/sbm-items`, payload);
        return response.data;
    },
};
