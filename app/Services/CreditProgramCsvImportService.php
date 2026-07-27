<?php

namespace App\Services;

use App\Models\CreditProgram;
use App\Models\User;
use App\Support\Enums\CreditProgramStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreditProgramCsvImportService
{
    private const HEADERS = [
        'program_code',
        'version',
        'partner_name',
        'program_name',
        'city',
        'vehicle_model',
        'vehicle_variant',
        'model_year',
        'otr_price',
        'approved_discount',
        'minimum_dp_basis_points',
        'maximum_dp_basis_points',
        'tenor_months',
        'annual_flat_rate_basis_points',
        'administration_fee',
        'provision_fee',
        'upfront_insurance',
        'other_upfront_cost_label',
        'other_upfront_costs',
        'effective_from',
        'effective_to',
        'source_reference',
        'status',
    ];

    /**
     * @return array{
     *     program_count: int,
     *     tenor_count: int,
     *     errors: list<string>,
     *     records: list<array<string, mixed>>
     * }
     */
    public function preview(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                return $this->failedPreview('Header CSV tidak ditemukan.');
            }
            $headers = array_map(
                fn (mixed $value): string => trim((string) $value),
                $headers,
            );
            if ($headers !== self::HEADERS) {
                return $this->failedPreview(
                    'Urutan header CSV tidak sesuai template program kredit.',
                );
            }

            $groups = [];
            $errors = [];
            $rowNumber = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowIsBlank($values)) {
                    continue;
                }
                if (count($values) !== count(self::HEADERS)) {
                    $errors[] = "Baris {$rowNumber}: jumlah kolom tidak sesuai.";

                    continue;
                }
                /** @var array<string, string> $row */
                $row = array_combine(self::HEADERS, array_map(
                    fn (mixed $value): string => trim((string) $value),
                    $values,
                ));
                $validator = Validator::make($row, $this->rules());
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = "Baris {$rowNumber}: {$message}";
                    }

                    continue;
                }

                $normalized = $this->normalize($row);
                $key = implode('|', [
                    $normalized['program_code'],
                    $normalized['version'],
                    mb_strtolower((string) $normalized['city']),
                    mb_strtolower((string) $normalized['vehicle_model']),
                    mb_strtolower((string) $normalized['vehicle_variant']),
                ]);
                $program = $this->programFields($normalized);
                if (isset($groups[$key])
                    && $groups[$key]['program'] !== $program) {
                    $errors[] = "Baris {$rowNumber}: data program berbeda untuk kode, versi, kota, model, dan varian yang sama.";

                    continue;
                }
                $groups[$key] ??= [
                    'program' => $program,
                    'tenor_options' => [],
                ];
                $months = (int) $normalized['tenor_months'];
                if (isset($groups[$key]['tenor_options'][$months])) {
                    $errors[] = "Baris {$rowNumber}: tenor {$months} bulan duplikat.";

                    continue;
                }
                $groups[$key]['tenor_options'][$months] = $this->tenorFields(
                    $normalized,
                );
            }

            $records = array_values(array_map(
                function (array $group): array {
                    ksort($group['tenor_options']);

                    return [
                        ...$group['program'],
                        'tenor_options' => array_values(
                            $group['tenor_options']
                        ),
                    ];
                },
                $groups,
            ));

            return [
                'program_count' => count($records),
                'tenor_count' => array_sum(array_map(
                    fn (array $record): int => count(
                        $record['tenor_options']
                    ),
                    $records,
                )),
                'errors' => $errors,
                'records' => $records,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{program_count: int, tenor_count: int}
     */
    public function import(string $path, User $actor): array
    {
        $preview = $this->preview($path);
        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages([
                'file' => $preview['errors'],
            ]);
        }
        if ($preview['records'] === []) {
            throw ValidationException::withMessages([
                'file' => ['CSV tidak memuat program kredit.'],
            ]);
        }

        DB::transaction(function () use ($preview, $actor): void {
            foreach ($preview['records'] as $record) {
                $identity = collect($record)->only([
                    'program_code',
                    'version',
                    'city',
                    'vehicle_model',
                    'vehicle_variant',
                ])->all();
                $existing = CreditProgram::query()
                    ->where($identity)
                    ->first();
                if ($existing?->simulations()->exists()) {
                    throw ValidationException::withMessages([
                        'file' => [
                            "Program {$record['program_code']} versi {$record['version']} sudah dipakai. Buat versi baru.",
                        ],
                    ]);
                }
                if ($record['status'] === CreditProgramStatus::Approved->value) {
                    $record['approved_by'] = $actor->getKey();
                    $record['approved_at'] = now();
                } else {
                    $record['approved_by'] = null;
                    $record['approved_at'] = null;
                }
                CreditProgram::query()->updateOrCreate($identity, $record);
            }
        }, 3);

        return [
            'program_count' => $preview['program_count'],
            'tenor_count' => $preview['tenor_count'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'program_code' => ['required', 'string', 'max:64'],
            'version' => ['required', 'integer', 'min:1', 'max:65535'],
            'partner_name' => ['required', 'string', 'max:255'],
            'program_name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'vehicle_model' => ['required', 'string', 'max:255'],
            'vehicle_variant' => ['required', 'string', 'max:255'],
            'model_year' => [
                'nullable',
                'integer',
                'min:1980',
                'max:'.(now('Asia/Jakarta')->year + 2),
            ],
            'otr_price' => ['required', 'integer', 'min:1'],
            'approved_discount' => ['required', 'integer', 'min:0'],
            'minimum_dp_basis_points' => [
                'required',
                'integer',
                'min:0',
                'max:10000',
            ],
            'maximum_dp_basis_points' => [
                'required',
                'integer',
                'min:0',
                'max:10000',
                'gte:minimum_dp_basis_points',
            ],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:120'],
            'annual_flat_rate_basis_points' => [
                'required',
                'integer',
                'min:0',
                'max:10000',
            ],
            'administration_fee' => ['required', 'integer', 'min:0'],
            'provision_fee' => ['required', 'integer', 'min:0'],
            'upfront_insurance' => ['required', 'integer', 'min:0'],
            'other_upfront_cost_label' => [
                'nullable',
                'required_unless:other_upfront_costs,0',
                'string',
                'max:255',
            ],
            'other_upfront_costs' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:effective_from',
            ],
            'source_reference' => ['required', 'string', 'max:2000'],
            'status' => [
                'required',
                Rule::enum(CreditProgramStatus::class),
            ],
        ];
    }

    /** @param array<string, string> $row */
    private function normalize(array $row): array
    {
        foreach ([
            'version',
            'model_year',
            'otr_price',
            'approved_discount',
            'minimum_dp_basis_points',
            'maximum_dp_basis_points',
            'tenor_months',
            'annual_flat_rate_basis_points',
            'administration_fee',
            'provision_fee',
            'upfront_insurance',
            'other_upfront_costs',
        ] as $field) {
            $row[$field] = $row[$field] === ''
                ? null
                : (int) $row[$field];
        }
        $row['effective_to'] = $row['effective_to'] ?: null;
        $row['other_upfront_cost_label'] =
            $row['other_upfront_cost_label'] ?: null;

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function programFields(array $row): array
    {
        return collect($row)->only([
            'program_code',
            'version',
            'partner_name',
            'program_name',
            'city',
            'vehicle_model',
            'vehicle_variant',
            'model_year',
            'otr_price',
            'approved_discount',
            'minimum_dp_basis_points',
            'maximum_dp_basis_points',
            'effective_from',
            'effective_to',
            'source_reference',
            'status',
        ])->merge([
            'formula_strategy' => 'flat_rate',
            'formula_version' => 'flat-v1',
        ])->all();
    }

    /** @param array<string, mixed> $row */
    private function tenorFields(array $row): array
    {
        return collect($row)->only([
            'tenor_months',
            'annual_flat_rate_basis_points',
            'administration_fee',
            'provision_fee',
            'upfront_insurance',
            'other_upfront_cost_label',
            'other_upfront_costs',
        ])->all();
    }

    /** @param list<mixed> $values */
    private function rowIsBlank(array $values): bool
    {
        return collect($values)->every(
            fn (mixed $value): bool => trim((string) $value) === '',
        );
    }

    /**
     * @return array{
     *     program_count: int,
     *     tenor_count: int,
     *     errors: list<string>,
     *     records: list<array<string, mixed>>
     * }
     */
    private function failedPreview(string $message): array
    {
        return [
            'program_count' => 0,
            'tenor_count' => 0,
            'errors' => [$message],
            'records' => [],
        ];
    }
}
