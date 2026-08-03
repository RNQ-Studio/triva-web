<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppraisalDecisionRequest;
use App\Http\Requests\Api\V1\AttachAppraisalPhotosRequest;
use App\Http\Requests\Api\V1\ResubmitAppraisalRequest;
use App\Http\Requests\Api\V1\ScheduleAppraisalInspectionRequest;
use App\Http\Requests\Api\V1\StoreAppraisalRequest;
use App\Http\Requests\Api\V1\SubmitAppraisalRequest;
use App\Http\Requests\Api\V1\UpdateAppraisalConditionRequest;
use App\Http\Resources\Api\V1\AppraisalResource;
use App\Models\Appraisal;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppraisalService;
use App\Support\ApiResponse;
use App\Support\Enums\AppraisalDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppraisalController extends Controller
{
    public function __construct(
        private readonly AppraisalService $appraisals,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appraisal::class);

        /** @var User $user */
        $user = $request->user();
        $items = $user->appraisals()
            ->with([
                'vehicle',
                'latestResult.comparables',
                'latestResult.marketEstimate',
            ])
            ->latest('updated_at')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success(AppraisalResource::collection($items));
    }

    public function store(StoreAppraisalRequest $request): JsonResponse
    {
        $this->authorize('create', Appraisal::class);

        /** @var User $user */
        $user = $request->user();
        $vehicle = Vehicle::query()->findOrFail($request->string('vehicle_id')->toString());
        $key = $request->validated('idempotency_key');
        $result = $this->appraisals->createDraft(
            $user,
            $vehicle,
            is_string($key) ? $key : null,
        );

        return ApiResponse::success(
            new AppraisalResource($result['appraisal']),
            $result['replayed']
                ? 'Draft appraisal yang sama ditemukan.'
                : 'Draft appraisal dibuat.',
            $result['replayed'] ? 200 : 201,
            ['idempotent_replay' => $result['replayed']],
        );
    }

    public function show(Appraisal $appraisal): JsonResponse
    {
        $this->authorize('view', $appraisal);

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->loadCustomerRelations($appraisal)
        ));
    }

    public function updateCondition(
        UpdateAppraisalConditionRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->updateCondition($appraisal, $request->validated(), $user)
        ), 'Kondisi kendaraan tersimpan.');
    }

    public function attachPhotos(
        AttachAppraisalPhotosRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->attachPhotos($appraisal, $request->validated('photos'), $user)
        ), 'Foto appraisal tersimpan.');
    }

    public function submit(SubmitAppraisalRequest $request, Appraisal $appraisal): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $submitted = $this->appraisals->submit(
            $appraisal,
            $user,
            $request->string('idempotency_key')->toString(),
            $request->boolean('marketing_consent'),
        );

        return ApiResponse::success(new AppraisalResource($submitted), 'Appraisal berhasil dikirim.');
    }

    public function resubmit(
        ResubmitAppraisalRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->resubmit($appraisal, $user)
        ), 'Perbaikan berhasil dikirim.');
    }

    public function decision(
        AppraisalDecisionRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $decision = AppraisalDecision::from($request->string('decision')->toString());

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->decide($appraisal, $user, $decision)
        ), 'Keputusan berhasil disimpan.');
    }

    public function scheduleInspection(
        ScheduleAppraisalInspectionRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new AppraisalResource(
            $this->appraisals->scheduleInspection(
                $appraisal,
                $user,
                $request->date('scheduled_at')->toIso8601String(),
                $request->string('notes')->toString() ?: null,
            )
        ), 'Inspeksi berhasil dijadwalkan.');
    }
}
