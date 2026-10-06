<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InfoPopupResource;
use App\Models\InfoPopup;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InfoPopupController extends Controller
{
    /**
     * Popup informasi yang sedang tayang, berurutan sesuai urutan tampil.
     *
     * Jeda tayang ulang (`interval_hours`) dihitung aplikasi per perangkat.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $popups = InfoPopup::query()
            ->running()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return ApiResponse::success(InfoPopupResource::collection($popups));
    }
}
