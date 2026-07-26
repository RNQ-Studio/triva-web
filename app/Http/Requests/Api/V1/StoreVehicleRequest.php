<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Region;
use App\Models\VehicleMake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'make_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicle_makes', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'make' => ['required_without:make_id', 'nullable', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:100'],
            'variant' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'fuel_type' => ['required', Rule::in(['gasoline', 'diesel', 'hybrid', 'electric'])],
            'mileage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'color' => ['required', 'string', 'max:60'],
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\s-]+$/'],
            'province_id' => [
                'required_with:city_id',
                'nullable',
                'integer',
                Rule::exists('regions', 'id')->where('type', 'state'),
            ],
            'city_id' => [
                'nullable',
                'integer',
                Rule::exists('regions', 'id')->where('type', 'city'),
            ],
            'city' => ['required_without:city_id', 'nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('province_id') || ! $this->filled('city_id')) {
                    return;
                }

                $belongsToProvince = Region::query()
                    ->whereKey($this->integer('city_id'))
                    ->where('type', 'city')
                    ->where('parent_id', $this->integer('province_id'))
                    ->exists();

                if (! $belongsToProvince) {
                    $validator->errors()->add(
                        'city_id',
                        'Kota/kabupaten harus berada di provinsi yang dipilih.',
                    );
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function vehicleData(): array
    {
        $data = $this->safe()->only([
            'vehicle_make_id',
            'make_id',
            'make',
            'model',
            'variant',
            'year',
            'transmission',
            'fuel_type',
            'mileage',
            'color',
            'license_plate',
            'province_id',
            'city_id',
            'city',
        ]);

        if (isset($data['make_id'])) {
            $data['vehicle_make_id'] = $data['make_id'];
            $data['make'] = VehicleMake::query()
                ->whereKey($data['make_id'])
                ->value('name');
        }
        unset($data['make_id']);

        if (isset($data['city_id'])) {
            $data['city'] = Region::query()
                ->whereKey($data['city_id'])
                ->value('name');
        }

        return $data;
    }
}
