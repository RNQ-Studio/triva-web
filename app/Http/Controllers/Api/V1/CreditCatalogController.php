<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCreditProgramsRequest;
use App\Http\Resources\Api\V1\CreditProgramResource;
use App\Models\CreditProgram;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreditCatalogController extends Controller
{
    public function vehicles(
        ListCreditProgramsRequest $request,
    ): JsonResponse {
        $this->authorize('viewCatalog', CreditProgram::class);
        $query = CreditProgram::query()->effective();
        foreach ([
            'city',
            'vehicle_model',
            'vehicle_variant',
            'model_year',
        ] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }
        $paginator = $query
            ->select([
                'city',
                'vehicle_model',
                'vehicle_variant',
                'model_year',
                'otr_price',
                'approved_discount',
            ])
            ->distinct()
            ->orderBy('city')
            ->orderBy('vehicle_model')
            ->orderBy('vehicle_variant')
            ->paginate($request->integer('per_page', 30))
            ->through(fn (CreditProgram $program): array => [
                'key' => hash('sha256', implode('|', [
                    $program->city,
                    $program->vehicle_model,
                    $program->vehicle_variant,
                    (string) $program->model_year,
                    (string) $program->otr_price,
                ])),
                'city' => $program->city,
                'model' => $program->vehicle_model,
                'variant' => $program->vehicle_variant,
                'model_year' => $program->model_year,
                'otr_price' => $program->otr_price,
                'approved_discount' => $program->approved_discount,
            ]);

        return ApiResponse::success($paginator);
    }

    public function programs(
        ListCreditProgramsRequest $request,
    ): JsonResponse {
        $this->authorize('viewCatalog', CreditProgram::class);
        $query = CreditProgram::query()->effective();
        foreach ([
            'city',
            'vehicle_model',
            'vehicle_variant',
            'model_year',
        ] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->validated($filter));
            }
        }

        return ApiResponse::success(
            CreditProgramResource::collection(
                $query->orderBy('partner_name')
                    ->orderBy('program_name')
                    ->paginate($request->integer('per_page', 30))
            )
        );
    }
}
