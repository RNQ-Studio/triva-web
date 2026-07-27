<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminBodyPaintEstimateActionRequest;
use App\Http\Requests\Api\V1\AdminListBodyPaintEstimatesRequest;
use App\Http\Requests\Api\V1\ImportBodyPaintPriceMatrixRequest;
use App\Http\Resources\Api\V1\AdminBodyPaintEstimateResource;
use App\Models\BodyPaintEstimate;
use App\Models\ToyotaServiceLocation;
use App\Models\User;
use App\Services\BodyPaintEstimateService;
use App\Services\BodyPaintEstimatorService;
use App\Services\BodyPaintPriceMatrixCsvImportService;
use App\Support\ApiResponse;
use App\Support\Enums\BodyPaintEstimateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AdminBodyPaintController extends Controller
{
    public function __construct(
        private readonly BodyPaintEstimateService $estimates,
        private readonly BodyPaintEstimatorService $estimator,
        private readonly BodyPaintPriceMatrixCsvImportService $matrixImport,
    ) {}

    public function options(Request $request): JsonResponse
    {
        $this->authorize('manageAny', BodyPaintEstimate::class);

        return ApiResponse::success([
            'statuses' => collect(BodyPaintEstimateStatus::cases())
                ->map(fn (BodyPaintEstimateStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ]),
            'service_locations' => ToyotaServiceLocation::query()
                ->effective()
                ->where('supports_workshop', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'city']),
            'estimators' => User::permission('bp_estimates.update')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'sla_minutes' => (int) config(
                'body_paint.estimator_sla_minutes',
                120,
            ),
        ]);
    }

    public function index(
        AdminListBodyPaintEstimatesRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $query = BodyPaintEstimate::query()
            ->visibleToStaff($user)
            ->with([
                'user',
                'vehicle.vehicleMake',
                'vehicle.vehicleModel',
                'serviceLocation',
                'assignedEstimator',
                'damages.photos.asset',
                'photos.asset',
                'items.damage',
                'items.priceItem',
                'versions.publisher',
                'currentPublishedVersion.publisher',
                'statusHistories.changedBy',
                'booking.serviceLocation',
                'booking.serviceType',
            ]);
        foreach ([
            'status',
            'service_location_id',
            'estimator_id' => 'assigned_estimator_id',
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
            $operator = $request->string('sla_status')->toString()
                === 'overdue'
                ? '<'
                : '>=';
            $query->whereIn('status', [
                BodyPaintEstimateStatus::Submitted,
                BodyPaintEstimateStatus::AutoEstimated,
                BodyPaintEstimateStatus::ManualReview,
            ])->where('due_at', $operator, now());
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('reference_no', 'ilike', $term)
                    ->orWhereHas(
                        'user',
                        fn (Builder $user): Builder => $user
                            ->where('name', 'ilike', $term)
                            ->orWhere('phone', 'ilike', $term),
                    )
                    ->orWhereHas(
                        'vehicle',
                        fn (Builder $vehicle): Builder => $vehicle
                            ->where('license_plate', 'ilike', $term),
                    );
            });
        }
        match ($request->string('sort', 'updated_desc')->toString()) {
            'due_asc' => $query->orderBy('due_at'),
            'submitted_desc' => $query->latest('submitted_at'),
            default => $query->latest('updated_at'),
        };

        return ApiResponse::success(
            AdminBodyPaintEstimateResource::collection(
                $query->paginate($request->integer('per_page', 20)),
            ),
        );
    }

    public function show(BodyPaintEstimate $estimate): JsonResponse
    {
        $this->authorize('manage', $estimate);

        return ApiResponse::success(
            new AdminBodyPaintEstimateResource(
                $this->estimates->loadAdminRelations($estimate),
            ),
        );
    }

    public function action(
        AdminBodyPaintEstimateActionRequest $request,
        BodyPaintEstimate $estimate,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new AdminBodyPaintEstimateResource(
                $this->estimator->execute(
                    $estimate,
                    $user,
                    $request->validated(),
                ),
            ),
            'Estimasi Body & Paint diperbarui.',
        );
    }

    public function previewImport(
        ImportBodyPaintPriceMatrixRequest $request,
    ): JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->matrixImport->process($file, $user),
            'Preview import selesai.',
        );
    }

    public function import(
        ImportBodyPaintPriceMatrixRequest $request,
    ): JsonResponse {
        $request->validate(['confirm' => ['required', 'accepted']]);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->matrixImport->process($file, $user, true),
            'Price matrix berhasil diimport.',
            201,
        );
    }
}
