import axios from 'axios';
import budgetRakData from '@/Mocks/budgetRakMock.json';
import subActivitiesData from '@/Mocks/subActivitiesMock.json';

const API_BASE = '/api/v1/budget';

/**
 * Service adapter untuk data anggaran dan RAK (Contract-First)
 */
export const budgetService = {
    /**
     * Dapatkan ringkasan dashboard eksekutif
     */
    async getDashboardSummary() {
        try {
            const response = await axios.get(`${API_BASE}/summary`);
            if (response.data && response.data.success) {
                const data = response.data.data;
                return {
                    meta: data.meta || budgetRakData.meta,
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
            }
        } catch (error) {
            console.warn('Fallback to budget mock data:', error);
        }

        return {
            meta: budgetRakData.meta,
            summary: budgetRakData.summary,
            units: budgetRakData.units,
            sub_activities: [],
            monthly_allocation: budgetRakData.monthly_allocation,
            recent_activities: budgetRakData.recent_activities,
        };
    },

    /**
     * Dapatkan daftar unit organisasi
     */
    async getUnits() {
        try {
            const response = await axios.get(`${API_BASE}/units`);
            if (response.data && response.data.success) {
                return Array.isArray(response.data.data) ? response.data.data : [];
            }
        } catch (error) {
            console.warn('Fallback to unit mock data:', error);
        }
        return budgetRakData.units;
    },

    /**
     * Dapatkan subkegiatan berdasarkan unit_id terpilih
     */
    async getSubActivitiesByUnit(unitId) {
        if (!unitId) return [];
        try {
            const response = await axios.get(`${API_BASE}/sub-activities`, {
                params: { unit_id: unitId }
            });
            if (response.data && response.data.success) {
                return Array.isArray(response.data.data) ? response.data.data : [];
            }
        } catch (error) {
            console.warn('Fallback to subActivities mock data:', error);
        }
        return subActivitiesData.filter(item => item.unit_id === Number(unitId));
    },

    /**
     * Dapatkan alokasi RAK 12 bulan dan status carry-over per rekening belanja
     */
    async getRakContext(rekeningId, selectedMonth = 6) {
        try {
            const response = await axios.get(`${API_BASE}/rak-context/${rekeningId}`, {
                params: { month: selectedMonth }
            });
            if (response.data && response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to rakContext mock calculation:', error);
        }

        const activeMonth = 6;
        const targetPerBulan = 10400000;
        const realisasiBulanLalu = 8200000;
        const sisaCarryOver = (targetPerBulan - realisasiBulanLalu) * (selectedMonth > 1 ? (selectedMonth - 1) : 0);
        const paguMurniBulan = targetPerBulan;
        const paguEligibleTotal = paguMurniBulan + sisaCarryOver;
        const paguTerkunciBulan = 2500000;
        const paguSisaBulan = paguEligibleTotal - paguTerkunciBulan;

        return {
            rekening_id: rekeningId,
            selected_month: selectedMonth,
            active_system_month: activeMonth,
            is_future_month: selectedMonth > activeMonth,
            pagu_murni_bulan: paguMurniBulan,
            carry_over_lalu: sisaCarryOver,
            total_tersedia: paguEligibleTotal,
            terkunci_komitmen: paguTerkunciBulan,
            sisa_dapat_digunakan: selectedMonth > activeMonth ? 0 : paguSisaBulan,
            status_lock: selectedMonth > activeMonth ? 'LOCKED_FUTURE' : 'AVAILABLE'
        };
    }
};
