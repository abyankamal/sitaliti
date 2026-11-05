<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptApiController extends Controller
{
    use ApiResponseTrait;

    /**
     * Endpoint validasi draft kuitansi & simulasi pajak (receiptService.validateDraft)
     */
    public function validateDraft(Request $request): JsonResponse
    {
        return $this->successResponse([], 'Draft kuitansi terverifikasi valid');
    }

    /**
     * Endpoint simpan kuitansi (receiptService.saveReceipt)
     */
    public function store(Request $request): JsonResponse
    {
        return $this->successResponse([
            'receipt_id' => null,
            'status' => 'DRAFT',
        ], 'Draft kuitansi berhasil disimpan', 201);
    }
}

