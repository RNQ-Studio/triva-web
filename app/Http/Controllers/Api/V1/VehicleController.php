<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVehicleRequest;
use App\Http\Requests\Api\V1\UpdateVehicleRequest;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        /** @var User $user */
        $user = $request->user();
        $vehicles = $user->vehicles()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success(VehicleResource::collection($vehicles));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $vehicle = new Vehicle($request->vehicleData());
        $vehicle->user()->associate($user);
        $vehicle->save();

        return ApiResponse::success(new VehicleResource($vehicle), 'Kendaraan berhasil disimpan.', 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return ApiResponse::success(new VehicleResource($vehicle));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->vehicleData());

        return ApiResponse::success(new VehicleResource($vehicle->refresh()), 'Kendaraan berhasil diperbarui.');
    }
}
