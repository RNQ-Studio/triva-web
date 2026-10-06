<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PartnerLogoResource;
use App\Models\PartnerLogo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PartnerLogoController extends Controller
{
    /**
     * Logo "Mitra resmi" yang aktif untuk beranda aplikasi.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $logos = PartnerLogo::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return ApiResponse::success(PartnerLogoResource::collection($logos));
    }
}
