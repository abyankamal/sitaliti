<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertSppdToKuitansiRequest;
use App\Http\Requests\StoreSppdAssignmentRequest;
use App\Http\Requests\UpdateSppdAssignmentRequest;
use App\Http\Resources\ApiResponseTrait;
use App\Models\SppdAssignment;
use App\Models\User;
use App\Services\SppdAssignmentService;
use App\Services\SppdScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SppdAssignmentApiController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SppdAssignmentService $assignmentService,
        protected SppdScheduleService $scheduleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isSkpdWide() && $user->unit_kerja_id === null)) {
            return $this->errorResponse('FORBIDDEN', 'Akun Anda belum terhubung dengan unit kerja.', null, 403);
        }

        $filters = $request->only(['unit_kerja_id', 'status', 'search']);
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->successResponse(
            $this->assignmentService->listForUser($user, $filters, $perPage),
            'Daftar penugasan SPPD berhasil diambil'
        );
    }

    public function store(StoreSppdAssignmentRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->canMutate($user)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang membuat penugasan SPPD.', null, 403);
        }

        try {
            $assignment = $this->assignmentService->create($user, $request->validated());

            return $this->successResponse($assignment, 'Penugasan SPPD berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('SPPD_SCHEDULE_CONFLICT', $e->getMessage(), $e->errors(), 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assignment = SppdAssignment::with([
            'unitKerja',
            'rekening',
            'kuitansi.rekening',
            'peserta.pegawai',
            'peserta.biayaItems',
        ])->find($id);

        if (! $assignment) {
            return $this->errorResponse('SPPD_NOT_FOUND', 'Penugasan SPPD tidak ditemukan.', null, 404);
        }

        if (! $this->canAccess($request->user(), $assignment)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang mengakses penugasan SPPD unit kerja lain.', null, 403);
        }

        return $this->successResponse($assignment, 'Detail penugasan SPPD berhasil diambil');
    }

    public function update(UpdateSppdAssignmentRequest $request, int $id): JsonResponse
    {
        $assignment = SppdAssignment::find($id);
        if (! $assignment) {
            return $this->errorResponse('SPPD_NOT_FOUND', 'Penugasan SPPD tidak ditemukan.', null, 404);
        }

        if (! $this->canMutate($request->user(), $assignment)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang mengubah penugasan SPPD ini.', null, 403);
        }

        try {
            $updated = $this->assignmentService->update($assignment, $request->validated());

            return $this->successResponse($updated, 'Penugasan SPPD berhasil diperbarui');
        } catch (ValidationException $e) {
            return $this->errorResponse('SPPD_UPDATE_REJECTED', $e->getMessage(), $e->errors(), 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $assignment = SppdAssignment::find($id);
        if (! $assignment) {
            return $this->errorResponse('SPPD_NOT_FOUND', 'Penugasan SPPD tidak ditemukan.', null, 404);
        }

        if (! $this->canMutate($request->user(), $assignment)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang menghapus penugasan SPPD ini.', null, 403);
        }

        try {
            $this->assignmentService->delete($assignment);

            return $this->successResponse(null, 'Penugasan SPPD berhasil dihapus');
        } catch (ValidationException $e) {
            return $this->errorResponse('SPPD_DELETE_REJECTED', $e->getMessage(), $e->errors(), 422);
        }
    }

    public function checkConflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude_sppd_assignment_id' => ['nullable', 'integer'],
            'exclude_kuitansi_id' => ['nullable', 'integer'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'peserta' => ['required', 'array', 'min:1'],
            'peserta.*.pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
        ]);

        $payload = $this->assignmentService->buildSchedulePayload($validated['peserta'], $validated);
        $conflicts = $this->scheduleService->findConflicts(
            $payload,
            $validated['exclude_kuitansi_id'] ?? null,
            $validated['exclude_sppd_assignment_id'] ?? null
        );

        return $this->successResponse([
            'has_conflicts' => ! empty($conflicts),
            'conflicts' => $conflicts,
        ], 'Pemeriksaan bentrok jadwal SPPD selesai');
    }

    public function convertToKuitansi(ConvertSppdToKuitansiRequest $request, int $id): JsonResponse
    {
        $assignment = SppdAssignment::find($id);
        if (! $assignment) {
            return $this->errorResponse('SPPD_NOT_FOUND', 'Penugasan SPPD tidak ditemukan.', null, 404);
        }

        if (! $this->canMutate($request->user(), $assignment)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang mengonversi penugasan SPPD ini.', null, 403);
        }

        try {
            $converted = $this->assignmentService->convertToKuitansi($request->user(), $assignment, $request->validated());

            return $this->successResponse($converted, 'Penugasan SPPD berhasil dikonversi ke Kuitansi SPJ');
        } catch (ValidationException $e) {
            return $this->errorResponse('SPPD_CONVERT_REJECTED', $e->getMessage(), $e->errors(), 422);
        }
    }

    public function linkKuitansi(Request $request, int $id): JsonResponse
    {
        $assignment = SppdAssignment::find($id);
        if (! $assignment) {
            return $this->errorResponse('SPPD_NOT_FOUND', 'Penugasan SPPD tidak ditemukan.', null, 404);
        }

        if (! $this->canMutate($request->user(), $assignment)) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak berwenang menautkan penugasan SPPD ini.', null, 403);
        }

        $validated = $request->validate([
            'kuitansi_id' => ['required', 'integer', 'exists:kuitansi,id'],
        ]);

        try {
            $linked = $this->assignmentService->linkExistingKuitansi($assignment, (int) $validated['kuitansi_id']);

            return $this->successResponse($linked, 'Penugasan SPPD berhasil ditautkan ke Kuitansi SPJ');
        } catch (ValidationException $e) {
            return $this->errorResponse('SPPD_LINK_REJECTED', $e->getMessage(), $e->errors(), 422);
        }
    }

    private function canAccess(?User $user, SppdAssignment $assignment): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSkpdWide()) {
            return true;
        }

        return $user->unit_kerja_id !== null
            && (int) $assignment->unit_kerja_id === (int) $user->unit_kerja_id;
    }

    private function canMutate(?User $user, ?SppdAssignment $assignment = null): bool
    {
        if (! $user || $user->isReadOnlyRole()) {
            return false;
        }

        if ($user->isSkpdWide()) {
            return true;
        }

        if ($user->unit_kerja_id === null) {
            return false;
        }

        return $assignment === null || (int) $assignment->unit_kerja_id === (int) $user->unit_kerja_id;
    }
}
