import axios from 'axios';

/**
 * Service adapter untuk pengelolaan Penugasan SPPD Pra-Kuitansi & Konversi SPJ.
 */
export const sppdAssignmentService = {
    async list(params = {}) {
        const response = await axios.get('/api/v1/sppd-assignments', { params });
        return response.data?.data ?? { data: [], current_page: 1, last_page: 1, total: 0 };
    },

    async get(id) {
        const response = await axios.get(`/api/v1/sppd-assignments/${id}`);
        return response.data?.data;
    },

    async create(payload) {
        const response = await axios.post('/api/v1/sppd-assignments', payload);
        return response.data;
    },

    async update(id, payload) {
        const response = await axios.put(`/api/v1/sppd-assignments/${id}`, payload);
        return response.data;
    },

    async remove(id) {
        const response = await axios.delete(`/api/v1/sppd-assignments/${id}`);
        return response.data;
    },

    async checkConflicts(payload) {
        const response = await axios.post('/api/v1/sppd-assignments/check-conflicts', payload);
        return response.data?.data ?? { has_conflicts: false, conflicts: [] };
    },

    async convertToKuitansi(id, payload) {
        const response = await axios.post(`/api/v1/sppd-assignments/${id}/convert-kuitansi`, payload);
        return response.data;
    },

    async linkKuitansi(id, kuitansiId) {
        const response = await axios.post(`/api/v1/sppd-assignments/${id}/link-kuitansi`, {
            kuitansi_id: kuitansiId,
        });
        return response.data;
    },
};
