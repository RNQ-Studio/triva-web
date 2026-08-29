<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListCreditSimulationsRequest;
use App\Http\Requests\Api\V1\AdminShowCreditSimulationRequest;
use App\Http\Resources\Api\V1\AdminCreditSimulationResource;
use App\Models\CreditSimulation;
use App\Support\ApiResponse;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCreditSimulationController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $this->authorize('manageAny', CreditSimulation::class);

        return ApiResponse::success([
            'statuses' => collect(CreditSimulationStatus::cases())
                ->map(fn (CreditSimulationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
        ]);
    }

    public function index(
        AdminListCreditSimulationsRequest $request,
    ): JsonResponse {
        $query = CreditSimulation::query()
            ->with(['user', 'followUpLead', 'appraisal']);

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
            'updated_desc' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('saved_at'),
        };
        $query->orderBy('id');

        return ApiResponse::success(AdminCreditSimulationResource::collection(
            $query->paginate($request->integer('per_page', 20))->withQueryString()
        ));
    }

    public function show(
        AdminShowCreditSimulationRequest $request,
        CreditSimulation $simulation,
    ): JsonResponse {
        $simulation->load([
            'user',
            'program',
            'followUpLead.assignedSales',
            'appraisal',
        ]);

        return ApiResponse::success(
            new AdminCreditSimulationResource($simulation),
        );
    }
}
