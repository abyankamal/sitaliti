<?php

namespace App\Http\Requests\Receipts;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi ketat boundary untuk kuitansi belanja.
     */
    public function rules(): array
    {
        return [
            'anggaran_rekening_id' => ['required', 'string'],
            'bulan_anggaran' => ['required', 'integer', 'between:1,12'],
            'nomor_bukti_toko' => ['required', 'string', 'max:100'],
            'tanggal_transaksi' => ['required', 'date'],
            'uraian_belanja' => ['required', 'string'],
            'nama_penerima' => ['required', 'string', 'max:200'],
            'npwp_penerima' => ['nullable', 'string', 'max:30'],
            'metode_pembayaran' => ['required', 'in:NON_TUNAI_IBC,TUNAI_UP'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['required', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ];
    }
}
