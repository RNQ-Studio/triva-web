<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleMakeResource;
use App\Http\Resources\Api\V1\VehicleModelResource;
use App\Models\VehicleMake;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VehicleMakeController extends Controller
{
    public function index(): JsonResponse
    {
        $makes = VehicleMake::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(VehicleMakeResource::collection($makes));
    }

    public function models(VehicleMake $vehicleMake): JsonResponse
    {
        abort_unless($vehicleMake->is_active, 404);

        $models = $vehicleMake->vehicleModels()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(VehicleModelResource::collection($models));
    }
}
