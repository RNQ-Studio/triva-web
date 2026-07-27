<?php

namespace App\Services;

use App\Models\BodyPaintPriceItem;
use App\Models\ToyotaServiceLocation;
use App\Models\User;
use App\Support\BodyPaintCatalog;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BodyPaintPriceMatrixCsvImportService
{
    private const HEADERS = [
        'matrix_code',
        'item_code',
        'version',
        'service_location_code',
        'vehicle_make_id',
        'vehicle_model_id',
        'vehicle_class',
        'panel_code',
        'damage_type',
        'severity',
        'work_type',
        'labor_low',
        'labor_high',
        'material_low',
        'material_high',
        'parts_low',
        'parts_high',
        'other_low',
        'other_high',
        'duration_min_hours',
        'duration_max_hours',
        'is_high_risk',
        'effective_from',
        'effective_to',
        'source_reference',
    ];

    /**
     * @return array{
     *     valid_count: int,
     *     error_count: int,
     *     rows: list<array<string, mixed>>,
     *     imported_count: int
     * }
     */
    public function process(
        UploadedFile $file,
        User $actor,
        bool $commit = false,
    ): array {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('CSV Body & Paint tidak dapat dibaca.');
        }

        try {
            $headers = fgetcsv($stream);
            if ($headers === false) {
                throw ValidationException::withMessages([
                    'file' => ['CSV kosong.'],
                ]);
            }
            $headers = array_map(
                fn (string $header): string => trim(
                    preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header,
                ),
                $headers,
            );
            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages([
                    'file' => [
                        'Header CSV tidak sesuai template price matrix Body & Paint.',
                    ],
                ]);
            }

            $rows = [];
            $valid = [];
            $line = 1;
            while (($values = fgetcsv($stream)) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count(self::HEADERS)) {
                    $rows[] = [
                        'line' => $line,
                        'status' => 'error',
                        'errors' => ['Jumlah kolom tidak sesuai header.'],
                    ];

                    continue;
                }
                /** @var array<string, string|null> $raw */
                $raw = array_combine(self::HEADERS, $values);
                $normalized = $this->normalize($raw);
                $validator = Validator::make(
                    $normalized,
                    $this->rules(),
                );
                if ($validator->fails()) {
                    $rows[] = [
                        'line' => $line,
                        'status' => 'error',
                        'item_code' => $normalized['item_code'],
                        'errors' => $validator->errors()->all(),
                    ];

                    continue;
                }
                $locationId = null;
                if ($normalized['service_location_code'] !== null) {
                    $locationId = ToyotaServiceLocation::query()
                        ->where('code', $normalized['service_location_code'])
                        ->value('id');
                    if ($locationId === null) {
                        $rows[] = [
                            'line' => $line,
                            'status' => 'error',
                            'item_code' => $normalized['item_code'],
                            'errors' => [
                                'Kode lokasi layanan tidak ditemukan.',
                            ],
                        ];

                        continue;
                    }
                }
                unset($normalized['service_location_code']);
                $normalized['service_location_id'] = $locationId;
                $valid[] = $normalized;
                $rows[] = [
                    'line' => $line,
                    'status' => 'valid',
                    'item_code' => $normalized['item_code'],
                    'matrix_code' => $normalized['matrix_code'],
                    'version' => $normalized['version'],
                ];
            }
        } finally {
            fclose($stream);
        }

        $errorCount = collect($rows)
            ->where('status', 'error')
            ->count();
        if ($commit && $errorCount > 0) {
            throw ValidationException::withMessages([
                'file' => [
                    'CSV memiliki baris tidak valid. Perbaiki semua error sebelum import.',
                ],
            ]);
        }

        $imported = 0;
        if ($commit) {
            DB::transaction(function () use (
                $valid,
                $actor,
                &$imported,
            ): void {
                foreach ($valid as $data) {
                    $item = BodyPaintPriceItem::query()->firstOrNew([
                        'matrix_code' => $data['matrix_code'],
                        'item_code' => $data['item_code'],
                        'version' => $data['version'],
                    ]);
                    if ($item->exists) {
                        throw ValidationException::withMessages([
                            'file' => [
                                "Versi {$data['matrix_code']}/{$data['item_code']}/{$data['version']} sudah ada dan immutable.",
                            ],
                        ]);
                    }
                    $item->fill($data);
                    $item->approved_by = $actor->getKey();
                    $item->approved_at = now();
                    $item->is_active = true;
                    $item->save();
                    $imported++;
                }
            }, 3);
        }

        return [
            'valid_count' => count($valid),
            'error_count' => $errorCount,
            'rows' => $rows,
            'imported_count' => $imported,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $nullable = static fn (?string $value): ?string => filled(
            trim((string) $value),
        ) ? trim((string) $value) : null;
        $integer = static fn (?string $value): int => (int) trim(
            (string) $value,
        );

        return [
            'matrix_code' => trim((string) $row['matrix_code']),
            'item_code' => trim((string) $row['item_code']),
            'version' => $integer($row['version']),
            'service_location_code' => $nullable(
                $row['service_location_code'],
            ),
            'vehicle_make_id' => $nullable($row['vehicle_make_id']) === null
                ? null
                : $integer($row['vehicle_make_id']),
            'vehicle_model_id' => $nullable($row['vehicle_model_id']) === null
                ? null
                : $integer($row['vehicle_model_id']),
            'vehicle_class' => $nullable($row['vehicle_class']),
            'panel_code' => trim((string) $row['panel_code']),
            'damage_type' => trim((string) $row['damage_type']),
            'severity' => trim((string) $row['severity']),
            'work_type' => trim((string) $row['work_type']),
            'labor_low' => $integer($row['labor_low']),
            'labor_high' => $integer($row['labor_high']),
            'material_low' => $integer($row['material_low']),
            'material_high' => $integer($row['material_high']),
            'parts_low' => $integer($row['parts_low']),
            'parts_high' => $integer($row['parts_high']),
            'other_low' => $integer($row['other_low']),
            'other_high' => $integer($row['other_high']),
            'duration_min_hours' => $integer($row['duration_min_hours']),
            'duration_max_hours' => $integer($row['duration_max_hours']),
            'is_high_risk' => filter_var(
                $row['is_high_risk'],
                FILTER_VALIDATE_BOOL,
            ),
            'effective_from' => trim((string) $row['effective_from']),
            'effective_to' => $nullable($row['effective_to']),
            'source_reference' => trim((string) $row['source_reference']),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'matrix_code' => ['required', 'string', 'max:64'],
            'item_code' => ['required', 'string', 'max:64'],
            'version' => ['required', 'integer', 'min:1', 'max:65535'],
            'service_location_code' => ['nullable', 'string', 'max:64'],
            'vehicle_make_id' => [
                'nullable',
                'integer',
                'exists:vehicle_makes,id',
            ],
            'vehicle_model_id' => [
                'nullable',
                'integer',
                'exists:vehicle_models,id',
            ],
            'vehicle_class' => ['nullable', 'string', 'max:40'],
            'panel_code' => [
                'required',
                Rule::in(BodyPaintCatalog::panelCodes()),
            ],
            'damage_type' => [
                'required',
                Rule::in(BodyPaintCatalog::damageTypeCodes()),
            ],
            'severity' => [
                'required',
                Rule::enum(BodyPaintSeverity::class),
                Rule::notIn([BodyPaintSeverity::Unsure->value]),
            ],
            'work_type' => [
                'required',
                Rule::enum(BodyPaintWorkType::class),
            ],
            'labor_low' => ['required', 'integer', 'min:0'],
            'labor_high' => ['required', 'integer', 'gte:labor_low'],
            'material_low' => ['required', 'integer', 'min:0'],
            'material_high' => ['required', 'integer', 'gte:material_low'],
            'parts_low' => ['required', 'integer', 'min:0'],
            'parts_high' => ['required', 'integer', 'gte:parts_low'],
            'other_low' => ['required', 'integer', 'min:0'],
            'other_high' => ['required', 'integer', 'gte:other_low'],
            'duration_min_hours' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'duration_max_hours' => [
                'required',
                'integer',
                'gte:duration_min_hours',
                'max:1000',
            ],
            'is_high_risk' => ['required', 'boolean'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:effective_from',
            ],
            'source_reference' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }
}
