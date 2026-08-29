<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordMenuUsageRequest;
use App\Models\User;
use App\Services\MenuUsageTrackingService;
use App\Support\ApiResponse;
use App\Support\Enums\VisitSource;
use Illuminate\Http\JsonResponse;

class MenuUsageController extends Controller
{
    public function __construct(
        private readonly MenuUsageTrackingService $trackingService,
    ) {}

    public function store(RecordMenuUsageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        /** @var User|null $user */
        $user = $request->user();

        $event = $this->trackingService->record(
            (string) $validated['menu_key'],
            VisitSource::from((string) $validated['source']),
            $user,
            isset($validated['app_version']) ? (string) $validated['app_version'] : null,
            isset($validated['app_build']) ? (string) $validated['app_build'] : null,
        );

        return ApiResponse::success(
            [
                'accepted' => true,
                'menu_key' => $event->menu_key,
                'recorded_at' => $event->occurred_at->toIso8601String(),
            ],
            'Menu usage accepted.',
            202,
        );
    }
}
