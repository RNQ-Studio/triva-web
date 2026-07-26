<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleMakeResource;
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
}
