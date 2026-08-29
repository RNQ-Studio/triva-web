<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CreditSimulation;
use Illuminate\Foundation\Http\FormRequest;

class AdminShowCreditSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'manageAny',
            CreditSimulation::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
