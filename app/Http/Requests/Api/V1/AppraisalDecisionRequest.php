<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\AppraisalDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppraisalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('decide', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(AppraisalDecision::class)],
        ];
    }
}
