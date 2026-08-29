<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListAppraisalsRequest;
use App\Http\Requests\Api\V1\AdminShowAppraisalRequest;
use App\Http\Resources\Api\V1\AdminAppraisalResource;
use App\Models\Appraisal;
use App\Support\ApiResponse;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAppraisalController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $this->authorize('manageAny', Appraisal::class);

        return ApiResponse::success([
            'statuses' => collect(AppraisalStatus::cases())
                ->map(fn (AppraisalStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->customerLabel(),
                ])
                ->values(),
        ]);
    }

    public function index(AdminListAppraisalsRequest $request): JsonResponse
    {
        $query = Appraisal::query()
            ->with([
                'user',
                'vehicle.vehicleMake',
                'vehicle.vehicleModel',
                'assignedAppraiser',
                'latestResult',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim()->toString().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('reference_no', 'ilike', $search)
                    ->orWhereHas(
                        'user',
                        fn (Builder $user) => $user
                            ->where('name', 'ilike', $search)
                            ->orWhere('email', 'ilike', $search)
                            ->orWhere('phone', 'ilike', $search),
                    );
            });
        }

        match ($request->string('sort')->toString()) {
            'submitted_desc' => $query->orderByDesc('submitted_at'),
            'created_desc' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('updated_at'),
        };
        $query->orderBy('id');

        return ApiResponse::success(AdminAppraisalResource::collection(
            $query->paginate($request->integer('per_page', 20))->withQueryString()
        ));
    }

    public function show(
        AdminShowAppraisalRequest $request,
        Appraisal $appraisal,
    ): JsonResponse {
        $appraisal->load([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'vehicle.vehicleVariant',
            'assignedAppraiser',
            'currentPhotos.asset',
            'statusHistories',
            'latestResult',
        ]);

        return ApiResponse::success(new AdminAppraisalResource($appraisal));
    }
}
