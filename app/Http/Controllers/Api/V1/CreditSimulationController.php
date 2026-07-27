<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CalculateCreditSimulationRequest;
use App\Http\Requests\Api\V1\ListCreditSimulationsRequest;
use App\Http\Requests\Api\V1\RequestCreditFollowUpRequest;
use App\Http\Requests\Api\V1\StoreCreditSimulationRequest;
use App\Http\Resources\Api\V1\CreditCalculationResource;
use App\Http\Resources\Api\V1\CreditSimulationResource;
use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Services\CreditFollowUpService;
use App\Services\CreditSimulationCalculator;
use App\Services\CreditSimulationCreationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreditSimulationController extends Controller
{
    public function __construct(
        private readonly CreditSimulationCalculator $calculator,
        private readonly CreditSimulationCreationService $creation,
        private readonly CreditFollowUpService $followUp,
    ) {}

    public function calculate(
        CalculateCreditSimulationRequest $request,
    ): JsonResponse {
        $this->authorize('create', CreditSimulation::class);
        /** @var User $user */
        $user = $request->user();
        $program = CreditProgram::query()->findOrFail(
            $request->string('program_id')->toString()
        );

        return ApiResponse::success(
            new CreditCalculationResource(
                $this->calculator->calculate(
                    $user,
                    $program,
                    $request->validated(),
                )
            ),
            'Simulasi berhasil dihitung.',
        );
    }

    public function index(
        ListCreditSimulationsRequest $request,
    ): JsonResponse {
        $this->authorize('viewAny', CreditSimulation::class);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            CreditSimulationResource::collection(
                $user->creditSimulations()
                    ->with('followUpLead.assignedSales')
                    ->latest('updated_at')
                    ->paginate($request->integer('per_page', 20))
            )
        );
    }

    public function store(
        StoreCreditSimulationRequest $request,
    ): JsonResponse {
        $this->authorize('create', CreditSimulation::class);
        /** @var User $user */
        $user = $request->user();
        $result = $this->creation->create($user, $request->validated());

        return ApiResponse::success(
            new CreditSimulationResource($result['simulation']),
            $result['replayed']
                ? 'Simulasi yang sama ditemukan.'
                : 'Simulasi berhasil disimpan.',
            $result['replayed'] ? 200 : 201,
            ['idempotent_replay' => $result['replayed']],
        );
    }

    public function show(CreditSimulation $simulation): JsonResponse
    {
        $this->authorize('view', $simulation);

        return ApiResponse::success(
            new CreditSimulationResource($this->creation->load($simulation))
        );
    }

    public function requestFollowUp(
        RequestCreditFollowUpRequest $request,
        CreditSimulation $simulation,
    ): JsonResponse {
        $this->authorize('requestFollowUp', $simulation);
        /** @var User $user */
        $user = $request->user();
        $result = $this->followUp->request(
            $simulation,
            $user,
            $request->validated(),
        );

        return ApiResponse::success(
            new CreditSimulationResource($result['simulation']),
            $result['replayed']
                ? 'Permintaan follow-up sudah tercatat.'
                : 'Permintaan follow-up diteruskan ke tim sales.',
            200,
            ['idempotent_replay' => $result['replayed']],
        );
    }
}
