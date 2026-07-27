<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintPriceItem;
use Illuminate\Foundation\Http\FormRequest;

class ImportBodyPaintPriceMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BodyPaintPriceItem::class)
            ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }
}
