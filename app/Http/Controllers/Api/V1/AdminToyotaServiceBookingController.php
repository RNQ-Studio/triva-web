<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListToyotaServiceBookingsRequest;
use App\Http\Requests\Api\V1\AdminToyotaServiceBookingActionRequest;
use App\Http\Resources\Api\V1\AdminToyotaServiceBookingResource;
use App\Http\Resources\Api\V1\ToyotaServiceLocationResource;
use App\Http\Resources\Api\V1\ToyotaServiceTypeResource;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Services\ToyotaServiceBookingAdminService;
use App\Support\ApiResponse;
use App\Support\Enums\BenefitVerificationSource;
use App\Support\Enums\ToyotaServiceAdminAction;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use App\Support\Enums\ToyotaServiceReasonCode;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminToyotaServiceBookingController extends Controller
{
    public function __construct(
        private readonly ToyotaServiceBookingAdminService $bookingService,
    ) {}

    public function index(AdminListToyotaServiceBookingsRequest $request): JsonResponse
    {
        $query = ToyotaServiceBooking::query()
            ->with([
                'user',
                'vehicle.vehicleMake',
                'vehicle.vehicleModel',
                'serviceLocation',
                'serviceType',
                'assignedServiceAdvisor',
                'photos.asset',
                'benefitChecks.verifiedBy',
                'statusHistories' => fn ($history) => $history
                    ->with('changedBy')
                    ->oldest('created_at'),
            ]);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request->string('sort', 'updated_desc')->toString());

        return ApiResponse::success(AdminToyotaServiceBookingResource::collection(
            $query->paginate($request->integer('per_page', 20))
        ));
    }

    public function options(Request $request): JsonResponse
    {
        if (! ($request->user()?->can('service_bookings.viewAny') ?? false)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $advisors = User::query()
            ->permission('service_bookings.update')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $advisor): array => [
                'id' => $advisor->getKey(),
                'name' => $advisor->name,
                'email' => $advisor->email,
                'phone' => $advisor->phone,
            ])
            ->values();

        return ApiResponse::success([
            'advisors' => $advisors,
            'statuses' => collect(ToyotaServiceBookingStatus::cases())
                ->map(fn (ToyotaServiceBookingStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->customerLabel(),
                ]),
            'actions' => collect(ToyotaServiceAdminAction::cases())
                ->map(fn (ToyotaServiceAdminAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ]),
            'fulfillment_types' => collect(ToyotaServiceFulfillmentType::cases())
                ->map(fn (ToyotaServiceFulfillmentType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ]),
            'service_locations' => ToyotaServiceLocationResource::collection(
                ToyotaServiceLocation::query()->effective()->orderBy('name')->get()
            ),
            'service_types' => ToyotaServiceTypeResource::collection(
                ToyotaServiceType::query()->effective()->orderBy('sort_order')->get()
            ),
            // Revisi 4 September 2026: T-Care dan Warranty tidak lagi ditampilkan
            // di detail booking; petugas hanya memverifikasi SSC.
            'benefit_types' => collect([VehicleBenefitType::Ssc])
                ->map(fn (VehicleBenefitType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ]),
            'benefit_statuses' => collect(VehicleBenefitStatus::cases())
                ->reject(fn (VehicleBenefitStatus $status): bool => $status === VehicleBenefitStatus::Unknown)
                ->map(fn (VehicleBenefitStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
            'verification_sources' => collect(BenefitVerificationSource::cases())
                ->map(fn (BenefitVerificationSource $source): array => [
                    'value' => $source->value,
                    'label' => $source->label(),
                ]),
            'reason_codes' => collect(ToyotaServiceReasonCode::cases())
                ->map(fn (ToyotaServiceReasonCode $code): array => [
                    'value' => $code->value,
                    'label' => $code->label(),
                ]),
        ]);
    }

    public function show(Request $request, ToyotaServiceBooking $booking): JsonResponse
    {
        if (! ($request->user()?->can('service_bookings.view') ?? false)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return ApiResponse::success(new AdminToyotaServiceBookingResource(
            $this->bookingService->loadAdminRelations($booking)
        ));
    }

    public function action(
        AdminToyotaServiceBookingActionRequest $request,
        ToyotaServiceBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $action = ToyotaServiceAdminAction::from($request->string('action')->toString());
        $updated = $this->bookingService->execute(
            $booking,
            $user,
            $action,
            $request->validated(),
        );

        return ApiResponse::success(
            new AdminToyotaServiceBookingResource($updated),
            $action->label().' berhasil.',
        );
    }

    /**
     * @param  Builder<ToyotaServiceBooking>  $query
     */
    private function applyFilters(
        Builder $query,
        AdminListToyotaServiceBookingsRequest $request,
    ): void {
        if ($request->filled('status')) {
            $query->where(
                'status',
                ToyotaServiceBookingStatus::from($request->string('status')->toString()),
            );
        }
        foreach (['fulfillment_type', 'service_location_id', 'service_type_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }
        if ($request->filled('advisor_id')) {
            $query->where('assigned_service_advisor_id', $request->integer('advisor_id'));
        }
        if ($request->filled('date')) {
            $query->forLocalDate($request->string('date')->toString());
        }
        if ($request->string('sla_status')->toString() === 'overdue') {
            $query->where('status', ToyotaServiceBookingStatus::AwaitingConfirmation)
                ->where('due_at', '<', now());
        } elseif ($request->string('sla_status')->toString() === 'within_sla') {
            $query->where(function (Builder $builder): void {
                $builder->where('status', '!=', ToyotaServiceBookingStatus::AwaitingConfirmation)
                    ->orWhere('due_at', '>=', now());
            });
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('reference_no', 'ilike', $search)
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', $search)
                        ->orWhere('email', 'ilike', $search))
                    ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle
                        ->where('license_plate', 'ilike', $search));
            });
        }
    }

    /** @param Builder<ToyotaServiceBooking> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'due_asc' => $query
                ->orderBy('due_at')
                ->orderBy('id'),
            'slot_asc' => $query
                ->orderBy('active_slot_start_at')
                ->orderBy('id'),
            default => $query
                ->orderByDesc('updated_at')
                ->orderBy('id'),
        };
    }
}
