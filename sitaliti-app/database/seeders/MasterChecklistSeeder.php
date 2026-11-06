<?php

namespace Database\Seeders;

use App\Models\MasterChecklistItem;
use Illuminate\Database\Seeder;

class MasterChecklistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'B.1' => [
                [
                    'kode_item' => 'DOC_B1_01',
                    'nama_item' => 'Surat Pesanan / Nota Pesanan',
                    'deskripsi' => 'Ditandatangani oleh PPTK dan Penyedia',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B1_02',
                    'nama_item' => 'Kuitansi Pembayaran Bermaterai',
                    'deskripsi' => 'Materai Rp10.000 untuk transaksi > Rp5.000.000',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B1_03',
                    'nama_item' => 'Nota / Faktur Toko Asli',
                    'deskripsi' => 'Rincian barang stempel toko penyedia',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B1_04',
                    'nama_item' => 'Berita Acara Serah Terima (BAST)',
                    'deskripsi' => 'Ditandatangani pihak penyedia dan penerima barang',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B1_05',
                    'nama_item' => 'Dokumentasi / Foto Fisik Barang',
                    'deskripsi' => 'Foto barang yang telah diterima di kantor',
                    'is_required' => true,
                    'urutan' => 5,
                ],
                [
                    'kode_item' => 'DOC_B1_06',
                    'nama_item' => 'Faktur Pajak Elektronik (e-Faktur / SSP)',
                    'deskripsi' => 'Wajib jika nilai transaksi di atas batas PKP / kena PPh-PPN',
                    'is_required' => false,
                    'urutan' => 6,
                ],
            ],
            'B.2' => [
                [
                    'kode_item' => 'DOC_B2_01',
                    'nama_item' => 'Surat Undangan Rapat / Kegiatan',
                    'deskripsi' => 'Mencantumkan tanggal, jam, tempat, dan agenda rapat',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B2_02',
                    'nama_item' => 'Daftar Hadir Peserta Rapat Asli',
                    'deskripsi' => 'Tanda tangan peserta sesuai jumlah pesanan konsumsi',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B2_03',
                    'nama_item' => 'Notula / Hasil Rapat',
                    'deskripsi' => 'Ringkasan pembahasan dan keputusan rapat',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B2_04',
                    'nama_item' => 'Kuitansi dan Nota Pembelian Konsumsi',
                    'deskripsi' => 'Dari katering / warung makan bermaterai (bila berlaku)',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B2_05',
                    'nama_item' => 'Foto Dokumentasi Pelaksanaan Rapat',
                    'deskripsi' => 'Memperlihatkan peserta dan jamuan konsumsi',
                    'is_required' => true,
                    'urutan' => 5,
                ],
                [
                    'kode_item' => 'DOC_B2_06',
                    'nama_item' => 'Bukti Setor Pajak Restoran / PPh 23',
                    'deskripsi' => 'Sesuai tarif pajak daerah PBJT 10% / PPh jasa boga',
                    'is_required' => false,
                    'urutan' => 6,
                ],
            ],
            'B.3' => [
                [
                    'kode_item' => 'DOC_B3_01',
                    'nama_item' => 'Surat Tugas (ST) Resmi',
                    'deskripsi' => 'Ditandatangani oleh Camat / Pejabat Berwenang',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B3_02',
                    'nama_item' => 'Surat Perintah Perjalanan Dinas (SPPD) Lembar 1 & 2',
                    'deskripsi' => 'Wajib distempel dan ditandatangani pejabat tujuan',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B3_03',
                    'nama_item' => 'Laporan Pelaksanaan Perjalanan Dinas',
                    'deskripsi' => 'Laporan hasil kunjungan / koordinasi',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B3_04',
                    'nama_item' => 'Rincian Biaya Perjalanan Dinas (Kuitansi Rampung)',
                    'deskripsi' => 'Uang harian, transport, dan penginapan riil',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B3_05',
                    'nama_item' => 'Tiket / Bukti Transport / Toll / Bahan Bakar',
                    'deskripsi' => 'Bukti pembayaran transport riil',
                    'is_required' => true,
                    'urutan' => 5,
                ],
                [
                    'kode_item' => 'DOC_B3_06',
                    'nama_item' => 'Foto Dokumentasi di Lokasi Tujuan',
                    'deskripsi' => 'Foto koordinasi / kegiatan di tempat tujuan',
                    'is_required' => true,
                    'urutan' => 6,
                ],
            ],
            'B.4' => [
                [
                    'kode_item' => 'DOC_B4_01',
                    'nama_item' => 'Surat Keputusan (SK) Tim / Narasumber',
                    'deskripsi' => 'SK Camat tentang penetapan narasumber atau tim',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B4_02',
                    'nama_item' => 'Daftar Penerimaan Honorarium',
                    'deskripsi' => 'Mencantumkan nama, golongan, NPWP, jumlah jam/OB, dan TTD',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B4_03',
                    'nama_item' => 'Materi / Bahan Tayang Paparan',
                    'deskripsi' => 'Salinan slide materi narasumber',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B4_04',
                    'nama_item' => 'Daftar Hadir Peserta Sosialisasi / Bimtek',
                    'deskripsi' => 'Tanda tangan peserta acara',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B4_05',
                    'nama_item' => 'Bukti Potong PPh Pasal 21',
                    'deskripsi' => 'Bukti potongan pajak honorarium sesuai golongan/NPWP',
                    'is_required' => true,
                    'urutan' => 5,
                ],
                [
                    'kode_item' => 'DOC_B4_06',
                    'nama_item' => 'Foto Dokumentasi Pemaparan Materi',
                    'deskripsi' => 'Foto narasumber saat membawakan materi',
                    'is_required' => true,
                    'urutan' => 6,
                ],
            ],
            'B.5' => [
                [
                    'kode_item' => 'DOC_B5_01',
                    'nama_item' => 'Surat Perintah Kerja (SPK) / Nota Pesanan Servis',
                    'deskripsi' => 'Perintah kerja pemeliharaan kendaraan atau peralatan',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B5_02',
                    'nama_item' => 'Kuitansi & Rincian Faktur Bengkel / Servis',
                    'deskripsi' => 'Rincian pergantian sparepart dan jasa ongkos kerja',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B5_03',
                    'nama_item' => 'Berita Acara Pemeriksaan Pekerjaan (BAPP)',
                    'deskripsi' => 'Pemeriksaan bahwa hasil servis berfungsi normal',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B5_04',
                    'nama_item' => 'Foto Fisik Sparepart Lama vs Baru (Before/After)',
                    'deskripsi' => 'Dokumentasi penggantian suku cadang mesin/kendaraan',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B5_05',
                    'nama_item' => 'Bukti Setor PPh Pasal 23 (Jasa Pemeliharaan)',
                    'deskripsi' => 'Potongan PPh 23 sebesar 2% atas ongkos jasa',
                    'is_required' => false,
                    'urutan' => 5,
                ],
            ],
            'B.6' => [
                [
                    'kode_item' => 'DOC_B6_01',
                    'nama_item' => 'Surat Pesanan (e-Katalog / Kontrak)',
                    'deskripsi' => 'Bukti pemesanan resmi tercatat',
                    'is_required' => true,
                    'urutan' => 1,
                ],
                [
                    'kode_item' => 'DOC_B6_02',
                    'nama_item' => 'Berita Acara Serah Terima Barang (BAST-B)',
                    'deskripsi' => 'Ditandatangani penyedia dan Pengurus Barang Pengguna',
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'kode_item' => 'DOC_B6_03',
                    'nama_item' => 'Berita Acara Hasil Pemeriksaan (BAHP)',
                    'deskripsi' => 'Hasil pengecekan spesifikasi teknis peralatan',
                    'is_required' => true,
                    'urutan' => 3,
                ],
                [
                    'kode_item' => 'DOC_B6_04',
                    'nama_item' => 'Kartu Inventaris Barang (KIB / Input SIMDA/SIPD-BMD)',
                    'deskripsi' => 'Bukti registrasi nomor kode barang aset daerah',
                    'is_required' => true,
                    'urutan' => 4,
                ],
                [
                    'kode_item' => 'DOC_B6_05',
                    'nama_item' => 'Faktur Asli, e-Faktur Pajak & Surat Garansi',
                    'deskripsi' => 'Faktur pembelian resmi dan kartu garansi resmi distributor',
                    'is_required' => true,
                    'urutan' => 5,
                ],
                [
                    'kode_item' => 'DOC_B6_06',
                    'nama_item' => 'Foto Fisik Aset Lengkap dengan Plat/Label Inventaris',
                    'deskripsi' => 'Foto barang modal terpasang label inventaris SKPD',
                    'is_required' => true,
                    'urutan' => 6,
                ],
            ],
        ];

        foreach ($categories as $kategori => $items) {
            foreach ($items as $item) {
                MasterChecklistItem::updateOrCreate(
                    ['kode_item' => $item['kode_item']],
                    array_merge($item, ['kategori_belanja' => $kategori])
                );
            }
        }
    }
}

