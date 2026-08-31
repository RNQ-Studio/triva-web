<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminPlayStoreInstallsRequest;
use App\Services\PlayStoreInstallsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPlayStoreInstallsController extends Controller
{
    public function __construct(
        private readonly PlayStoreInstallsService $installsService,
    ) {}

    public function __invoke(AdminPlayStoreInstallsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->installsService->summarize());
    }
}
