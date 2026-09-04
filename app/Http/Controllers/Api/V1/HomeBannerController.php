<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HomeBannerResource;
use App\Models\HomeBanner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HomeBannerController extends Controller
{
    /**
     * Banner iklan yang sedang tayang untuk slider beranda.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $banners = HomeBanner::query()
            ->running()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return ApiResponse::success(
            HomeBannerResource::collection($banners),
        );
    }
}
