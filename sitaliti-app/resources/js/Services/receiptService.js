import axios from 'axios';

const API_BASE = '/api/v1/receipts';

/**
 * Service adapter untuk pengelolaan checklist dokumen, validasi pajak, dan siklus hidup kuitansi.
 */
export const receiptService = {
    /**
     * Dapatkan daftar template checklist dokumen wajib & pendukung dari DB berdasarkan kategori (B.1 - B.6).
     *
     * @param {string} categoryCode Contoh: 'B.1' atau 'B1_ATK'
     */
    async getChecklistByCategory(categoryCode) {
        const response = await axios.get(`/api/v1/master/checklist/${encodeURIComponent(categoryCode || 'B.1')}`);
        const list = response.data?.data || [];

        return list.map((item) => ({
            id: item.id,
            code: item.kode_item,
            name: item.nama_item,
            description: item.deskripsi,
            required: Boolean(item.is_required),
            file: null,
            file_name: null,
            file_size: null,
            upload_date: null,
        }));
    },

    /**
     * Simulasi validasi draft kuitansi & kalkulasi pajak resmi ke backend.
     */
    async validateDraft(payload) {
        const response = await axios.post(`${API_BASE}/validate-draft`, payload);
        return response.data?.data;
    },

    /**
     * Simpan kuitansi draft ke backend database.
     */
    async storeReceipt(formData) {
        const response = await axios.post(API_BASE, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        return response.data;
    },

    /**
     * Perbarui kuitansi (DRAFT, REVISI_MINOR, REVISI_MAYOR).
     */
    async updateReceipt(receiptId, formData) {
        const response = await axios.post(`${API_BASE}/${receiptId}/update`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        return response.data;
    },

    /**
     * Hapus satu lampiran kuitansi.
     */
    async deleteAttachment(receiptId, attachmentId) {
        const response = await axios.delete(`${API_BASE}/${receiptId}/attachments/${attachmentId}`);
        return response.data;
    },

    /**
     * Kirim ulang kuitansi REVISI_MINOR ke Verifikator.
     */
    async resubmitRevision(receiptId) {
        const response = await axios.post(`${API_BASE}/${receiptId}/resubmit-revision`);
        return response.data;
    },

    /**
     * Submit kuitansi ke PPTK.
     */
    async submitReceipt(receiptId) {
        const response = await axios.post(`${API_BASE}/${receiptId}/submit`);
        return response.data;
    },

    /**
     * Tarik kembali kuitansi ke DRAFT oleh Operator pembuatnya (Self-Recall).
     */
    async recallReceipt(receiptId, alasan = null) {
        const response = await axios.post(`${API_BASE}/${receiptId}/recall`, { alasan });
        return response.data;
    },

    /**
     * Persetujuan kuitansi oleh PPTK.
     */
    async approveByPptk(receiptId, catatan = null) {
        const response = await axios.post(`${API_BASE}/${receiptId}/pptk-approve`, { catatan });
        return response.data;
    },

    /**
     * Penolakan kuitansi oleh PPTK (mengembalikan ke DRAFT & melepaskan pagu).
     */
    async rejectByPptk(receiptId, alasan) {
        const response = await axios.post(`${API_BASE}/${receiptId}/pptk-reject`, { alasan });
        return response.data;
    },

    /**
     * Mengambil riwayat mutasi audit trail kuitansi.
     */
    async getHistory(receiptId) {
        const response = await axios.get(`${API_BASE}/${receiptId}/history`);
        return response.data?.data || [];
    },

    /**
     * Bangun URL unduhan CSV rekapitulasi kuitansi.
     */
    getExportUrl(filters = {}) {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.append(key, value);
            }
        });
        const qs = params.toString();
        return `${API_BASE}/export${qs ? `?${qs}` : ''}`;
    },

    /**
     * Unduh laporan CSV rekapitulasi kuitansi.
     */
    exportReceipts(filters = {}) {
        window.location.href = this.getExportUrl(filters);
    },
};
