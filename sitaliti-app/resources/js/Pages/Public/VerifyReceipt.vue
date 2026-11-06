<script setup>
import { Head, Link } from '@inertiajs/vue3';
import GovBadge from '@/Components/UI/GovBadge.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

const props = defineProps({
    verification: {
        type: Object,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <Head title="Verifikasi Keabsahan Dokumen SPJ - SI-TALITI Garut" />

    <div class="min-h-screen bg-slate-100 flex flex-col justify-between font-sans text-slate-800">
        <!-- Header Resmi Pemerintah -->
        <header class="bg-gov-navy-950 text-white shadow-md border-b-2 border-amber-500">
            <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center font-black text-gov-navy-950 text-xs shadow-inner">
                        GARUT
                    </div>
                    <div>
                        <h1 class="text-xs font-bold uppercase tracking-wider text-amber-400">Pemerintah Kabupaten Garut</h1>
                        <p class="text-sm font-black text-white">Kecamatan Tarogong Kidul</p>
                    </div>
                </div>
                <span class="text-[10px] bg-gov-navy-800 text-slate-300 px-2 py-1 rounded font-mono border border-gov-navy-700">
                    SI-TALITI v2.0
                </span>
            </div>
        </header>

        <!-- Konten Utama Verifikasi Publik -->
        <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 my-auto">
            <!-- Dokumen Ditemukan & Valid -->
            <div v-if="verification" class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
                <!-- Status Banner -->
                <div
                    class="p-5 text-center border-b"
                    :class="verification.is_valid ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'"
                >
                    <div
                        class="w-14 h-14 mx-auto mb-3 rounded-full flex items-center justify-center shadow-xs"
                        :class="verification.is_valid ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white'"
                    >
                        <svg v-if="verification.is_valid" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>

                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Status Keabsahan Dokumen</span>
                    <h2 class="text-lg font-black text-slate-900 mt-0.5">
                        {{ verification.status_label }}
                    </h2>
                    <p class="text-xs text-slate-600 mt-1 font-mono">
                        {{ verification.nomor_register }}
                    </p>
                </div>

                <!-- Detail Verifikasi -->
                <div class="p-6 space-y-5 text-xs">
                    <!-- Unit & Subkegiatan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <span class="text-slate-400 block text-[11px]">Unit Kerja Pengaju</span>
                            <span class="font-bold text-slate-900 text-sm">{{ verification.unit_kerja }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Kode & Nama Rekening</span>
                            <span class="font-semibold text-slate-800">{{ verification.nama_rekening }}</span>
                            <span class="block text-[10px] font-mono text-slate-500">{{ verification.kode_rekening }}</span>
                        </div>
                    </div>

                    <!-- Uraian Belanja -->
                    <div class="pb-4 border-b border-slate-100">
                        <span class="text-slate-400 block text-[11px]">Uraian Belanja Kuitansi</span>
                        <p class="font-medium text-slate-800 mt-0.5 text-xs sm:text-sm">
                            {{ verification.uraian }}
                        </p>
                    </div>

                    <!-- Rekapitulasi Keuangan -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-[11px] font-bold text-gov-navy-950 uppercase tracking-wider block mb-2">
                            Nilai Transaksi Akuntabel
                        </span>
                        <div class="flex items-baseline justify-between py-1 border-b border-slate-200 text-xs">
                            <span class="text-slate-600">Nominal Bruto Belanja</span>
                            <span class="font-bold text-slate-900 tabular-nums">{{ formatRupiah(verification.nominal_bruto) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between py-1 border-b border-slate-200 text-xs">
                            <span class="text-slate-600">Potongan Pajak (PPN/PPh/PBJT)</span>
                            <span class="font-bold text-rose-600 tabular-nums">- {{ formatRupiah(verification.total_pajak) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between pt-2 text-sm">
                            <span class="font-bold text-slate-800">Nominal Neto Diterima</span>
                            <span class="font-black text-emerald-700 text-base tabular-nums">{{ formatRupiah(verification.nominal_neto) }}</span>
                        </div>
                    </div>

                    <!-- Rekanan / Vendor (Data Disamarkan / Privacy-Protected) -->
                    <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl">
                        <div class="flex items-center gap-1.5 text-amber-800 font-bold text-[11px] mb-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Data Rekanan Disamarkan (UU Perlindungan Data Pribadi)</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                            <div>
                                <span class="text-slate-500 text-[10px] block">Penerima Pembayaran:</span>
                                <span class="font-bold font-mono text-slate-800">{{ verification.nama_penerima_redacted }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[10px] block">NPWP Rekanan:</span>
                                <span class="font-mono text-slate-800">{{ verification.npwp_penerima_redacted || 'Tidak Ada' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tanda Tangan & Pemeriksa -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] flex items-center justify-between">
                        <div>
                            <span class="text-slate-400 block">Diverifikasi Oleh:</span>
                            <span class="font-bold text-slate-900">{{ verification.verifikator.nama }}</span>
                            <span class="text-[10px] text-slate-500 block">NIP: {{ verification.verifikator.nip }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 block">Waktu Verifikasi:</span>
                            <span class="font-semibold text-slate-700">{{ verification.tanggal_verifikasi }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error State / Token Tidak Ditemukan -->
            <div v-else class="bg-white rounded-2xl shadow-lg border border-rose-200 p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-slate-900 mb-2">QR Code Tidak Valid</h2>
                <p class="text-xs text-slate-600 max-w-sm mx-auto mb-6">
                    {{ error || 'Dokumen Lembar Kendali tidak ditemukan dalam database resmi SI-TALITI Kabupaten Garut. Mohon pastikan dokumen fisik asli dan diterbitkan secara resmi.' }}
                </p>
                <Link
                    href="/"
                    class="inline-block px-4 py-2 bg-gov-navy-900 text-white rounded-lg text-xs font-semibold hover:bg-gov-navy-800"
                >
                    Kembali ke Halaman Utama
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-4 text-center text-[10px] text-slate-400">
            Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ (SI-TALITI v2.0)<br/>
            Kecamatan Tarogong Kidul & 5 Kelurahan — Kabupaten Garut © 2026
        </footer>
    </div>
</template>

