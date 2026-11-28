<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertSppdToKuitansiRequest extends FormRequest
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
            'anggaran_rekening_id' => ['required', 'integer', 'exists:anggaran_rekening,id'],
            'bulan_anggaran' => ['required', 'integer', 'between:1,12'],
            'tanggal_transaksi' => ['nullable', 'date'],
            'metode_pembayaran' => ['nullable', 'string', Rule::in(['NON_TUNAI_IBC', 'TUNAI_UP'])],
            'uraian' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
