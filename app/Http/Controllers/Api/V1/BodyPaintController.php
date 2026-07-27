<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachBodyPaintPhotosRequest;
use App\Http\Requests\Api\V1\BodyPaintEstimateDecisionRequest;
use App\Http\Requests\Api\V1\ListBodyPaintEstimatesRequest;
use App\Http\Requests\Api\V1\RequestBodyPaintBookingRequest;
use App\Http\Requests\Api\V1\StoreBodyPaintEstimateRequest;
use App\Http\Requests\Api\V1\SubmitBodyPaintEstimateRequest;
use App\Http\Requests\Api\V1\UpdateBodyPaintDamagesRequest;
use App\Http\Resources\Api\V1\BodyPaintEstimateResource;
use App\Http\Resources\Api\V1\ToyotaServiceBookingResource;
use App\Http\Resources\Api\V1\ToyotaServiceLocationResource;
use App\Models\BodyPaintEstimate;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Services\BodyPaintEstimateService;
use App\Support\ApiResponse;
use App\Support\BodyPaintCatalog;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BodyPaintController extends Controller
{
    public function __construct(
        private readonly BodyPaintEstimateService $estimates,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $this->authorize('create', BodyPaintEstimate::class);
        $locations = ToyotaServiceLocation::query()
            ->effective()
            ->where('supports_workshop', true)
            ->orderBy('name')
            ->get();
        $serviceType = ToyotaServiceType::query()
            ->effective()
            ->where('code', 'body-paint')
            ->where('supports_workshop', true)
            ->first();

        return ApiResponse::success([
            'panels' => collect(BodyPaintCatalog::PANELS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'damage_types' => collect(BodyPaintCatalog::DAMAGE_TYPES)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                    'is_high_risk' => in_array(
                        $value,
                        BodyPaintCatalog::HIGH_RISK_DAMAGE_TYPES,
                        true,
                    ),
                ])
                ->values(),
            'severities' => collect(BodyPaintSeverity::cases())
                ->map(fn (BodyPaintSeverity $severity): array => [
                    'value' => $severity->value,
                    'label' => $severity->label(),
                ]),
            'work_types' => collect(BodyPaintWorkType::cases())
                ->map(fn (BodyPaintWorkType $workType): array => [
                    'value' => $workType->value,
                    'label' => $workType->label(),
                ]),
            'photo_upload' => [
                'type' => 'body-paint-estimate-photo',
                'allowed_extensions' => [
                    'jpeg',
                    'jpg',
                    'png',
                    'heic',
                    'heif',
                ],
                'maximum_size_kb' => 10240,
                'maximum_count' => (int) config(
                    'body_paint.maximum_photos',
                    10,
                ),
                'minimum_close_per_damage' => 1,
                'minimum_context' => 1,
            ],
            'service_locations' => ToyotaServiceLocationResource::collection(
                $locations,
            ),
            'booking' => [
                'request_to_confirm' => true,
                'service_type_id' => $serviceType?->getKey(),
                'availability_endpoint' => '/api/v1/toyota-service/availability',
            ],
            'requires_physical_inspection' => true,
            'disclaimer' => config('body_paint.disclaimer'),
        ]);
    }

    public function index(
        ListBodyPaintEstimatesRequest $request,
    ): JsonResponse {
        $this->authorize('viewAny', BodyPaintEstimate::class);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            BodyPaintEstimateResource::collection(
                $user->bodyPaintEstimates()
                    ->with([
                        'vehicle.vehicleMake',
                        'vehicle.vehicleModel',
                        'serviceLocation',
                        'damages.photos.asset',
                        'photos.asset',
                        'items.damage',
                        'currentPublishedVersion.publisher',
                        'statusHistories' => fn ($query) => $query
                            ->where('user_visible', true)
                            ->oldest('created_at'),
                        'booking.serviceLocation',
                        'booking.serviceType',
                    ])
                    ->latest('updated_at')
                    ->paginate($request->integer('per_page', 20)),
            ),
        );
    }

    public function store(
        StoreBodyPaintEstimateRequest $request,
    ): JsonResponse {
        $this->authorize('create', BodyPaintEstimate::class);
        /** @var User $user */
        $user = $request->user();
        $result = $this->estimates->create($user, $request->validated());

        return ApiResponse::success(
            new BodyPaintEstimateResource($result['estimate']),
            $result['replayed']
                ? 'Draft estimasi yang sama ditemukan.'
                : 'Draft estimasi berhasil dibuat.',
            $result['replayed'] ? 200 : 201,
            ['idempotent_replay' => $result['replayed']],
        );
    }

    public function show(BodyPaintEstimate $estimate): JsonResponse
    {
        $this->authorize('view', $estimate);

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->loadCustomerRelations($estimate),
            ),
        );
    }

    public function updateDamages(
        UpdateBodyPaintDamagesRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->updateDamages(
                    $estimate,
                    $user,
                    $request->validated('damages'),
                ),
            ),
            'Data kerusakan berhasil disimpan.',
        );
    }

    public function attachPhotos(
        AttachBodyPaintPhotosRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->attachPhotos(
                    $estimate,
                    $user,
                    $request->validated('photos'),
                ),
            ),
            'Foto kerusakan berhasil disimpan.',
        );
    }

    public function submit(
        SubmitBodyPaintEstimateRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->submit($estimate, $user),
            ),
            'Permintaan estimasi berhasil dikirim.',
        );
    }

    public function resubmit(
        SubmitBodyPaintEstimateRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->submit($estimate, $user, true),
            ),
            'Perbaikan foto berhasil dikirim ulang.',
        );
    }

    public function decision(
        BodyPaintEstimateDecisionRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new BodyPaintEstimateResource(
                $this->estimates->decide(
                    $estimate,
                    $user,
                    $request->validated(),
                ),
            ),
            $request->string('decision')->toString() === 'accept'
                ? 'Estimasi diterima.'
                : 'Keputusan tidak melanjutkan tersimpan.',
        );
    }

    public function requestBooking(
        RequestBodyPaintBookingRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $result = $this->estimates->requestBooking(
            $estimate,
            $user,
            $request->validated(),
        );

        return ApiResponse::success([
            'estimate' => new BodyPaintEstimateResource($result['estimate']),
            'booking' => new ToyotaServiceBookingResource($result['booking']),
        ], $result['replayed']
            ? 'Booking yang sama ditemukan.'
            : 'Booking Body & Paint berhasil diminta.', 200, [
                'idempotent_replay' => $result['replayed'],
            ]);
    }
}
