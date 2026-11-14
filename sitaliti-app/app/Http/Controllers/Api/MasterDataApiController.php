<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use App\Models\MasterChecklistItem;
use Illuminate\Http\JsonResponse;

class MasterDataApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Mengembalikan daftar item MasterChecklistItem aktif berdasarkan kategori belanja.
     */
    public function checklist(string $category): JsonResponse
    {
        $normalizedCategory = match (strtoupper($category)) {
            'B.1', 'B1', 'B1_ATK' => 'B.1',
            'B.2', 'B2', 'B2_MAMIN' => 'B.2',
            'B.3', 'B3', 'B3_HONOR' => 'B.3',
            'B.4', 'B4', 'B4_PERJADIN' => 'B.4',
            'B.5', 'B5', 'B5_PEMELIHARAAN', 'B5_MODAL' => 'B.5',
            'B.6', 'B6', 'B6_MODAL', 'B6_LAINNYA' => 'B.6',
            default => $category,
        };

        $items = MasterChecklistItem::where('kategori_belanja', $normalizedCategory)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get();

        return $this->successResponse($items, 'Daftar master checklist berhasil dimuat');
    }
}
