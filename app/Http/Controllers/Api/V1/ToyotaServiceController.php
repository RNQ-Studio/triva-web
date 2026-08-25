<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptToyotaServiceAlternativeRequest;
use App\Http\Requests\Api\V1\CancelToyotaServiceBookingRequest;
use App\Http\Requests\Api\V1\ListToyotaServiceBookingsRequest;
use App\Http\Requests\Api\V1\MaintenanceEstimateRequest;
use App\Http\Requests\Api\V1\RejectToyotaServiceAlternativeRequest;
use App\Http\Requests\Api\V1\RescheduleToyotaServiceBookingRequest;
use App\Http\Requests\Api\V1\StoreToyotaServiceBookingRequest;
use App\Http\Requests\Api\V1\ToyotaServiceAvailabilityRequest;
use App\Http\Resources\Api\V1\ToyotaServiceBookingResource;
use App\Http\Resources\Api\V1\ToyotaServiceLocationResource;
use App\Http\Resources\Api\V1\ToyotaServiceTypeResource;
use App\Http\Resources\Api\V1\ToyotaThsCoverageResource;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\ToyotaThsCoverage;
use App\Models\User;
use App\Services\ToyotaMaintenanceEstimateService;
use App\Services\ToyotaServiceAvailabilityService;
use App\Services\ToyotaServiceBookingCreationService;
use App\Services\ToyotaServiceBookingService;
use App\Support\ApiResponse;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToyotaServiceController extends Controller
{
    public function __construct(
        private readonly ToyotaServiceAvailabilityService $availabilityService,
        private readonly ToyotaServiceBookingCreationService $bookingCreationService,
        private readonly ToyotaServiceBookingService $bookingService,
    ) {}

    /**
     * Simulasi biaya servis berkala berdasarkan paket reguler cabang.
     *
     * Diminta notulensi 19 Agustus 2026 agar pelanggan tahu perkiraan biaya
     * sebelum memutuskan booking.
     */
    public function maintenanceEstimate(
        MaintenanceEstimateRequest $request,
        ToyotaMaintenanceEstimateService $estimates,
    ): JsonResponse {
        return ApiResponse::success(
            $estimates->estimate(
                $request->filled('vehicle_model')
                    ? $request->string('vehicle_model')->toString()
                    : null,
                $request->filled('mileage') ? $request->integer('mileage') : null,
            ),
        );
    }

    public function options(Request $request): JsonResponse
    {
        $locations = ToyotaServiceLocation::query()
            ->effective()
            ->orderBy('name')
            ->get();
        $serviceTypes = ToyotaServiceType::query()
            ->effective()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $thsLocationIds = $locations
            ->filter(fn (ToyotaServiceLocation $location): bool => $location->supports_ths)
            ->modelKeys();
        $coverage = ToyotaThsCoverage::query()
            ->operational()
            ->whereIn('service_location_id', $thsLocationIds)
            ->orderBy('city')
            ->get();
        $thsAvailable = $serviceTypes->contains(
            fn (ToyotaServiceType $type): bool => $type->supports_ths
        ) && $locations->contains(
            fn (ToyotaServiceLocation $location): bool => $location->supports_ths
                && $coverage->contains(
                    fn (ToyotaThsCoverage $item): bool => $item->service_location_id
                        === $location->getKey()
                )
        );
        $workshopAvailable = $locations->contains(
            fn (ToyotaServiceLocation $location): bool => $location->supports_workshop
        ) && $serviceTypes->contains(
            fn (ToyotaServiceType $type): bool => $type->supports_workshop
        );

        return ApiResponse::success([
            'timezone' => $locations->isNotEmpty()
                ? $locations->first()->timezone
                : 'Asia/Jakarta',
            'request_to_confirm' => true,
            'notice' => 'Jadwal pilihan adalah preferensi dan belum dikonfirmasi.',
            'fulfillment_types' => collect(ToyotaServiceFulfillmentType::cases())
                ->map(fn (ToyotaServiceFulfillmentType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'is_available' => match ($type) {
                        ToyotaServiceFulfillmentType::Workshop => $workshopAvailable,
                        ToyotaServiceFulfillmentType::Ths => $thsAvailable,
                    },
                    'unavailable_reason' => match ($type) {
                        ToyotaServiceFulfillmentType::Workshop => $workshopAvailable
                            ? null
                            : 'Layanan bengkel belum tersedia.',
                        ToyotaServiceFulfillmentType::Ths => $thsAvailable
                            ? null
                            : 'Cakupan operasional Toyota Home Service belum tersedia.',
                    },
                ])
                ->values()
                ->all(),
            'contact_channels' => collect(ToyotaServiceContactChannel::cases())
                ->map(fn (ToyotaServiceContactChannel $channel): array => [
                    'value' => $channel->value,
                    'label' => $channel->label(),
                ])
                ->values()
                ->all(),
            'locations' => ToyotaServiceLocationResource::collection($locations)->resolve($request),
            'service_types' => ToyotaServiceTypeResource::collection($serviceTypes)->resolve($request),
            'ths_coverage' => ToyotaThsCoverageResource::collection($coverage)->resolve($request),
            'photo_upload' => [
                'type' => 'toyota-service-photo',
                'max_files' => 5,
                'max_size_kb' => 10240,
                'mime_types' => ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
                'is_protected' => true,
            ],
        ]);
    }

    public function availability(ToyotaServiceAvailabilityRequest $request): JsonResponse
    {
        $location = ToyotaServiceLocation::query()->findOrFail(
            $request->string('service_location_id')->toString()
        );
        $serviceType = ToyotaServiceType::query()->findOrFail(
            $request->string('service_type_id')->toString()
        );

        return ApiResponse::success($this->availabilityService->availability(
            $location,
            $serviceType,
            ToyotaServiceFulfillmentType::from(
                $request->string('fulfillment_type')->toString()
            ),
            $request->filled('from_date')
                ? $request->string('from_date')->toString()
                : null,
            $request->integer('days', 14),
            $request->filled('city') ? $request->string('city')->toString() : null,
            $request->filled('latitude') ? $request->float('latitude') : null,
            $request->filled('longitude') ? $request->float('longitude') : null,
        ));
    }

    public function index(ListToyotaServiceBookingsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ToyotaServiceBooking::class);

        /** @var User $user */
        $user = $request->user();
        $query = $user->toyotaServiceBookings()
            ->with([
                'vehicle.vehicleMake',
                'vehicle.vehicleModel',
                'serviceLocation',
                'serviceType',
                'assignedServiceAdvisor',
                'photos.asset',
                'benefitChecks',
                'statusHistories' => fn ($history) => $history
                    ->where('user_visible', true)
                    ->oldest('created_at'),
            ]);

        if ($request->filled('status')) {
            $query->where(
                'status',
                ToyotaServiceBookingStatus::from($request->string('status')->toString()),
            );
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(ToyotaServiceBookingResource::collection($items));
    }

    public function store(StoreToyotaServiceBookingRequest $request): JsonResponse
    {
        $this->authorize('create', ToyotaServiceBooking::class);

        /** @var User $user */
        $user = $request->user();
        $result = $this->bookingCreationService->create($user, $request->validated());

        return ApiResponse::success(
            new ToyotaServiceBookingResource($result['booking']),
            $result['replayed']
                ? 'Permintaan booking yang sama ditemukan.'
                : 'Permintaan booking diterima dan menunggu konfirmasi.',
            $result['replayed'] ? 200 : 201,
            ['idempotent_replay' => $result['replayed']],
        );
    }

    public function show(ToyotaServiceBooking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return ApiResponse::success(new ToyotaServiceBookingResource(
            $this->bookingService->loadCustomerRelations($booking)
        ));
    }

    public function acceptAlternative(
        AcceptToyotaServiceAlternativeRequest $request,
        ToyotaServiceBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new ToyotaServiceBookingResource(
            $this->bookingService->acceptAlternative($booking, $user)
        ), 'Jadwal alternatif diterima dan booking dikonfirmasi.');
    }

    public function rejectAlternative(
        RejectToyotaServiceAlternativeRequest $request,
        ToyotaServiceBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new ToyotaServiceBookingResource(
            $this->bookingService->rejectAlternative($booking, $user, $request->validated())
        ), 'Jadwal alternatif ditolak dan preferensi baru dikirim.');
    }

    public function reschedule(
        RescheduleToyotaServiceBookingRequest $request,
        ToyotaServiceBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new ToyotaServiceBookingResource(
            $this->bookingService->requestReschedule($booking, $user, $request->validated())
        ), 'Permintaan jadwal ulang dikirim. Jadwal lama tetap berlaku.');
    }

    public function cancel(
        CancelToyotaServiceBookingRequest $request,
        ToyotaServiceBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new ToyotaServiceBookingResource(
            $this->bookingService->cancel(
                $booking,
                $user,
                $request->string('reason')->toString(),
            )
        ), 'Booking dibatalkan.');
    }
}
