<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
            ],
            'phone' => ['sometimes', 'required', 'regex:/^\+?[0-9]{9,15}$/'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'province_id' => [
                'required_with:city_id',
                'integer',
                Rule::exists('regions', 'id')->where('type', 'state'),
            ],
            'city_id' => [
                'required_with:province_id',
                'integer',
                Rule::exists('regions', 'id')->where('type', 'city'),
            ],
            'service_consent' => ['sometimes', 'accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $provinceId = $this->integer('province_id');
                $cityId = $this->integer('city_id');

                if ($provinceId === 0 && $cityId === 0) {
                    return;
                }

                $isValidPair = Region::query()
                    ->where('type', 'city')
                    ->whereKey($cityId)
                    ->where('parent_id', $provinceId)
                    ->whereHas(
                        'parent.parent',
                        fn ($query) => $query
                            ->where('type', 'country')
                            ->where('code', 'ID'),
                    )
                    ->exists();

                if (! $isValidPair) {
                    $validator->errors()->add(
                        'city_id',
                        'Kota/kabupaten harus sesuai dengan provinsi yang dipilih.',
                    );
                }
            },
        ];
    }
}
