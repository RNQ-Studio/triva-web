<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordVisitRequest;
use App\Services\VisitTrackingService;
use App\Support\ApiResponse;
use App\Support\Enums\VisitSource;
use Illuminate\Http\JsonResponse;

class VisitController extends Controller
{
    public function __construct(
        private readonly VisitTrackingService $trackingService,
    ) {}

    public function store(RecordVisitRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $visit = $this->trackingService->record(
            VisitSource::from((string) $validated['source']),
            (string) $validated['visit_id'],
            isset($validated['app_version']) ? (string) $validated['app_version'] : null,
            isset($validated['app_build']) ? (string) $validated['app_build'] : null,
        );

        return ApiResponse::success(
            [
                'accepted' => true,
                'source' => $visit->source->value,
                'recorded_at' => $visit->occurred_at->toIso8601String(),
            ],
            'Visit accepted.',
            202,
        );
    }
}
