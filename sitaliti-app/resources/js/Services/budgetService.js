import budgetRakData from '@/Mocks/budgetRakMock.json';
import subActivitiesData from '@/Mocks/subActivitiesMock.json';

/**
 * Service adapter untuk data anggaran dan RAK (Contract-First)
 */
export const budgetService = {
    /**
     * Dapatkan ringkasan dashboard eksekutif
     */
    async getDashboardSummary() {
        return Promise.resolve({
            meta: budgetRakData.meta,
            summary: budgetRakData.summary,
            units: budgetRakData.units,
            monthly_allocation: budgetRakData.monthly_allocation,
            recent_activities: budgetRakData.recent_activities,
        });
    },

    /**
     * Dapatkan daftar 6 unit organisasi
     */
    async getUnits() {
        return Promise.resolve(budgetRakData.units);
    },

    /**
     * Dapatkan subkegiatan berdasarkan unit_id terpilih
     */
    async getSubActivitiesByUnit(unitId) {
        if (!unitId) return Promise.resolve([]);
        const filtered = subActivitiesData.filter(item => item.unit_id === Number(unitId));
        return Promise.resolve(filtered);
    },

    /**
     * Dapatkan alokasi RAK 12 bulan dan status carry-over per rekening belanja
     */
    async getRakContext(rekeningId, selectedMonth = 6) {
        // Simulasi alokasi bulanan rekening spesifik
        const activeMonth = 6; // Juni (bulan berjalan simulasi)
        
        // Pagu bulanan tipikal (contoh total pagu ~125jt dibagi 12 bulan)
        const targetPerBulan = 10400000;
        const realisasiBulanLalu = 8200000;
        const sisaCarryOver = (targetPerBulan - realisasiBulanLalu) * (selectedMonth > 1 ? (selectedMonth - 1) : 0);

        const paguMurniBulan = targetPerBulan;
        const paguEligibleTotal = paguMurniBulan + sisaCarryOver;
        const paguTerkunciBulan = 2500000;
        const paguSisaBulan = paguEligibleTotal - paguTerkunciBulan;

        return Promise.resolve({
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
        });
    }
};


