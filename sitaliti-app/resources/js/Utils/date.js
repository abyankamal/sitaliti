/**
 * Format tanggal standar Indonesia
 * @param {string|Date} date
 * @param {boolean} withTime
 * @returns {string} Contoh: "5 November 2026"
 */
export function formatTanggalIndo(date, withTime = false) {
    if (!date) return '-';
    const d = new Date(date);
    if (isNaN(d.getTime())) return '-';

    const options = {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...(withTime && {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        })
    };

    return new Intl.DateTimeFormat('id-ID', options).format(d);
}

export const NAMA_BULAN = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

