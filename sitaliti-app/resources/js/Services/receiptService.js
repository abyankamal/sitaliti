import axios from 'axios';
import checklistData from '@/Mocks/checklistTemplatesMock.json';

const API_BASE = '/api/v1/receipts';

/**
 * Service adapter untuk pengelolaan checklist dokumen dan draft kuitansi
 */
export const receiptService = {
    /**
     * Dapatkan daftar template checklist dokumen wajib & pendukung berdasarkan kategori (B.1 - B.6)
     * @param {string} categoryCode Contoh: 'B.1'
     */
    async getChecklistByCategory(categoryCode) {
        const list = checklistData[categoryCode] || checklistData['B.1'];
        const formatted = list.map(item => ({
            ...item,
            file: null,
            file_name: null,
            file_size: null,
            upload_date: null,
        }));
        return Promise.resolve(formatted);
    },

    /**
     * Simulasi validasi draft kuitansi ke backend (Contract-First)
     */
    async validateDraft(payload) {
        try {
            const response = await axios.post(`${API_BASE}/validate-draft`, payload);
            if (response.data && response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.warn('Fallback to local draft validation:', error);
        }

        // Fallback local calculation
        const totalNilai = (payload.items || []).reduce((acc, curr) => acc + (curr.volume * curr.harga_satuan), 0);
        const sisaPagu = payload.budget_context?.rakContext?.sisa_dapat_digunakan || 0;
        const isExceedingBudget = totalNilai > sisaPagu;
        const missingMandatoryDocs = (payload.checklist || [])
            .filter(doc => doc.required && !doc.file)
            .map(doc => doc.name);

        return {
            success: !isExceedingBudget && missingMandatoryDocs.length === 0,
            total_belanja: totalNilai,
            is_exceeding_budget: isExceedingBudget,
            missing_mandatory_docs: missingMandatoryDocs,
            calculated_tax: {
                ppn: payload.tax_settings?.apply_ppn ? Math.round(totalNilai * 0.11) : 0,
                pph21: payload.tax_settings?.pph21_rate ? Math.round(totalNilai * (payload.tax_settings.pph21_rate / 100)) : 0,
                pph22: payload.tax_settings?.apply_pph22 ? Math.round(totalNilai * 0.015) : 0,
                pph23: payload.tax_settings?.apply_pph23 ? Math.round(totalNilai * 0.02) : 0,
            }
        };
    },

    /**
     * Simpan kuitansi draft ke backend database
     */
    async storeReceipt(formData) {
        const response = await axios.post(API_BASE, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            }
        });
        return response.data;
    },

    /**
     * Submit kuitansi ke PPTK
     */
    async submitReceipt(receiptId) {
        const response = await axios.post(`${API_BASE}/${receiptId}/submit`);
        return response.data;
    },

    /**
     * Persetujuan kuitansi oleh PPTK
     */
    async approveByPptk(receiptId, catatan = null) {
        const response = await axios.post(`${API_BASE}/${receiptId}/pptk-approve`, { catatan });
        return response.data;
    },

    /**
     * Penolakan kuitansi oleh PPTK (mengembalikan ke DRAFT & melepaskan pagu)
     */
    async rejectByPptk(receiptId, alasan) {
        const response = await axios.post(`${API_BASE}/${receiptId}/pptk-reject`, { alasan });
        return response.data;
    },

    /**
     * Otorisasi kuitansi oleh Lurah (menuju MENUNGGU_VERIFIKASI)
     */
    async approveByLurah(receiptId, catatan = null) {
        const response = await axios.post(`${API_BASE}/${receiptId}/lurah-approve`, { catatan });
        return response.data;
    },

    /**
     * Penolakan kuitansi oleh Lurah (mengembalikan ke DRAFT & melepaskan pagu)
     */
    async rejectByLurah(receiptId, alasan) {
        const response = await axios.post(`${API_BASE}/${receiptId}/lurah-reject`, { alasan });
        return response.data;
    },

    /**
     * Mengambil riwayat mutasi audit trail kuitansi
     */
    async getHistory(receiptId) {
        const response = await axios.get(`${API_BASE}/${receiptId}/history`);
        return response.data?.data || [];
    }
};
