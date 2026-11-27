<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Download, Printer } from 'lucide-vue-next';
import GovButton from '@/Components/UI/GovButton.vue';
import VerificationQrCard from '@/Modules/ControlSheet/VerificationQrCard.vue';
import { formatRupiah } from '@/Utils/currency';
import { formatTanggalIndo } from '@/Utils/date';

const props = defineProps({
    id: {
        type: [Number, String],
        required: true,
    },
});

const detail = ref(null);
const loading = ref(true);
const errorMessage = ref('');

onMounted(async () => {
    try {
        const response = await axios.get(`/api/v1/control-sheet/${props.id}`);
        detail.value = response.data?.data || null;
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message || 'Gagal memuat data Lembar Kendali dari server.';
    } finally {
        loading.value = false;
    }
});

const qrVerificationUrl = computed(() => {
    if (!detail.value) return '';
    if (detail.value.verification_url) return detail.value.verification_url;
    if (detail.value.qr_token) return `${window.location.origin}/verify/${detail.value.qr_token}`;
    return '';
});

function handlePrint() {
    window.print();
}
</script>

<template>
    <Head :title="`Lembar Kendali Verifikasi - ${detail?.nomor_kuitansi || props.id}`" />

    <div class="min-h-screen bg-canvas py-6 px-4 font-sans text-ink print:bg-white print:p-0">
        <!-- Floating Action Header (Disembunyikan saat dicetak) -->
        <div class="max-w-4xl mx-auto mb-6 bg-surface text-ink p-4 rounded-xl shadow-subtle border border-hairline flex items-center justify-between print:hidden">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('receipts.index')"
                    class="p-2 rounded-lg text-ink-muted hover:text-ink hover:bg-raised"
                >
                    <ArrowLeft class="w-5 h-5" />
                </Link>
                <div>
                    <h2 class="text-sm font-bold text-ink">Lembar Kendali Hasil Verifikasi Akuntabilitas SPJ</h2>
                    <p class="text-xs text-ink-secondary">Dokumen tanda sah kelayakan administrasi sebelum diajukan ke SIPD-RI</p>
                </div>
            </div>

            <div v-if="detail" class="flex items-center gap-3">
                <a
                    :href="route('control-sheet.download-pdf', { id: detail.id || props.id })"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-accent text-white hover:bg-accent-hover shadow-subtle transition"
                >
                    <Download class="w-4 h-4" />
                    Unduh PDF Resmi
                </a>
                <GovButton variant="secondary" size="sm" @click="handlePrint">
                    <Printer class="w-4 h-4" />
                    Cetak Halaman
                </GovButton>
            </div>
        </div>

        <div v-if="loading" class="max-w-4xl mx-auto bg-surface p-12 text-center text-sm text-ink-muted rounded-sm shadow-subtle border border-hairline">
            Memuat data Lembar Kendali...
        </div>

        <div v-else-if="errorMessage" class="max-w-4xl mx-auto bg-red-50 border border-red-200 text-red-900 p-6 rounded-xl text-sm">
            <p class="font-bold">Lembar Kendali Tidak Tersedia</p>
            <p class="mt-1">{{ errorMessage }}</p>
        </div>

        <!-- Official Control Sheet Document Paper (A4 / Folio Simulation) -->
        <div
            v-else-if="detail"
            class="max-w-4xl mx-auto bg-surface print:bg-white p-8 sm:p-12 shadow-floating border border-hairline rounded-sm print:shadow-none print:border-none print:p-6 print:m-0"
        >
            <!-- Kop Dokumen Resmi Pemerintah Daerah -->
            <div class="flex items-center gap-5 border-b-2 border-ink pb-4 mb-6">
                <img
                    src="/Lambang_Kabupaten_Garut.svg.webp"
                    alt="Lambang Kabupaten Garut"
                    class="w-16 h-16 object-contain shrink-0"
                />

                <div class="flex-1 text-center">
                    <h3 class="text-sm font-bold text-ink">
                        Pemerintah Daerah Kabupaten Garut
                    </h3>
                    <h2 class="text-base font-semibold uppercase text-ink">
                        Kecamatan Tarogong Kidul
                    </h2>
                    <p class="text-xs text-ink-secondary">
                        Jalan Simpang No. 1, Tarogong Kidul, Kabupaten Garut, Jawa Barat 44151
                    </p>
                    <p class="text-xs text-ink-muted font-mono mt-0.5">
                        E - Verifikatur Kecamatan Tarogong Kidul
                    </p>
                </div>

                <div class="w-16 shrink-0"></div>
            </div>

            <!-- Document Title & Badge -->
            <div class="text-center mb-6">
                <h1 class="text-sm font-bold text-ink underline decoration-2">
                    Lembar Kendali Verifikasi Akuntabilitas SPJ
                </h1>
                <p class="text-xs text-ink-secondary font-mono mt-1">
                    No. Register: <strong>{{ detail.nomor_register }}</strong>
                </p>
            </div>

            <!-- Bagian 1: Data Identitas Kuitansi Transaksi -->
            <div class="mb-6">
                <h4 class="text-xs font-bold text-ink bg-raised px-3 py-1.5 border border-hairline rounded-md mb-3">
                    I. Informasi Dokumen Kuitansi & Anggaran
                </h4>
                <table class="w-full text-xs">
                    <tbody>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary w-44">Nomor Bukti Transaksi</td>
                            <td class="py-1.5 font-bold text-ink">: {{ detail.nomor_kuitansi }}</td>
                            <td class="py-1.5 font-semibold text-ink-secondary w-36">Tanggal Transaksi</td>
                            <td class="py-1.5 text-ink">: {{ formatTanggalIndo(detail.tanggal_transaksi) }}</td>
                        </tr>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary">Unit Organisasi (SKPD)</td>
                            <td class="py-1.5 text-ink" colspan="3">: {{ detail.unit_kerja?.nama_unit || '-' }}</td>
                        </tr>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary">Program & Subkegiatan</td>
                            <td class="py-1.5 text-ink" colspan="3">
                                : <span class="font-mono">{{ detail.rekening?.kode_sub_kegiatan || '-' }}</span> - {{ detail.rekening?.nama_sub_kegiatan || '-' }}
                            </td>
                        </tr>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary">Kode Rekening Belanja</td>
                            <td class="py-1.5 text-ink" colspan="3">
                                : <span class="font-mono font-bold">{{ detail.rekening?.kode_rekening || '-' }}</span> - {{ detail.rekening?.nama_rekening || '-' }}
                            </td>
                        </tr>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary">Kategori Belanja</td>
                            <td class="py-1.5 font-bold text-ink">: Kategori {{ detail.rekening?.kategori_belanja || '-' }}</td>
                            <td class="py-1.5 font-semibold text-ink-secondary">Penyedia / Penerima</td>
                            <td class="py-1.5 text-ink">: {{ detail.nama_penerima }}</td>
                        </tr>
                        <tr class="border-b border-hairline">
                            <td class="py-1.5 font-semibold text-ink-secondary">Uraian Pengeluaran</td>
                            <td class="py-1.5 text-ink leading-relaxed" colspan="3">: {{ detail.uraian }}</td>
                        </tr>
                        <tr class="bg-raised font-semibold">
                            <td class="py-2 font-bold text-ink">Total Nilai Bruto</td>
                            <td class="py-2 text-base font-bold text-ink tabular-nums">: {{ formatRupiah(detail.nominal_bruto) }}</td>
                            <td class="py-2 font-bold text-ink">Jumlah Netto Kas</td>
                            <td class="py-2 text-base font-bold text-emerald-800 tabular-nums">: {{ formatRupiah(detail.nominal_neto) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bagian 2: Hasil Pemeriksaan Checklist Dokumen Fisik -->
            <div class="mb-6">
                <h4 class="text-xs font-bold text-ink bg-raised px-3 py-1.5 border border-hairline rounded-md mb-3">
                    II. Checklist Hasil Uji Fisik Dokumen Pendukung
                </h4>
                <table class="w-full text-xs border border-hairline print:border-black">
                    <thead class="bg-raised print:bg-white text-xs font-bold text-ink-secondary print:text-black">
                        <tr>
                            <th class="py-2 px-3 border-b border-hairline print:border-black text-center w-10">No</th>
                            <th class="py-2 px-3 border-b border-hairline print:border-black text-left">Nama Dokumen Bukti Pertanggungjawaban</th>
                            <th class="py-2 px-3 border-b border-hairline print:border-black text-center w-24">Kode</th>
                            <th class="py-2 px-3 border-b border-hairline print:border-black text-center w-32">Hasil Uji</th>
                            <th class="py-2 px-3 border-b border-hairline print:border-black text-left">Catatan Verifikator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline print:divide-black">
                        <tr v-for="(item, idx) in detail.checklist || []" :key="item.kode_item || idx" class="print:break-inside-avoid">
                            <td class="py-2 px-3 text-center text-ink-muted print:text-black font-mono">{{ idx + 1 }}</td>
                            <td class="py-2 px-3 font-medium text-ink">{{ item.nama_item || '-' }}</td>
                            <td class="py-2 px-3 text-center font-mono text-xs text-ink-secondary">{{ item.kode_item || '-' }}</td>
                            <td class="py-2 px-3 text-center font-bold">
                                <span
                                    :class="['SESUAI', 'VALID'].includes(item.status_kesesuaian)
                                        ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                                        : 'text-amber-700 bg-amber-50 border-amber-200'"
                                    class="px-2 py-0.5 rounded text-xs border [print-color-adjust:exact]"
                                >
                                    {{ ['SESUAI', 'VALID'].includes(item.status_kesesuaian) ? 'MEMENUHI' : (item.status_kesesuaian || 'DIPERIKSA') }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-xs text-ink-secondary">
                                {{ item.catatan || 'Lengkap & Sesuai' }}
                            </td>
                        </tr>
                        <tr v-if="!detail.checklist || detail.checklist.length === 0">
                            <td colspan="5" class="py-4 text-center text-ink-muted italic">
                                Tidak ada item checklist verifikasi tercatat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bagian 3: Kesimpulan & QR Code Otentikasi -->
            <div class="mb-8 p-4 bg-raised print:bg-white border border-hairline print:border-black rounded-lg flex flex-col sm:flex-row items-center justify-between gap-6 print:break-inside-avoid">
                <div class="space-y-1.5 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-md bg-semantic-success [print-color-adjust:exact]"></span>
                        <h4 class="text-xs font-bold text-ink print:text-black">
                            Kesimpulan Verifikasi Pre-Audit E - Verifikatur:
                        </h4>
                    </div>
                    <p class="text-xs font-bold text-emerald-800 print:text-black">
                        DINYATAKAN LOLOS & MEMENUHI SYARAT KELENGKAPAN AKUNTABILITAS SPJ
                    </p>
                    <p class="text-xs text-ink-secondary print:text-black leading-relaxed">
                        {{ detail.catatan_verifikasi || 'Dokumen ini telah diperiksa kesesuaian SSH/SBM, ketersediaan alokasi RAK, dan kelengkapan bukti fisik pengeluaran kas. Berkas ini siap dibundel dalam pengesahan SPP sebelum diproses resmi melalui SIPD-RI.' }}
                    </p>
                    <p class="text-xs font-medium text-ink print:text-black pt-1">
                        Dokumen ini telah diverifikasi akuntabilitasnya melalui sistem SI-TALITI v2.0.
                    </p>
                </div>

                <!-- QR Code Verification Card -->
                <div class="shrink-0 text-center">
                    <VerificationQrCard
                        :value="qrVerificationUrl"
                        :label="detail.qr_token ? `TOKEN: ${detail.qr_token}` : 'TOKEN: -'"
                        :size="100"
                    />
                    <p class="text-xs text-ink-muted mt-1">Pindai untuk validasi</p>
                </div>
            </div>

            <!-- Bagian 4: Lembar Tanda Tangan Resmi Pengesahan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-xs text-center pt-4 print:break-inside-avoid">
                <div>
                    <p class="text-ink-secondary">Diverifikasi oleh,</p>
                    <p class="font-bold text-ink">
                        {{ detail.verifikator?.jabatan || 'Tim Verifikasi Akuntabilitas SPJ' }}
                    </p>
                    <div class="h-20"></div>
                    <p class="font-bold text-ink underline">
                        {{ detail.verifikator?.name || 'Verifikator Kecamatan' }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        NIP. {{ detail.verifikator?.nip || '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-ink-secondary">
                        Tarogong Kidul, {{ formatTanggalIndo(detail.tanggal_verifikasi || new Date()) }}
                    </p>
                    <p class="font-bold text-ink">Dokumen Tervalidasi Sistem</p>
                    <div class="h-20"></div>
                    <p class="font-mono text-xs font-bold text-ink-secondary">
                        {{ detail.nomor_register }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        Status: {{ detail.status }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 12mm;
    }
}
</style>
