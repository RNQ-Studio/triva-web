<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListOtoxpertBookingsRequest;
use App\Http\Requests\Api\V1\AdminOtoxpertBookingActionRequest;
use App\Http\Resources\Api\V1\AdminOtoxpertBookingResource;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\User;
use App\Services\OtoxpertBookingAdminService;
use App\Support\ApiResponse;
use App\Support\Enums\OtoxpertAdminAction;
use App\Support\Enums\OtoxpertBookingStatus;
use App\Support\Enums\OtoxpertReasonCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOtoxpertBookingController extends Controller
{
    public function __construct(
        private readonly OtoxpertBookingAdminService $adminService,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $this->authorize('manageAny', OtoxpertBooking::class);
        /** @var User $user */
        $user = $request->user();
        $workshops = OtoxpertWorkshop::query()
            ->effective()
            ->when(
                ! $user->hasAnyRole(['super-admin', 'admin']),
                fn (Builder $query): Builder => $query->whereHas(
                    'operators',
                    fn (Builder $operator): Builder => $operator
                        ->whereKey($user->getKey())
                        ->where(
                            'otoxpert_workshop_operators.is_active',
                            true,
                        ),
                ),
            )
            ->orderBy('name')
            ->get();
        $workshopIds = $workshops->modelKeys();
        $operators = User::permission('service_bookings.update')
            ->where(function (Builder $query) use ($workshopIds): void {
                $query->whereHas('roles', fn (Builder $role): Builder => $role
                    ->whereIn('name', ['super-admin', 'admin']))
                    ->orWhereHas(
                        'otoxpertWorkshops',
                        fn (Builder $workshop): Builder => $workshop
                            ->whereKey($workshopIds)
                            ->where(
                                'otoxpert_workshop_operators.is_active',
                                true,
                            ),
                    );
            })
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'workshops' => $workshops->map(fn (OtoxpertWorkshop $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'city' => $item->city,
            ])->values()->all(),
            'services' => OtoxpertService::query()
                ->effective()
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name'])
                ->toArray(),
            'operators' => $operators->map(fn (User $item): array => [
                'id' => $item->getKey(),
                'name' => $item->name,
                'email' => $item->email,
            ])->values()->all(),
            'statuses' => collect(OtoxpertBookingStatus::cases())
                ->map(fn (OtoxpertBookingStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->customerLabel(),
                ])->values()->all(),
            'actions' => collect(OtoxpertAdminAction::cases())
                ->map(fn (OtoxpertAdminAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ])->values()->all(),
            'reason_codes' => collect(OtoxpertReasonCode::cases())
                ->map(fn (OtoxpertReasonCode $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ])->values()->all(),
        ]);
    }

    public function index(
        AdminListOtoxpertBookingsRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $query = $this->filteredQuery($request, $user)->with([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'workshop',
            'service',
            'assignedOperator',
            'photos.asset',
            'statusHistories' => fn ($history) => $history->oldest('created_at'),
        ]);
        match ($request->string('sort', 'updated_desc')->toString()) {
            'due_asc' => $query->orderBy('due_at'),
            'slot_asc' => $query->orderByRaw(
                'COALESCE(confirmed_start_at, primary_start_at) ASC'
            ),
            default => $query->orderByDesc('updated_at'),
        };

        return ApiResponse::success(
            AdminOtoxpertBookingResource::collection(
                $query->paginate($request->integer('per_page', 20))
            )
        );
    }

    public function show(OtoxpertBooking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return ApiResponse::success(new AdminOtoxpertBookingResource(
            $this->adminService->loadAdminRelations($booking)
        ));
    }

    public function action(
        AdminOtoxpertBookingActionRequest $request,
        OtoxpertBooking $booking,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new AdminOtoxpertBookingResource(
            $this->adminService->execute(
                $booking,
                $user,
                $request->validated(),
            )
        ), 'Booking OtoXpert diperbarui.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('manageAny', OtoxpertBooking::class);
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'workshop_id' => [
                'nullable',
                'uuid',
                'exists:otoxpert_workshops,id',
            ],
        ]);
        /** @var User $user */
        $user = $request->user();
        $query = OtoxpertBooking::query()
            ->visibleToStaff($user)
            ->with(['user', 'vehicle', 'workshop', 'service'])
            ->whereDate('primary_start_at', $request->string('date')->toString())
            ->when(
                $request->filled('workshop_id'),
                fn (Builder $builder): Builder => $builder->where(
                    'workshop_id',
                    $request->string('workshop_id')->toString(),
                ),
            )
            ->orderBy('primary_start_at');

        return response()->streamDownload(
            function () use ($query): void {
                $stream = fopen('php://output', 'wb');
                if ($stream === false) {
                    return;
                }
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, [
                    'Reference',
                    'Workshop',
                    'Jadwal',
                    'Status',
                    'Layanan',
                    'Kendaraan',
                    'Pelanggan',
                    'Telepon',
                    'Campaign',
                    'Follow Up',
                ]);
                $query->chunkById(200, function ($items) use ($stream): void {
                    foreach ($items as $booking) {
                        fputcsv($stream, [
                            $this->csv($booking->reference_no),
                            $this->csv($booking->workshop->name),
                            $booking->primary_start_at->toIso8601String(),
                            $booking->status->value,
                            $this->csv($booking->service->name),
                            $this->csv(
                                "{$booking->vehicle->make} {$booking->vehicle->model}"
                            ),
                            $this->csv($booking->user->name),
                            $this->csv($booking->user->phone ?? ''),
                            $this->csv($booking->campaign_source ?? ''),
                            $this->csv($booking->follow_up_outcome ?? ''),
                        ]);
                    }
                });
                fclose($stream);
            },
            'otoxpert-bookings-'.$request->string('date')->toString().'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function filteredQuery(
        AdminListOtoxpertBookingsRequest $request,
        User $user,
    ): Builder {
        $query = OtoxpertBooking::query()->visibleToStaff($user);
        foreach ([
            'status',
            'workshop_id',
            'service_id',
            'operator_id' => 'assigned_operator_id',
        ] as $input => $column) {
            if (is_int($input)) {
                $input = $column;
            }
            if ($request->filled($input)) {
                $query->where(
                    $column,
                    $request->string($input)->toString(),
                );
            }
        }
        if ($request->filled('sla_status')) {
            $sla = $request->string('sla_status')->toString();
            if ($sla === 'overdue') {
                $query->where(
                    'status',
                    OtoxpertBookingStatus::AwaitingConfirmation,
                )->where('due_at', '<', now());
            } elseif ($sla === 'due') {
                $query->where(
                    'status',
                    OtoxpertBookingStatus::AwaitingConfirmation,
                )->where('due_at', '>=', now());
            }
        }
        if ($request->filled('date')) {
            $query->whereDate(
                'primary_start_at',
                $request->string('date')->toString(),
            );
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('reference_no', 'ilike', $term)
                    ->orWhereHas('user', fn (Builder $user): Builder => $user
                        ->where('name', 'ilike', $term)
                        ->orWhere('phone', 'ilike', $term))
                    ->orWhereHas('vehicle', fn (Builder $vehicle): Builder => $vehicle
                        ->where('license_plate', 'ilike', $term));
            });
        }

        return $query;
    }

    private function csv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
