<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSppdAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', 'integer', 'exists:master_unit_kerja,id'],
            'anggaran_rekening_id' => ['nullable', 'integer', 'exists:anggaran_rekening,id'],
            'nomor_surat_tugas' => ['nullable', 'string', 'max:100'],
            'maksud_perjalanan' => ['required', 'string', 'max:2000'],
            'alat_angkut' => ['nullable', 'string', 'max:150'],
            'tempat_berangkat' => ['required', 'string', 'max:200'],
            'tempat_tujuan' => ['required', 'string', 'max:255'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'pemberi_perintah_nama' => ['nullable', 'string', 'max:200'],
            'pemberi_perintah_nip' => ['nullable', 'string', 'max:30'],
            'pemberi_perintah_jabatan' => ['nullable', 'string', 'max:150'],
            'dikeluarkan_di' => ['nullable', 'string', 'max:100'],
            'tanggal_penetapan' => ['nullable', 'date'],
            'laporan_hasil' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(['DRAFT', 'TERBIT', 'SELESAI'])],

            'peserta' => ['required', 'array', 'min:1'],
            'peserta.*.pegawai_id' => ['required', 'integer', 'exists:pegawai,id', 'distinct'],
            'peserta.*.urutan' => ['nullable', 'integer', 'min:1'],
            'peserta.*.nomor_spd' => ['nullable', 'string', 'max:100'],
            'peserta.*.tingkat_biaya' => ['nullable', 'string', 'max:100'],
            'peserta.*.pengikut' => ['nullable', 'array'],
            'peserta.*.pengikut.*.nama' => ['required_with:peserta.*.pengikut', 'string', 'max:200'],
            'peserta.*.pengikut.*.tanggal_lahir' => ['nullable', 'string', 'max:50'],
            'peserta.*.pengikut.*.keterangan' => ['nullable', 'string', 'max:150'],
            'peserta.*.dibayar_semula' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.keterangan_lain' => ['nullable', 'string', 'max:500'],

            'peserta.*.biaya_items' => ['nullable', 'array'],
            'peserta.*.biaya_items.*.uraian_biaya' => ['required', 'string', 'max:255'],
            'peserta.*.biaya_items.*.nominal' => ['required', 'numeric', 'min:0'],
            'peserta.*.biaya_items.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
