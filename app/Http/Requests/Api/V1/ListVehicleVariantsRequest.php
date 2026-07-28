<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListVehicleVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'min:1950',
                'max:'.(now()->year + 1),
            ],
        ];
    }
}
