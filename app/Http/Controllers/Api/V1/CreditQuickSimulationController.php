<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCreditQuickSimulationRequest;
use App\Http\Resources\Api\V1\CreditSimulationResource;
use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Services\Credit\AccCreditCalculator;
use App\Services\CreditQuickSimulationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Simulasi kredit cepat ala lembar kerja ACC (revisi 4 September 2026).
 */
class CreditQuickSimulationController extends Controller
{
    public function __construct(
        private readonly AccCreditCalculator $calculator,
        private readonly CreditQuickSimulationService $service,
    ) {}

    /**
     * Rate card untuk perhitungan langsung di aplikasi.
     */
    public function rateCard(): JsonResponse
    {
        return ApiResponse::success($this->calculator->rateCard());
    }

    public function store(StoreCreditQuickSimulationRequest $request): JsonResponse
    {
        $this->authorize('create', CreditSimulation::class);
        /** @var User $user */
        $user = $request->user();
        $program = CreditProgram::query()
            ->effective()
            ->findOrFail($request->string('program_id')->toString());

        $simulation = $this->service->create($user, $program, $request->validated());

        return ApiResponse::success(
            new CreditSimulationResource($simulation),
            'Simulasi berhasil dihitung dan diteruskan ke admin.',
            201,
        );
    }
}
