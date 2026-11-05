import checklistData from '@/Mocks/checklistTemplatesMock.json';

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
        // Format dengan status awal file null
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
        // Backend Source of Truth validation check
        const totalNilai = payload.items.reduce((acc, curr) => acc + (curr.volume * curr.harga_satuan), 0);
        const sisaPagu = payload.budget_context?.rakContext?.sisa_dapat_digunakan || 0;

        const isExceedingBudget = totalNilai > sisaPagu;
        
        // Cek kelengkapan dokumen wajib
        const missingMandatoryDocs = (payload.checklist || [])
            .filter(doc => doc.required && !doc.file)
            .map(doc => doc.name);

        return Promise.resolve({
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
        });
    },

    /**
     * Simpan kuitansi baru (Simulasi POST /api/v1/receipts)
     */
    async saveReceipt(payload) {
        return Promise.resolve({
            success: true,
            receipt_id: `KW-2026-06-${Math.floor(1000 + Math.random() * 9000)}`,
            message: 'Kuitansi dan berkas pendukung berhasil disimpan sebagai draft.',
            status: 'DRAFT'
        });
    }
};

