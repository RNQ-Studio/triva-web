<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitAppraisalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('update', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
