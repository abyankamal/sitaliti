import axios from 'axios';

/**
 * Service adapter untuk pengelolaan Master Pegawai, cek bentrok jadwal SPPD, dan Laporan Rekap SPPD.
 */
export const sppdService = {
    /**
     * Ambil daftar opsi pegawai aktif untuk dropdown SPPD dan filter laporan.
     */
    async getPegawaiOptions(params = {}) {
        const response = await axios.get('/api/v1/pegawai/options', { params });
        return response.data?.data ?? [];
    },

    /**
     * Ambil daftar Master Pegawai terpaginasi.
     */
    async getPegawaiList(params = {}) {
        const response = await axios.get('/api/v1/pegawai', { params });
        return response.data?.data ?? { data: [], current_page: 1, last_page: 1, total: 0 };
    },

    /**
     * Tambah data pegawai baru.
     */
    async createPegawai(payload) {
        const response = await axios.post('/api/v1/pegawai', payload);
        return response.data;
    },

    /**
     * Perbarui data pegawai.
     */
    async updatePegawai(id, payload) {
        const response = await axios.put(`/api/v1/pegawai/${id}`, payload);
        return response.data;
    },

    /**
     * Hapus data pegawai (jika belum pernah dipakai di SPPD).
     */
    async deletePegawai(id) {
        const response = await axios.delete(`/api/v1/pegawai/${id}`);
        return response.data;
    },

    /**
     * Cek potensi bentrok jadwal SPPD secara real-time sebelum simpan/submit.
     */
    async checkScheduleConflict(payload) {
        const response = await axios.post('/api/v1/sppd/check-conflict', payload);
        return response.data?.data ?? { has_conflict: false, conflicts: [] };
    },

    /**
     * Ambil data rekap laporan SPPD terpaginasi beserta KPI.
     */
    async getSppdReport(params = {}) {
        const response = await axios.get('/api/v1/sppd/report', { params });
        return response.data?.data ?? {};
    },

    /**
     * Bangun URL ekspor laporan SPPD (xlsx | csv | pdf).
     */
    getSppdExportUrl(params = {}, format = 'xlsx') {
        const searchParams = new URLSearchParams();
        searchParams.set('format', format);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                searchParams.set(key, String(value));
            }
        });

        return `/api/v1/sppd/report/export?${searchParams.toString()}`;
    },
};
