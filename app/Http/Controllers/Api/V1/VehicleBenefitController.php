<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckVehicleBenefitRequest;
use App\Services\VehicleBenefitLookupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VehicleBenefitController extends Controller
{
    /**
     * Pemeriksaan mandiri No. Rangka terhadap kampanye SSC dan sisa T-Care.
     */
    public function check(
        CheckVehicleBenefitRequest $request,
        VehicleBenefitLookupService $lookup,
    ): JsonResponse {
        return ApiResponse::success(
            $lookup->check(
                $request->string('vin')->toString(),
                $request->filled('year') ? $request->integer('year') : null,
            ),
            'Pemeriksaan selesai.',
        );
    }
}
