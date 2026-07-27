<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Support\BodyPaintCatalog;
use App\Support\Enums\BodyPaintSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBodyPaintDamagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('updateCustomer', $estimate) ?? false);
    }

    public function rules(): array
    {
        return [
            'damages' => ['required', 'array', 'min:1', 'max:9'],
            'damages.*' => [
                'required',
                'array:panel_code,damage_type,severity,note',
            ],
            'damages.*.panel_code' => [
                'required',
                'string',
                Rule::in(BodyPaintCatalog::panelCodes()),
            ],
            'damages.*.damage_type' => [
                'required',
                'string',
                Rule::in(BodyPaintCatalog::damageTypeCodes()),
            ],
            'damages.*.severity' => [
                'required',
                Rule::enum(BodyPaintSeverity::class),
            ],
            'damages.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];
            foreach ($this->input('damages', []) as $index => $damage) {
                if (! is_array($damage)) {
                    continue;
                }
                $key = ($damage['panel_code'] ?? '').'|'
                    .($damage['damage_type'] ?? '');
                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "damages.{$index}.damage_type",
                        'Panel dan jenis kerusakan yang sama tidak boleh diduplikasi.',
                    );
                }
                $seen[$key] = true;
            }
        });
    }
}
