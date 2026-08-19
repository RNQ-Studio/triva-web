<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminVisitStatisticsRequest;
use App\Services\VisitStatisticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminVisitStatisticsController extends Controller
{
    public function __construct(
        private readonly VisitStatisticsService $statisticsService,
    ) {}

    public function __invoke(AdminVisitStatisticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->statisticsService->summarize());
    }
}
