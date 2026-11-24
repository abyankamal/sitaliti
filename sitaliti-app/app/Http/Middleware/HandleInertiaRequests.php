<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'unit_kerja' => $user->unitKerja ? [
                        'id' => $user->unitKerja->id,
                        'kode_unit' => $user->unitKerja->kode_unit,
                        'nama_unit' => $user->unitKerja->nama_unit,
                        'tipe' => $user->unitKerja->tipe,
                    ] : null,
                    'is_kelurahan_scoped' => $user->isKelurahanScoped(),
                    'is_skpd_wide' => $user->isSkpdWide(),
                    'is_kelurahan_bpp' => $user->isKelurahanBpp(),
                    'can_input_cross_unit' => $user->canInputCrossUnit(),
                    'can_auto_finalize_receipt' => $user->canAutoFinalizeReceipt(),
                    'can_verify_receipt' => $user->canVerifyReceipt(),
                    'is_read_only' => $user->isReadOnlyRole(),
                    'assigned_sub_kegiatans' => $user->getAssignedSubkegiatanCodes(),
                ]) : null,
            ],
            'flash' => [
                'success' => fn () => $this->flashPayload($request, 'success'),
                'error' => fn () => $this->flashPayload($request, 'error'),
                'warning' => fn () => $this->flashPayload($request, 'warning'),
                'info' => fn () => $this->flashPayload($request, 'info'),
                'status' => fn () => $this->flashPayload($request, 'status'),
            ],
        ];
    }

    /**
     * Build a deduplicable flash payload with a unique ID per occurrence.
     *
     * @return array{id: string, message: string}|null
     */
    private function flashPayload(Request $request, string $key): ?array
    {
        $message = $request->session()->get($key);

        if (! is_string($message) || $message === '') {
            return null;
        }

        return [
            'id' => (string) Str::uuid(),
            'message' => $message,
        ];
    }
}
