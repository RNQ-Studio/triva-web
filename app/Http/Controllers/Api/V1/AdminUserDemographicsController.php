<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminAnalyticsRequest;
use App\Services\UserDemographicsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserDemographicsController extends Controller
{
    public function __construct(
        private readonly UserDemographicsService $demographicsService,
    ) {}

    public function __invoke(AdminAnalyticsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->demographicsService->summarize());
    }
}
