<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import GovButton from '@/Components/UI/GovButton.vue';
import VerificationQrCard from '@/Modules/ControlSheet/VerificationQrCard.vue';
import { verificationService } from '@/Services/verificationService';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

const props = defineProps({
    id: {
        type: String,
        default: 'KW-2026-06-0042',
    },
});

const detail = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        detail.value = await verificationService.getDetail(props.id);
    } finally {
        loading.value = false;
    }
});

function handlePrint() {
    window.print();
}
</script>

<template>
    <Head :title="`Lembar Kendali Verifikasi - ${detail?.id || ''}`" />

    <div class="min-h-screen bg-slate-200 py-6 px-4 font-sans text-slate-800 print:bg-white print:p-0">
        <!-- Floating Action Header (Disembunyikan saat dicetak) -->
        <div class="max-w-4xl mx-auto mb-6 bg-gov-navy-950 text-white p-4 rounded-xl shadow-md border border-gov-navy-800 flex items-center justify-between print:hidden">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('verification.index')"
                    class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-gov-navy-900"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h2 class="text-sm font-bold text-white">Lembar Kendali Hasil Verifikasi Akuntabilitas SPJ</h2>
                    <p class="text-xs text-slate-300">Dokumen tanda sah kelayakan administrasi sebelum diajukan ke SIPD-RI</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a
                    :href="route('control-sheet.download-pdf', { id: detail.id || props.id })"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gov-gold-500 text-gov-navy-950 hover:bg-gov-gold-400 shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Unduh PDF Resmi
                </a>
                <GovButton variant="secondary" size="sm" @click="handlePrint">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak Halaman
                </GovButton>
            </div>
        </div>

        <!-- Official Control Sheet Document Paper (A4 / Folio Simulation) -->
        <div
            v-if="detail"
            class="max-w-4xl mx-auto bg-white p-8 sm:p-12 shadow-xl border border-slate-300 rounded-sm print:shadow-none print:border-none print:p-6 print:m-0"
        >
            <!-- Kop Dokumen Resmi Pemerintah Daerah -->
            <div class="flex items-center gap-5 border-b-2 border-slate-900 pb-4 mb-6">
                <!-- Logo Garut Placeholder -->
                <div class="w-16 h-16 border-2 border-slate-900 rounded-lg flex items-center justify-center font-bold text-xs text-center shrink-0">
                    KAB.<br/>GARUT
                </div>

                <div class="flex-1 text-center">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                        Pemerintah Daerah Kabupaten Garut
                    </h3>
                    <h2 class="text-base font-black uppercase text-gov-navy-950">
                        Kecamatan Tarogong Kidul
                    </h2>
                    <p class="text-[11px] text-slate-600">
                        Jl. Suherman No. 27, Tarogong Kidul, Kabupaten Garut, Jawa Barat 44151
                    </p>
                    <p class="text-[10px] text-slate-500 font-mono mt-0.5">
                        Sistem Informasi Tata Kelola, Asistensi, dan Verifikasi Akuntabilitas SPJ (SI-TALITI v2.0)
                    </p>
                </div>

                <div class="w-16 shrink-0"></div>
            </div>

            <!-- Document Title & Badge -->
            <div class="text-center mb-6">
                <h1 class="text-sm font-bold uppercase tracking-widest text-gov-navy-950 underline decoration-2">
                    Lembar Kendali Verifikasi Akuntabilitas SPJ
                </h1>
                <p class="text-xs text-slate-600 font-mono mt-1">
                    No. Register: <strong>LK-V2/{{ detail.id }}/2026</strong>
                </p>
            </div>

            <!-- Bagian 1: Data Identitas Kuitansi Transaksi -->
            <div class="mb-6">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gov-navy-950 bg-slate-100 px-3 py-1.5 border-l-4 border-gov-navy-900 mb-3">
                    I. Informasi Dokumen Kuitansi & Anggaran
                </h4>
                <table class="w-full text-xs">
                    <tbody>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600 w-44">Nomor Bukti Transaksi</td>
                            <td class="py-1.5 font-bold text-slate-900">: {{ detail.id }}</td>
                            <td class="py-1.5 font-semibold text-slate-600 w-36">Tanggal Transaksi</td>
                            <td class="py-1.5 text-slate-900">: {{ formatTanggalIndo(detail.tanggal) }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600">Unit Organisasi (SKPD)</td>
                            <td class="py-1.5 text-slate-900" colspan="3">: {{ detail.unit }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600">Program & Subkegiatan</td>
                            <td class="py-1.5 text-slate-900" colspan="3">: {{ detail.sub_activity }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600">Kode Rekening Belanja</td>
                            <td class="py-1.5 text-slate-900" colspan="3">: <span class="font-mono font-bold">{{ detail.account_code }}</span> - {{ detail.account_name }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600">Kategori Belanja</td>
                            <td class="py-1.5 font-bold text-slate-900">: Kategori {{ detail.category }}</td>
                            <td class="py-1.5 font-semibold text-slate-600">Penyedia / Penerima</td>
                            <td class="py-1.5 text-slate-900">: {{ detail.vendor }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 font-semibold text-slate-600">Uraian Pengeluaran</td>
                            <td class="py-1.5 text-slate-900 leading-relaxed" colspan="3">: {{ detail.uraian }}</td>
                        </tr>
                        <tr class="bg-slate-50 font-semibold">
                            <td class="py-2 font-bold text-slate-800">Total Nilai Bruto</td>
                            <td class="py-2 text-base font-bold text-gov-navy-950 tabular-nums">: {{ formatRupiah(detail.nominal) }}</td>
                            <td class="py-2 font-bold text-slate-800">Jumlah Netto Kas</td>
                            <td class="py-2 text-base font-bold text-emerald-800 tabular-nums">: {{ formatRupiah(detail.taxes?.netto || detail.nominal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bagian 2: Hasil Pemeriksaan Checklist Dokumen Fisik -->
            <div class="mb-6">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gov-navy-950 bg-slate-100 px-3 py-1.5 border-l-4 border-gov-navy-900 mb-3">
                    II. Checklist Hasil Uji Fisik Dokumen Pendukung
                </h4>
                <table class="w-full text-xs border border-slate-200">
                    <thead class="bg-slate-100 text-[11px] font-bold uppercase text-slate-700">
                        <tr>
                            <th class="py-2 px-3 border-b text-center w-10">No</th>
                            <th class="py-2 px-3 border-b text-left">Nama Dokumen Bukti Pertanggungjawaban</th>
                            <th class="py-2 px-3 border-b text-center w-24">Sifat</th>
                            <th class="py-2 px-3 border-b text-center w-28">Hasil Uji</th>
                            <th class="py-2 px-3 border-b text-left">Catatan Verifikator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="(att, idx) in detail.attachments" :key="att.id">
                            <td class="py-2 px-3 text-center text-slate-500 font-mono">{{ idx + 1 }}</td>
                            <td class="py-2 px-3 font-medium text-slate-800">{{ att.doc_name }}</td>
                            <td class="py-2 px-3 text-center">
                                <span class="text-[10px] font-bold" :class="att.required ? 'text-rose-700' : 'text-slate-500'">
                                    {{ att.required ? 'WAJIB' : 'OPSIONAL' }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-center font-bold">
                                <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded text-[10px] border border-emerald-200">
                                    ✓ MEMENUHI
                                </span>
                            </td>
                            <td class="py-2 px-3 text-[11px] text-slate-600">
                                {{ att.notes || 'Lengkap & Sesuai' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bagian 3: Kesimpulan & QR Code Otentikasi -->
            <div class="mb-8 p-4 bg-slate-50 border border-slate-300 rounded-lg flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="space-y-1.5 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gov-navy-950">
                            Kesimpulan Verifikasi Pre-Audit SI-TALITI:
                        </h4>
                    </div>
                    <p class="text-xs font-bold text-emerald-800">
                        DINYATAKAN LOLOS & MEMENUHI SYARAT KELENGKAPAN AKUNTABILITAS SPJ
                    </p>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        Dokumen ini telah diperiksa kesesuaian SSH/SBM, ketersediaan alokasi RAK, dan kelengkapan bukti fisik pengeluaran kas. Berkas ini siap dibundel dalam pengesahan SPP sebelum diproses resmi melalui SIPD-RI.
                    </p>
                </div>

                <!-- QR Code Verification Card -->
                <div class="shrink-0 text-center">
                    <VerificationQrCard
                        :value="`https://sitaliti.garutkab.go.id/verify/${detail.id}?hash=e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`"
                        :label="`TOKEN: LK-${detail.id}`"
                        :size="100"
                    />
                    <p class="text-[9px] text-slate-400 mt-1">Pindai untuk validasi</p>
                </div>
            </div>

            <!-- Bagian 4: Lembar Tanda Tangan Resmi Pengesahan -->
            <div class="grid grid-cols-2 gap-8 text-xs text-center pt-4">
                <div>
                    <p class="text-slate-600">Mengetahui,</p>
                    <p class="font-bold text-gov-navy-950">Pejabat Pembuat Komitmen (PPK)</p>
                    <div class="h-20"></div>
                    <p class="font-bold text-slate-900 underline">H. DEDI SUTRISNO, S.Sos., M.Si</p>
                    <p class="text-[11px] text-slate-500">NIP. 19780512 200501 1 008</p>
                </div>

                <div>
                    <p class="text-slate-600">Tarogong Kidul, {{ formatTanggalIndo(new Date()) }}</p>
                    <p class="font-bold text-gov-navy-950">Tim Verifikator Akuntabilitas SPJ</p>
                    <div class="h-20"></div>
                    <p class="font-bold text-slate-900 underline">IRA MARLINA, S.IP</p>
                    <p class="text-[11px] text-slate-500">NIP. 19850924 201001 2 015</p>
                </div>
            </div>
        </div>
    </div>
</template>

