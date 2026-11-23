import axios from 'axios';

/**
 * Service adapter untuk Master Rekap Belanja Operasi (CRUD pos rekap & pemetaan rekening 5.1.*)
 * serta Laporan Rekap Belanja Operasi.
 */
export const rekapBelanjaOperasiService = {
    async getMasterList(params = {}) {
        const response = await axios.get('/api/v1/master/rekap-belanja-operasi', { params });
        return response.data?.data ?? [];
    },

    async getRekeningOptions(params = {}) {
        const response = await axios.get('/api/v1/master/rekap-belanja-operasi/rekening-options', { params });
        return response.data?.data ?? [];
    },

    async createMasterPos(payload) {
        const response = await axios.post('/api/v1/master/rekap-belanja-operasi', payload);
        return response.data;
    },

    async updateMasterPos(id, payload) {
        const response = await axios.put(`/api/v1/master/rekap-belanja-operasi/${id}`, payload);
        return response.data;
    },

    async deleteMasterPos(id) {
        const response = await axios.delete(`/api/v1/master/rekap-belanja-operasi/${id}`);
        return response.data;
    },

    async getReport(params = {}) {
        const response = await axios.get('/api/v1/rekap-belanja-operasi', { params });
        return response.data?.data ?? { summary: {}, rows: [], filters: {} };
    },

    async getDrilldown(params = {}) {
        const response = await axios.get('/api/v1/rekap-belanja-operasi/drilldown', { params });
        return response.data?.data ?? { rekenings: [], kuitansi: [] };
    },

    getExportUrl(params = {}, format = 'xlsx') {
        const searchParams = new URLSearchParams();
        searchParams.set('format', format);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                searchParams.set(key, String(value));
            }
        });

        return `/api/v1/rekap-belanja-operasi/export?${searchParams.toString()}`;
    },
};
