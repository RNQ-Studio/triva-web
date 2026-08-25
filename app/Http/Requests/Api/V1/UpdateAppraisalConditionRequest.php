<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppraisalConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('update', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [
            'tax_status' => ['required', Rule::in(['active', 'overdue', 'unknown'])],
            'flood_history' => ['required', Rule::in(['yes', 'no', 'unknown'])],
            'major_accident_history' => ['required', Rule::in(['yes', 'no', 'unknown'])],
            'service_history' => ['required', Rule::in(['complete', 'partial', 'none', 'unknown'])],
            'ownership' => ['required', Rule::in(['first', 'second', 'more', 'unknown'])],
            'condition_grade' => ['required', Rule::in(['a', 'b', 'c', 'd'])],
            'engine_condition' => ['required', Rule::in(['normal', 'wet'])],
            'tyre_condition' => ['required', Rule::in(['normal', 'damaged'])],
        ];
    }
}
