<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Region;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Optional for backward compatibility with released API v1 clients.
            // Current clients always send it so a lost response can be replayed.
            'idempotency_key' => ['nullable', 'uuid'],
            'make_id' => [
                'nullable',
                'required_with:model_id',
                'integer',
                Rule::exists('vehicle_makes', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'make' => ['required_without:make_id', 'nullable', 'string', 'max:80'],
            'model_id' => [
                'nullable',
                'required_with:variant_id',
                'integer',
                Rule::exists('vehicle_models', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'model' => ['required_without:model_id', 'nullable', 'string', 'max:100'],
            'variant_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicle_variants', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'variant' => [
                'required_without:variant_id',
                'nullable',
                'string',
                'max:120',
            ],
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
            function (Validator $validator): void {
                if (! $this->filled('make_id') || ! $this->filled('model_id')) {
                    return;
                }

                $belongsToMake = VehicleModel::query()
                    ->whereKey($this->integer('model_id'))
                    ->where('vehicle_make_id', $this->integer('make_id'))
                    ->where('is_active', true)
                    ->exists();

                if (! $belongsToMake) {
                    $validator->errors()->add(
                        'model_id',
                        'Model harus berada pada merek yang dipilih.',
                    );
                }
            },
            function (Validator $validator): void {
                if (! $this->filled('variant_id')) {
                    return;
                }

                $variant = VehicleVariant::query()
                    ->active()
                    ->find($this->integer('variant_id'));

                if ($variant === null) {
                    return;
                }

                if (
                    ! $this->filled('model_id')
                    || $variant->vehicle_model_id !== $this->integer('model_id')
                ) {
                    $validator->errors()->add(
                        'variant_id',
                        'Varian harus berada pada model yang dipilih.',
                    );
                }

                if (
                    $variant->transmission !== null
                    && $variant->transmission !== $this->string('transmission')->toString()
                ) {
                    $validator->errors()->add(
                        'transmission',
                        'Transmisi harus sesuai dengan varian yang dipilih.',
                    );
                }

                if (
                    $variant->fuel_type !== null
                    && $variant->fuel_type !== $this->string('fuel_type')->toString()
                ) {
                    $validator->errors()->add(
                        'fuel_type',
                        'Jenis bahan bakar harus sesuai dengan varian yang dipilih.',
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
            'vehicle_model_id',
            'model_id',
            'model',
            'vehicle_variant_id',
            'variant_id',
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
        } else {
            $data['vehicle_make_id'] = null;
        }
        unset($data['make_id']);

        if (isset($data['model_id'])) {
            $data['vehicle_model_id'] = $data['model_id'];
            $data['model'] = VehicleModel::query()
                ->whereKey($data['model_id'])
                ->value('name');
        } else {
            $data['vehicle_model_id'] = null;
        }
        unset($data['model_id']);

        if (isset($data['variant_id'])) {
            $variant = VehicleVariant::query()
                ->whereKey($data['variant_id'])
                ->firstOrFail();
            $data['vehicle_variant_id'] = $data['variant_id'];
            $data['variant'] = $variant->name;
        } else {
            $data['vehicle_variant_id'] = null;
        }
        unset($data['variant_id']);

        if (isset($data['city_id'])) {
            $data['city'] = Region::query()
                ->whereKey($data['city_id'])
                ->value('name');
        }

        return $data;
    }
}
