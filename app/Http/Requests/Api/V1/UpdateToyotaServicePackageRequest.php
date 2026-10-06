<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ToyotaServicePackage;
use Illuminate\Validation\Validator;

class UpdateToyotaServicePackageRequest extends StoreToyotaServicePackageRequest
{
    public function authorize(): bool
    {
        $package = $this->package();

        return $package !== null && ($this->user()?->can('update', $package) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'km_interval' => ['sometimes', 'required', 'integer', 'min:1000', 'max:1000000'],
            'labor_cost' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000000'],
            'parts_cost' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000000'],
            ...collect($this->optionalRules())
                ->map(fn (array $rules): array => in_array('sometimes', $rules, true) ? $rules : ['sometimes', ...$rules])
                ->all(),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateConsistency($validator, $this->package()),
        ];
    }

    public function package(): ?ToyotaServicePackage
    {
        $package = $this->route('package');

        return $package instanceof ToyotaServicePackage ? $package : null;
    }
}
