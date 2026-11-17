/**
 * Format angka ke format Rupiah standar Indonesia
 * @param {number|string} value
 * @param {boolean} withPrefix
 * @returns {string} Contoh: "Rp 14.252.126.458" atau "14.252.126.458"
 */
export function formatRupiah(value, withPrefix = true) {
    if (value === null || value === undefined || isNaN(Number(value))) {
        return withPrefix ? 'Rp 0' : '0';
    }

    const num = Math.round(Number(value));
    const formatted = new Intl.NumberFormat('id-ID').format(num);

    return withPrefix ? `Rp ${formatted}` : formatted;
}

/**
 * Parse string nominal (e.g. "Rp 1.500.000") kembali menjadi number murni
 * @param {string|number} value
 * @returns {number}
 */
export function parseRupiah(value) {
    if (typeof value === 'number') return value;
    if (!value) return 0;
    const cleanStr = value.toString().replace(/[^0-9]/g, '');
    return cleanStr ? parseInt(cleanStr, 10) : 0;
}

/**
 * Parse string nominal desimal dari API (e.g. "1000000.00") menjadi number murni.
 * @param {string|number|null|undefined} value
 * @returns {number}
 */
export function parseDecimalAmount(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    if (value === null || value === undefined || value === '') return 0;
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
}

