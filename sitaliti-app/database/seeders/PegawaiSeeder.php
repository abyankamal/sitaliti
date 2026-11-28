<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;
use RuntimeException;

class PegawaiSeeder extends Seeder
{
    /**
     * Seed master data pegawai Kecamatan Tarogong Kidul beserta seluruh kelurahan.
     */
    public function run(): void
    {
        $unitIds = UnitKerja::query()->pluck('id', 'nama_unit');

        if ($unitIds->isEmpty()) {
            $this->call(UnitKerjaSeeder::class);
            $unitIds = UnitKerja::query()->pluck('id', 'nama_unit');
        }

        foreach ($this->records() as $record) {
            $unitKerjaId = $unitIds->get($record['unit_kerja']);

            if ($unitKerjaId === null) {
                throw new RuntimeException("Unit kerja '{$record['unit_kerja']}' belum tersedia. Jalankan UnitKerjaSeeder terlebih dahulu.");
            }

            Pegawai::updateOrCreate(
                ['nip' => $record['nip']],
                [
                    'unit_kerja_id' => $unitKerjaId,
                    'status_kepegawaian' => $record['status_kepegawaian'],
                    'nama' => $record['nama'],
                    'jabatan' => $record['jabatan'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array{nip: string, nama: string, jabatan: string, unit_kerja: string, status_kepegawaian: string}>
     */
    private function records(): array
    {
        return [
            [
                'nip' => '197005101990091001',
                'nama' => 'AHMAD MAWARDI, AP.,SE',
                'jabatan' => 'Camat',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197605242007012004',
                'nama' => 'ERIS ROSPITANINGSIH,SE',
                'jabatan' => 'Sekretaris Kecamatan',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197904012005011008',
                'nama' => 'NURJAMAN, S.IP.,M.Si',
                'jabatan' => 'Kasi Trantib',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197002042010012001',
                'nama' => 'POPON SITI ROHMAH, S.Ag',
                'jabatan' => 'Kasi Kesejahteraan Masyarakat',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198008172006042011',
                'nama' => 'DENTI TRESNAWATI, S Pd.',
                'jabatan' => 'Kasi Pelayanan',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197504242008011006',
                'nama' => 'MULVI SURYA, A.Md',
                'jabatan' => 'Kasubbag Keuangan dan BMD',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198211032010012002',
                'nama' => 'SITI SUPIAH, S.IP',
                'jabatan' => 'Kasubbag Umum dan Evlap',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '199909032025041005',
                'nama' => 'AZKA MUHAMMAD RIDWAN, ST',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197110062006042005',
                'nama' => 'AI ROHAYATI, SH',
                'jabatan' => 'Penelaah Teknis Kebijakan',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197402062010011006',
                'nama' => 'YADI MULYADI',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197505082009011005',
                'nama' => 'WAWAN SAEPULAH',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '196910202009061003',
                'nama' => 'AYI ROSID',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '196905042010011001',
                'nama' => 'SUHERMAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198206202009011006',
                'nama' => 'ASEP SOPIANDI',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197501182025211002',
                'nama' => 'SHOFI MUHAMMAD ANSHAR,SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198408192025211009',
                'nama' => 'AGUS WAHYUDIN,S.IP',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197705112025211005',
                'nama' => 'BUBUN BUNYAMIN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197205252025212002',
                'nama' => 'RINI SUSRIANTI',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198404142025211011',
                'nama' => 'RAHMAT RAHAYU',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198209212025211005',
                'nama' => 'ADE FIRMANSYAH',
                'jabatan' => 'Pengelola Umum Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197906252025211056',
                'nama' => 'ALINUDIN',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198512202025211086',
                'nama' => 'ASEP JUANDA NURPALAH',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198711212025212050',
                'nama' => 'SISKA RESDIYANI, SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198909022025211087',
                'nama' => 'BAYU SURYATNA PRATAMA',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '199501092025211105',
                'nama' => 'TEGUH RAHAYU',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kecamatan Tarogong Kidul',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197503132010011007',
                'nama' => 'YOYO, S.IP',
                'jabatan' => 'Lurah',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '196906062009061006',
                'nama' => 'USEP RODHIANA, S.IP',
                'jabatan' => 'Kasi Ekonomi',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197811142010012009',
                'nama' => 'RISA ROSALIA,S.IP',
                'jabatan' => 'Kasi Kemasyarakatan',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197811132008012004',
                'nama' => 'BETI SAPURA, SE',
                'jabatan' => 'Pengolah Data dan Informasi',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '200112152025041002',
                'nama' => 'MOCH. HAIQAL DIAZKI, S.Kom',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197503212005011005',
                'nama' => 'BUDI GUNAWAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197805202010011002',
                'nama' => 'IRVAN MALIK',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197902172025212007',
                'nama' => 'RATNA WULAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197710042025212018',
                'nama' => 'DIAH SYADIAH',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198302162025211057',
                'nama' => 'RIKI GINANJAR, S.IP',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198812162025212057',
                'nama' => 'LINDA LINDIANI, SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Jayawaras',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197706212010012001',
                'nama' => 'NENENG SUSANA, SE',
                'jabatan' => 'Sekretaris Kelurahan',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '196811232010011001',
                'nama' => 'AEP SAEPUDIN',
                'jabatan' => 'Kasi Kemasyarakatan',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '199304022025041001',
                'nama' => 'FIKRI AKBAR, S.Kom.',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197609202009011004',
                'nama' => 'FITRA PRAMUDIA',
                'jabatan' => 'Penelaah Teknis Kebijakan',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198310252025211006',
                'nama' => 'ARI SOFYAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198602202025212059',
                'nama' => 'ARNI FEBIYANTI, SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '199109292025211110',
                'nama' => 'EKA RAHMAN NURHAKIM',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '200111202025211012',
                'nama' => 'DWI AZHAR RAMDHANI',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukajaya',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198112182008011002',
                'nama' => 'SOPI PERDIANSYAH, SH',
                'jabatan' => 'Lurah',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197809192010011001',
                'nama' => 'RAHMAT GUNAWAN,S.d.,M.Si',
                'jabatan' => 'Kasi Pemerintahan',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197005052009011007',
                'nama' => 'ANAS,S.IP',
                'jabatan' => 'Kasi Ekonomi',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197306252010012004',
                'nama' => 'DEDEH KURNIASIH,S.IP',
                'jabatan' => 'Kasi Kemasyarakatan',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '199304212025041006',
                'nama' => 'IRVAN SYAIDUSSALAM, S.Kom',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197401042009011007',
                'nama' => 'DINDIN ROYADIN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197206192014111001',
                'nama' => 'SARIP MUSLIM',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197603122025211007',
                'nama' => 'MUHAMMAD RAKHMAT,S.IP',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198304112025212004',
                'nama' => 'GITA RACHMAWATI,SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198008012025211007',
                'nama' => 'DENI RAMDAN KUNIAWAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197103162025212006',
                'nama' => 'TINTIN SUWARSIH, S.Sos',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197001242025212006',
                'nama' => 'IDA FARIDA, S.Pd',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197003082025211014',
                'nama' => 'ARIP RAHMAN',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198704252025211075',
                'nama' => 'HIBAN APRIANA, SE',
                'jabatan' => 'Penata Layanan Operasional',
                'unit_kerja' => 'Kelurahan Sukagalih',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197710102010011038',
                'nama' => 'SUTENDI,SE,M.I.P',
                'jabatan' => 'Lurah',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198304152009011006',
                'nama' => 'GUMILANG LINGGA KUSMAYADI, S.IP',
                'jabatan' => 'Sekretaris Kelurahan',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198210222008011003',
                'nama' => 'ERI JUHERI, S.IP',
                'jabatan' => 'Kasi Pemerintahan',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198302182010012001',
                'nama' => 'RINA FARIDA,S.IP',
                'jabatan' => 'Kasi Ekonomi',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '200009292025042007',
                'nama' => 'NISA NUR MALASARI, S.Kom.',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197011132010011001',
                'nama' => 'ARYAMAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197712122014111001',
                'nama' => 'YADI PERMANA',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197802052025212002',
                'nama' => 'ATI SUMIATI',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197608072025211053',
                'nama' => 'AGUS DEWANTO',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '198101232025211049',
                'nama' => 'ANDRI JHON ECA',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '200102242025211018',
                'nama' => 'MUHAMMAD PEBRIAN GUMELAR',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197707182025212018',
                'nama' => 'YULI SUMARYANI',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '200203302025212012',
                'nama' => 'ALDA SINTIA RAHAYU',
                'jabatan' => 'Operator Layanan Operasional',
                'unit_kerja' => 'Kelurahan Pataruman',
                'status_kepegawaian' => 'PPPK',
            ],
            [
                'nip' => '197611102010011021',
                'nama' => 'EA RUSTANDI, S.IP',
                'jabatan' => 'Lurah',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197303142007012004',
                'nama' => 'LILIES SALAMAH, S.Sos',
                'jabatan' => 'Sekretaris Kelurahan',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197109222008011002',
                'nama' => 'KANA SUKANA, SE',
                'jabatan' => 'Kasi Ekonomi',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197608062014092001',
                'nama' => 'AMIE AGUSTIANI DEWI',
                'jabatan' => 'Kasi Pemerintahan',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197807102014111002',
                'nama' => 'JEMMY MULYADI, SE',
                'jabatan' => 'Penelaah Teknis Kebijakan',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197104291993032005',
                'nama' => 'IIN MAULINA INDRAYANI',
                'jabatan' => 'Penelaah Teknis Kebijakan',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '199401072025042001',
                'nama' => 'IMA ANISA YUNIAR, ST.',
                'jabatan' => 'Penata Kelola Sistem Dan Teknologi Informasi',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '197411152010011002',
                'nama' => 'DONI KUSMAWAN',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
            [
                'nip' => '198212122010012003',
                'nama' => 'YATI SOLIHAT',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Kelurahan Sukakarya',
                'status_kepegawaian' => 'ASN',
            ],
        ];
    }
}
