<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use Illuminate\Foundation\Http\FormRequest;

class SubmitBodyPaintEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('submit', $estimate) ?? false);
    }

    public function rules(): array
    {
        return [
            'service_consent' => ['required', 'accepted'],
            'estimate_disclaimer_accepted' => ['required', 'accepted'],
        ];
    }
}
