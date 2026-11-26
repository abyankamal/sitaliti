<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, AlertTriangle, Lock, XCircle } from 'lucide-vue-next';
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
    <Head title="Verifikasi Keabsahan Dokumen SPJ - E - Verifikatur Kecamatan Tarogong Kidul" />

    <div class="min-h-screen bg-canvas flex flex-col justify-between font-sans text-ink">
        <!-- Header Resmi Pemerintah -->
        <header class="bg-surface text-ink border-b border-hairline">
            <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img
                        src="/Lambang_Kabupaten_Garut.svg.webp"
                        alt="Lambang Kabupaten Garut"
                        class="w-9 h-9 object-contain"
                    />
                    <div>
                        <h1 class="text-xs font-medium text-ink-muted">Pemerintah Kabupaten Garut</h1>
                        <p class="text-sm font-semibold text-ink">Kecamatan Tarogong Kidul</p>
                    </div>
                </div>
                <span class="text-xs bg-raised text-ink-secondary px-2.5 py-1 rounded-md font-mono border border-hairline">
                    E - Verifikatur
                </span>
            </div>
        </header>

        <!-- Konten Utama Verifikasi Publik -->
        <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 my-auto">
            <!-- Dokumen Ditemukan & Valid -->
            <div v-if="verification" class="bg-surface rounded-xl shadow-subtle border border-hairline overflow-hidden">
                <!-- Status Banner -->
                <div
                    class="p-5 text-center border-b"
                    :class="verification.is_valid ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'"
                >
                    <div
                        class="w-14 h-14 mx-auto mb-3 rounded-md flex items-center justify-center shadow-subtle"
                        :class="verification.is_valid ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white'"
                    >
                        <CheckCircle2 v-if="verification.is_valid" class="w-8 h-8" />
                        <AlertTriangle v-else class="w-8 h-8" />
                    </div>

                    <h2 class="text-lg font-semibold text-ink">
                        {{ verification.status_label }}
                    </h2>
                    <p class="text-xs text-ink-secondary mt-1 font-mono">
                        {{ verification.nomor_register }}
                    </p>
                </div>

                <!-- Detail Verifikasi -->
                <div class="p-6 space-y-5 text-xs">
                    <!-- Unit & Subkegiatan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-hairline">
                        <div>
                            <span class="text-ink-muted block text-xs">Unit Kerja Pengaju</span>
                            <span class="font-bold text-ink text-sm">{{ verification.unit_kerja }}</span>
                        </div>
                        <div>
                            <span class="text-ink-muted block text-xs">Kode & Nama Rekening</span>
                            <span class="font-semibold text-ink">{{ verification.nama_rekening }}</span>
                            <span class="block text-xs font-mono text-ink-muted">{{ verification.kode_rekening }}</span>
                        </div>
                    </div>

                    <!-- Uraian Belanja -->
                    <div class="pb-4 border-b border-hairline">
                        <span class="text-ink-muted block text-xs">Uraian Belanja Kuitansi</span>
                        <p class="font-medium text-ink mt-0.5 text-xs sm:text-sm">
                            {{ verification.uraian }}
                        </p>
                    </div>

                    <!-- Rekapitulasi Keuangan -->
                    <div class="bg-raised p-4 rounded-xl border border-hairline">
                        <span class="text-xs font-bold text-ink block mb-2">
                            Nilai Transaksi Akuntabel
                        </span>
                        <div class="flex items-baseline justify-between py-1 border-b border-hairline text-xs">
                            <span class="text-ink-secondary">Nominal Bruto Belanja</span>
                            <span class="font-bold text-ink tabular-nums">{{ formatRupiah(verification.nominal_bruto) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between py-1 border-b border-hairline text-xs">
                            <span class="text-ink-secondary">Potongan Pajak (PPN/PPh/PBJT)</span>
                            <span class="font-bold text-rose-600 tabular-nums">- {{ formatRupiah(verification.total_pajak) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between pt-2 text-sm">
                            <span class="font-bold text-ink">Nominal Neto Diterima</span>
                            <span class="font-semibold text-emerald-700 text-base tabular-nums">{{ formatRupiah(verification.nominal_neto) }}</span>
                        </div>
                    </div>

                    <!-- Rekanan / Vendor (Data Disamarkan / Privacy-Protected) -->
                    <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl">
                        <div class="flex items-center gap-1.5 text-amber-800 font-bold text-xs mb-1">
                            <Lock class="w-3.5 h-3.5" />
                            <span>Data Rekanan Disamarkan (UU Perlindungan Data Pribadi)</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                            <div>
                                <span class="text-ink-muted text-xs block">Penerima Pembayaran:</span>
                                <span class="font-bold font-mono text-ink">{{ verification.nama_penerima_redacted }}</span>
                            </div>
                            <div>
                                <span class="text-ink-muted text-xs block">NPWP Rekanan:</span>
                                <span class="font-mono text-ink">{{ verification.npwp_penerima_redacted || 'Tidak Ada' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tanda Tangan & Pemeriksa -->
                    <div class="p-3 rounded-xl bg-raised border border-hairline text-xs flex items-center justify-between">
                        <div>
                            <span class="text-ink-muted block">Diverifikasi Oleh:</span>
                            <span class="font-bold text-ink">{{ verification.verifikator.nama }}</span>
                            <span class="text-xs text-ink-muted block">NIP: {{ verification.verifikator.nip }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-ink-muted block">Waktu Verifikasi:</span>
                            <span class="font-semibold text-ink-secondary">{{ verification.tanggal_verifikasi }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error State / Token Tidak Ditemukan -->
            <div v-else class="bg-surface rounded-xl shadow-subtle border border-rose-200 p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-rose-100 text-rose-600 rounded-md flex items-center justify-center">
                    <XCircle class="w-8 h-8" />
                </div>
                <h2 class="text-lg font-bold text-ink mb-2">QR Code Tidak Valid</h2>
                <p class="text-xs text-ink-secondary max-w-sm mx-auto mb-6">
                    {{ error || 'Dokumen Lembar Kendali tidak ditemukan dalam database resmi E - Verifikatur Kecamatan Tarogong Kidul. Mohon pastikan dokumen fisik asli dan diterbitkan secara resmi.' }}
                </p>
                <Link
                    href="/"
                    class="inline-block px-4 py-2 bg-accent text-white rounded-lg text-xs font-semibold hover:bg-accent-hover"
                >
                    Kembali ke Halaman Utama
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-4 text-center text-xs text-ink-muted">
            E - Verifikatur Kecamatan Tarogong Kidul<br/>
            Kecamatan Tarogong Kidul & 5 Kelurahan — Kabupaten Garut © 2026
        </footer>
    </div>
</template>

