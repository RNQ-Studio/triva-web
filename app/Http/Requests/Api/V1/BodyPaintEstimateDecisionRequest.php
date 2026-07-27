<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BodyPaintEstimateDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('decide', $estimate) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
