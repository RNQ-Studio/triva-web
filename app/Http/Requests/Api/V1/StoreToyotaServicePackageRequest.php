<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesAdminContent;
use App\Models\ToyotaServicePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Budget jasa & part paket servis berkala (T-Care) dari Admin Panel
 * aplikasi. Hanya model, kelipatan km, dan kedua budget yang wajib; sisanya
 * diisi default server supaya form di aplikasi tetap ringkas.
 */
class StoreToyotaServicePackageRequest extends FormRequest
{
    use ValidatesAdminContent;

    public const DEFAULT_CODE = 'T-CARE';

    public const DEFAULT_SOURCE_REFERENCE = 'Admin Panel aplikasi TRIVA';

    public const DEFAULT_DURATION_MIN = 60;

    public const DEFAULT_DURATION_MAX = 180;

    public static function defaultName(int $kmInterval): string
    {
        return 'Servis Berkala '.number_format($kmInterval, 0, ',', '.').' km';
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', ToyotaServicePackage::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleans(['is_active']);

        // Baris cakupan yang dibiarkan kosong di form tidak ikut disimpan.
        if (is_array($this->input('includes'))) {
            $this->merge([
                'includes' => array_values(array_filter(
                    $this->input('includes'),
                    fn (mixed $item): bool => $item !== null && (! is_string($item) || trim($item) !== ''),
                )),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'km_interval' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'labor_cost' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'parts_cost' => ['required', 'integer', 'min:0', 'max:1000000000'],
            ...$this->optionalRules(),
        ];
    }

    /** @return array<string, list<string>> */
    protected function optionalRules(): array
    {
        return [
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'includes' => ['nullable', 'array', 'max:30'],
            'includes.*' => ['required', 'string', 'max:120'],
            'duration_min_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'duration_max_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'source_reference' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'km_interval' => 'Kelipatan kilometer',
            'labor_cost' => 'Budget jasa',
            'parts_cost' => 'Budget part',
            'vehicle_model' => 'Model kendaraan',
            'name' => 'Nama paket',
            'code' => 'Kode paket',
            'description' => 'Penjelasan',
            'includes' => 'Cakupan pekerjaan',
            'includes.*' => 'Cakupan pekerjaan',
            'duration_min_minutes' => 'Durasi minimum',
            'duration_max_minutes' => 'Durasi maksimum',
            'is_active' => 'Status aktif',
            'effective_from' => 'Berlaku dari',
            'effective_to' => 'Berlaku sampai',
            'source_reference' => 'Rujukan sumber',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateConsistency($validator, null),
        ];
    }

    /**
     * Atribut siap simpan: nilai kosong pada kolom wajib diganti default
     * server saat membuat, atau dibiarkan memakai nilai lama saat mengubah.
     *
     * @return array<string, mixed>
     */
    public function packageAttributes(?ToyotaServicePackage $current = null): array
    {
        $data = $this->validated();

        if (array_key_exists('vehicle_model', $data)) {
            $data['vehicle_model'] = self::normalizeModel($data['vehicle_model']);
        }

        $kmInterval = (int) ($data['km_interval'] ?? $current?->km_interval);

        // Nama kosong selalu diganti nama bawaan dari kelipatan km. Aplikasi
        // mengirim ulang seluruh isian saat mengubah paket, jadi nama bawaan
        // lama yang ikut terkirim juga mengikuti kelipatan km yang baru.
        $name = array_key_exists('name', $data) ? $data['name'] : $current?->name;
        $staleDefault = $current !== null
            && $kmInterval !== $current->km_interval
            && $name === self::defaultName($current->km_interval);
        if (blank($name) || $staleDefault) {
            $data['name'] = self::defaultName($kmInterval);
        }

        // Kolom wajib lain: kosong saat membuat → default server; kosong saat
        // mengubah → nilai lama dipertahankan.
        $defaults = [
            'code' => self::DEFAULT_CODE,
            'duration_min_minutes' => self::DEFAULT_DURATION_MIN,
            'duration_max_minutes' => self::DEFAULT_DURATION_MAX,
            'source_reference' => self::DEFAULT_SOURCE_REFERENCE,
            'effective_from' => now('Asia/Jakarta')->toDateString(),
        ];
        foreach ($defaults as $field => $default) {
            if (filled($data[$field] ?? null)) {
                continue;
            }
            if ($current === null) {
                $data[$field] = $default;
            } else {
                unset($data[$field]);
            }
        }

        if ($current === null) {
            $data['is_active'] ??= true;
            $data['includes'] ??= [];
        } elseif (array_key_exists('includes', $data)) {
            $data['includes'] ??= [];
        }

        return $data;
    }

    protected function validateConsistency(Validator $validator, ?ToyotaServicePackage $current): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $durationMin = $this->filled('duration_min_minutes')
            ? $this->integer('duration_min_minutes')
            : ($current === null ? self::DEFAULT_DURATION_MIN : $current->duration_min_minutes);
        $durationMax = $this->filled('duration_max_minutes')
            ? $this->integer('duration_max_minutes')
            : ($current === null ? self::DEFAULT_DURATION_MAX : $current->duration_max_minutes);
        if ($durationMax < $durationMin) {
            $validator->errors()->add('duration_max_minutes', 'Durasi maksimum tidak boleh lebih kecil dari durasi minimum.');
        }

        $effectiveFrom = $this->filled('effective_from')
            ? $this->string('effective_from')->toString()
            : ($current === null ? now('Asia/Jakarta')->toDateString() : $current->effective_from->toDateString());
        $effectiveTo = $this->has('effective_to') ? $this->input('effective_to') : $current?->effective_to?->toDateString();
        if (filled($effectiveTo) && Carbon::parse($effectiveTo)->lt(Carbon::parse($effectiveFrom))) {
            $validator->errors()->add('effective_to', 'Tanggal berlaku sampai tidak boleh sebelum tanggal berlaku dari.');
        }

        $model = $this->has('vehicle_model')
            ? self::normalizeModel($this->input('vehicle_model'))
            : $current?->vehicle_model;
        $kmInterval = $this->has('km_interval') ? $this->integer('km_interval') : $current?->km_interval;

        $duplicate = ToyotaServicePackage::query()
            ->where('km_interval', $kmInterval)
            ->when(
                $model === null,
                fn ($query) => $query->whereNull('vehicle_model'),
                fn ($query) => $query->whereRaw('lower(vehicle_model) = ?', [Str::lower((string) $model)]),
            )
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current?->getKey()))
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'km_interval',
                $model === null
                    ? 'Paket untuk semua model pada kelipatan km ini sudah ada. Ubah paket yang ada.'
                    : 'Paket untuk model '.$model.' pada kelipatan km ini sudah ada. Ubah paket yang ada.',
            );
        }
    }

    private static function normalizeModel(mixed $model): ?string
    {
        if (! is_string($model)) {
            return null;
        }
        $trimmed = trim(preg_replace('/\s+/', ' ', $model) ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
