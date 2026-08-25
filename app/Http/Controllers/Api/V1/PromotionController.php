<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PromotionResource;
use App\Models\Promotion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    /**
     * Promo yang sedang berjalan untuk banner dan pop-up halaman depan.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $promotions = Promotion::query()
            ->running()
            ->orderBy('sort_order')
            ->orderByDesc('starts_on')
            ->limit(20)
            ->get();

        return ApiResponse::success(
            PromotionResource::collection($promotions),
        );
    }
}
