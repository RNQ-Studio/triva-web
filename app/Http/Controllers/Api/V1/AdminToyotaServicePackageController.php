<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreToyotaServicePackageRequest;
use App\Http\Requests\Api\V1\UpdateToyotaServicePackageRequest;
use App\Http\Resources\Api\V1\AdminToyotaServicePackageResource;
use App\Models\ToyotaServicePackage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tabel budget jasa & part servis berkala (T-Care) yang menjadi sumber
 * simulasi biaya servis, dikelola dari Admin Panel aplikasi (revisi
 * 6 Oktober 2026).
 */
class AdminToyotaServicePackageController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ToyotaServicePackage::class);

        $packages = ToyotaServicePackage::query()
            // Paket umum (semua model) didahulukan, lalu per model.
            ->orderByRaw('vehicle_model IS NOT NULL')
            ->orderByRaw('lower(vehicle_model)')
            ->orderBy('km_interval')
            ->get();

        return ApiResponse::success(AdminToyotaServicePackageResource::collection($packages));
    }

    public function store(StoreToyotaServicePackageRequest $request): JsonResponse
    {
        $package = ToyotaServicePackage::query()->create($request->packageAttributes());

        return ApiResponse::success(
            new AdminToyotaServicePackageResource($package->refresh()),
            'Paket servis ditambahkan.',
            201,
        );
    }

    public function update(
        UpdateToyotaServicePackageRequest $request,
        ToyotaServicePackage $package,
    ): JsonResponse {
        $package->update($request->packageAttributes($package));

        return ApiResponse::success(
            new AdminToyotaServicePackageResource($package->refresh()),
            'Paket servis diperbarui.',
        );
    }

    public function destroy(ToyotaServicePackage $package): JsonResponse
    {
        $this->authorize('delete', $package);

        $package->delete();

        return ApiResponse::success(null, 'Paket servis dihapus.');
    }
}
