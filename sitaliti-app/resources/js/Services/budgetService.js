import axios from 'axios';

const API_BASE = '/api/v1/budget';

/**
 * Service adapter untuk data anggaran dan RAK.
 */
export const budgetService = {
    /**
     * Dapatkan ringkasan dashboard eksekutif
     */
    async getDashboardSummary() {
        const response = await axios.get(`${API_BASE}/summary`);
        const data = response.data?.data || {};

        return {
            meta: data.meta || {},
            summary: data.summary || data,
            queue_counts: data.queue_counts || data.summary?.queue_counts || {
                menunggu_pptk: data.pending_pptk_count || 0,
                menunggu_lurah: data.pending_lurah_count || 0,
                menunggu_verifikasi: data.pending_verification_count || 0,
                revisi_minor: 0,
                revisi_mayor: 0,
                lolos_verifikasi: data.verified_count || 0,
            },
            units: Array.isArray(data.units) ? data.units : (data.units ? [data.units] : []),
            sub_activities: Array.isArray(data.sub_activities) ? data.sub_activities : [],
            monthly_allocation: Array.isArray(data.monthly_allocation) ? data.monthly_allocation : [],
            recent_activities: Array.isArray(data.recent_activities) ? data.recent_activities : [],
        };
    },

    /**
     * Dapatkan daftar unit organisasi
     */
    async getUnits() {
        const response = await axios.get(`${API_BASE}/units`);
        const data = response.data?.data;
        return Array.isArray(data) ? data : [];
    },

    /**
     * Dapatkan subkegiatan berdasarkan unit_id terpilih
     */
    async getSubActivitiesByUnit(unitId) {
        if (!unitId) return [];
        const response = await axios.get(`${API_BASE}/sub-activities`, {
            params: { unit_id: unitId },
        });
        const data = response.data?.data;
        return Array.isArray(data) ? data : [];
    },

    /**
     * Dapatkan alokasi RAK 12 bulan dan status carry-over per rekening belanja
     */
    async getRakContext(rekeningId, selectedMonth = 6) {
        const response = await axios.get(`${API_BASE}/rak-context/${rekeningId}`, {
            params: { month: selectedMonth },
        });
        return response.data?.data;
    },
};
