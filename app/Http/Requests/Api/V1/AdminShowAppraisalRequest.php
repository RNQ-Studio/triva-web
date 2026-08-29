<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Appraisal;
use Illuminate\Foundation\Http\FormRequest;

class AdminShowAppraisalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAny', Appraisal::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
