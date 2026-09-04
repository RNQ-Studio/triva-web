<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalesContactResource;
use App\Models\SalesContact;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SalesContactController extends Controller
{
    /**
     * Daftar sales dan supervisor aktif yang bisa dihubungi pelanggan.
     *
     * Supervisor diurutkan paling depan supaya aplikasi mudah memilih tujuan
     * bawaan ("belum ada sales") tanpa logika tambahan.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $contacts = SalesContact::query()
            ->active()
            ->orderByRaw("CASE WHEN role = 'spv' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            SalesContactResource::collection($contacts),
        );
    }
}
