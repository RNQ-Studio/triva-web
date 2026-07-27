<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptOtoxpertAlternativeRequest;
use App\Http\Requests\Api\V1\CancelOtoxpertBookingRequest;
use App\Http\Requests\Api\V1\ListOtoxpertBookingsRequest;
use App\Http\Requests\Api\V1\ListOtoxpertWorkshopsRequest;
use App\Http\Requests\Api\V1\OtoxpertAvailabilityRequest;
use App\Http\Requests\Api\V1\RejectOtoxpertAlternativeRequest;
use App\Http\Requests\Api\V1\RescheduleOtoxpertBookingRequest;
use App\Http\Requests\Api\V1\StoreOtoxpertBookingRequest;
use App\Http\Resources\Api\V1\OtoxpertBookingResource;
use App\Http\Resources\Api\V1\OtoxpertServiceResource;
use App\Http\Resources\Api\V1\OtoxpertWorkshopResource;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\OtoxpertAvailabilityService;
use App\Services\OtoxpertBookingCreationService;
use App\Services\OtoxpertBookingService;
use App\Support\ApiResponse;
use App\Support\Enums\OtoxpertBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtoxpertController extends Controller
{
    public function __construct(
        private readonly OtoxpertAvailabilityService $availabilityService,
        private readonly OtoxpertBookingCreationService $creationService,
        private readonly OtoxpertBookingService $bookingService,
    ) {}

    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'request_to_confirm' => true,
            'notice' => 'Jadwal dan harga adalah indikasi sampai dikonfirmasi bengkel OtoXpert.',
            'partner_consent_version' => 'otoxpert-data-sharing-v1',
            'symptom_options' => [
                ['value' => 'noise', 'label' => 'Suara tidak normal'],
                ['value' => 'vibration', 'label' => 'Getaran'],
                ['value' => 'warning_light', 'label' => 'Lampu peringatan'],
                ['value' => 'leak', 'label' => 'Kebocoran'],
                ['value' => 'performance', 'label' => 'Performa menurun'],
                ['value' => 'other', 'label' => 'Lainnya'],
            ],
            'contact_channels' => collect(
                ToyotaServiceContactChannel::cases()
            )->map(fn (ToyotaServiceContactChannel $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
            ])->values()->all(),
            'photo_upload' => [
                'type' => 'otoxpert-booking-photo',
                'max_files' => 5,
                'max_size_kb' => 10240,
                'mime_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/heic',
                    'image/heif',
                ],
                'is_protected' => true,
            ],
        ]);
    }

    public function workshops(
        ListOtoxpertWorkshopsRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        /** @var Vehicle $vehicle */
        $vehicle = $user->vehicles()->findOrFail(
            $request->string('vehicle_id')->toString()
        );
        $query = OtoxpertWorkshop::query()
            ->effective()
            ->where(function (Builder $builder) use ($vehicle): void {
                $builder->where('supports_all_vehicle_makes', true);
                if ($vehicle->vehicle_make_id !== null) {
                    $builder->orWhereHas(
                        'vehicleMakes',
                        fn (Builder $make): Builder => $make->whereKey(
                            $vehicle->vehicle_make_id
                        ),
                    );
                }
            })
            ->with([
                'services' => fn ($service) => $service
                    ->effective()
                    ->wherePivot('is_active', true)
                    ->orderBy('sort_order'),
            ]);
        if ($request->filled('service_id')) {
            $serviceId = $request->string('service_id')->toString();
            $query->whereHas(
                'services',
                fn (Builder $service): Builder => $service
                    ->whereKey($serviceId)
                    ->where('otoxpert_workshop_services.is_active', true),
            );
        }
        if ($request->filled('city')) {
            $query->where(
                'city',
                'ilike',
                $request->string('city')->toString(),
            );
        }

        return ApiResponse::success(
            OtoxpertWorkshopResource::collection(
                $query->orderBy('city')->orderBy('name')->get()
            )
        );
    }

    public function services(
        Request $request,
        OtoxpertWorkshop $workshop,
    ): JsonResponse {
        abort_unless(
            OtoxpertWorkshop::query()->effective()->whereKey($workshop)->exists(),
            404,
        );
        $services = $workshop->services()
            ->effective()
            ->wherePivot('is_active', true)
            ->with([
                'prices' => fn ($price) => $price
                    ->effective()
                    ->where('workshop_id', $workshop->getKey())
                    ->latest('verified_at'),
            ])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(
            OtoxpertServiceResource::collection($services)->resolve($request)
        );
    }

    public function availability(
        OtoxpertAvailabilityRequest $request,
    ): JsonResponse {
        $workshop = OtoxpertWorkshop::query()->findOrFail(
            $request->string('workshop_id')->toString()
        );
        $service = OtoxpertService::query()->findOrFail(
            $request->string('service_id')->toString()
        );

        return ApiResponse::success(
            $this->availabilityService->availability(
                $workshop,
                $service,
                $request->filled('from_date')
                    ? $request->string('from_date')->toString()
                    : null,
                $request->integer('days', 14),
            )
        );
    }

    public function index(
        ListOtoxpertBookingsRequest $request,
    ): JsonResponse {
        $this->authorize('viewAny', OtoxpertBooking::class);
        /** @var User $user */
        $user = $request->user();
        $query = $user->otoxpertBookings()->with([
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'workshop',
            'service',
            'assignedOperator',
            'photos.asset',
            'statusHistories' => fn ($history) => $history
                ->where('user_visible', true)
                ->oldest('created_at'),
        ]);
        if ($request->filled('status')) {
            $query->where(
                'status',
                OtoxpertBookingStatus::from(
                    $request->string('status')->toString()
                ),
            );
        }

        return ApiResponse::success(
            OtoxpertBookingResource::collection(
                $query->latest('updated_at')->paginate(
                    $request->integer('per_page', 20)
                )
            )
        );
    }

    public function store(
        StoreOtoxpertBookingRequest $request,
    ): JsonResponse {
        $this->authorize('create', OtoxpertBooking::class);
        /** @var User $user */
        $user = $request->user();
        $result = $this->creationService->create(
            $user,
            $request->validated(),
        );

        return ApiResponse::success(
            new OtoxpertBookingResource($result['booking']),
            $result['replayed']
                ? 'Permintaan booking yang sama ditemukan.'
                : 'Permintaan OtoXpert diterima dan menunggu konfirmasi.',
            $result['replayed'] ? 200 : 201,
            ['idempotent_replay' => $result['replayed']],
        );
    }

    public function show(OtoxpertBooking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return ApiResponse::success(new OtoxpertBookingResource(
            $this->bookingService->loadCustomerRelations($booking)
        ));
    }

    public function acceptAlternative(
        AcceptOtoxpertAlternativeRequest $request,
        OtoxpertBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new OtoxpertBookingResource(
            $this->bookingService->acceptAlternative($booking, $user)
        ), 'Jadwal alternatif diterima.');
    }

    public function rejectAlternative(
        RejectOtoxpertAlternativeRequest $request,
        OtoxpertBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new OtoxpertBookingResource(
            $this->bookingService->rejectAlternative(
                $booking,
                $user,
                $request->validated(),
            )
        ), 'Preferensi jadwal baru dikirim.');
    }

    public function reschedule(
        RescheduleOtoxpertBookingRequest $request,
        OtoxpertBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new OtoxpertBookingResource(
            $this->bookingService->requestReschedule(
                $booking,
                $user,
                $request->validated(),
            )
        ), 'Permintaan jadwal ulang dikirim.');
    }

    public function cancel(
        CancelOtoxpertBookingRequest $request,
        OtoxpertBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new OtoxpertBookingResource(
            $this->bookingService->cancel(
                $booking,
                $user,
                $request->string('reason')->toString(),
            )
        ), 'Booking dibatalkan.');
    }
}
