<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListVehicleVariantsRequest;
use App\Http\Resources\Api\V1\VehicleVariantResource;
use App\Models\VehicleModel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VehicleModelController extends Controller
{
    public function variants(
        ListVehicleVariantsRequest $request,
        VehicleModel $vehicleModel,
    ): JsonResponse {
        abort_unless(
            $vehicleModel->is_active && $vehicleModel->vehicleMake->is_active,
            404,
        );

        $variants = $vehicleModel->vehicleVariants()
            ->active()
            ->availableInYear($request->integer('year'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            VehicleVariantResource::collection($variants),
        );
    }
}
