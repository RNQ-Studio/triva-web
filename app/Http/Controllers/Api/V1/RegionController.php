<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProvinceResource;
use App\Models\Region;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        $indonesiaId = Region::query()
            ->countries()
            ->where('code', 'ID')
            ->value('id');

        if ($indonesiaId === null) {
            return ApiResponse::success([], 'Master wilayah belum tersedia.');
        }

        $provinces = Region::query()
            ->states()
            ->where('parent_id', $indonesiaId)
            ->select(['id', 'code', 'name'])
            ->with([
                'childCities' => fn ($query) => $query
                    ->select(['id', 'parent_id', 'code', 'name'])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->limit(100)
            ->get();

        return ApiResponse::success(ProvinceResource::collection($provinces));
    }
}
