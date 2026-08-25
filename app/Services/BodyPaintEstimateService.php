<?php

namespace App\Services;

use App\Exceptions\BodyPaintConflictException;
use App\Models\Appraisal;
use App\Models\Asset;
use App\Models\BodyPaintDamagePhoto;
use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintEstimateDamage;
use App\Models\BodyPaintEstimateItem;
use App\Models\BodyPaintPriceItem;
use App\Models\BodyPaintStatusHistory;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\BodyPaintCatalog;
use App\Support\Enums\AppraisalStatus;
use App\Support\Enums\BodyPaintEstimateStatus;
use App\Support\Enums\BodyPaintPhotoReviewStatus;
use App\Support\Enums\BodyPaintPhotoType;
use App\Support\Enums\BodyPaintSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BodyPaintEstimateService
{
    public function __construct(
        private readonly BodyPaintNotificationService $notifications,
        private readonly ToyotaServiceBookingCreationService $bookingCreation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{estimate: BodyPaintEstimate, replayed: bool}
     */
    public function create(User $user, array $data): array
    {
        $vehicle = Vehicle::query()
            ->whereKey($data['vehicle_id'])
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $appraisal = $this->sourceAppraisal($user, $vehicle, $data);
        $location = isset($data['service_location_id'])
            ? ToyotaServiceLocation::query()
                ->effective()
                ->where('supports_workshop', true)
                ->findOrFail($data['service_location_id'])
            : ToyotaServiceLocation::query()
                ->effective()
                ->where('supports_workshop', true)
                ->orderBy('code')
                ->first();
        $fingerprint = $this->fingerprint($data);

        try {
            return DB::transaction(function () use (
                $user,
                $data,
                $vehicle,
                $appraisal,
                $location,
                $fingerprint,
            ): array {
                $existing = BodyPaintEstimate::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                        throw $this->idempotencyConflict();
                    }

                    return [
                        'estimate' => $this->loadCustomerRelations($existing),
                        'replayed' => true,
                    ];
                }

                $estimate = new BodyPaintEstimate([
                    'reference_no' => $this->referenceNumber(),
                    'status' => BodyPaintEstimateStatus::Draft,
                    'customer_notes' => $data['customer_notes'] ?? null,
                    'is_insured' => (bool) ($data['is_insured'] ?? false),
                    'insurance_provider' => ($data['is_insured'] ?? false)
                        ? ($data['insurance_provider'] ?? null)
                        : null,
                    'campaign_source' => $data['campaign_source'] ?? null,
                    'campaign_metadata' => $data['campaign_metadata'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'request_fingerprint' => $fingerprint,
                    'requires_physical_inspection' => true,
                    'last_status_changed_at' => now(),
                ]);
                $estimate->user()->associate($user);
                $estimate->vehicle()->associate($vehicle);
                if ($appraisal !== null) {
                    $estimate->appraisal()->associate($appraisal);
                }
                if ($location !== null) {
                    $estimate->serviceLocation()->associate($location);
                }
                $estimate->save();
                $this->history(
                    $estimate,
                    $user,
                    'draft_created',
                    'Draft estimasi dibuat',
                    'Lengkapi panel, jenis kerusakan, dan foto sebelum dikirim.',
                    'customer',
                );

                return [
                    'estimate' => $this->loadCustomerRelations($estimate),
                    'replayed' => false,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23505') {
                throw $exception;
            }
            $existing = BodyPaintEstimate::query()
                ->where('user_id', $user->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing !== null) {
                if (hash_equals($existing->request_fingerprint, $fingerprint)) {
                    return [
                        'estimate' => $this->loadCustomerRelations($existing),
                        'replayed' => true,
                    ];
                }
                throw $this->idempotencyConflict();
            }
            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $damages
     */
    public function updateDamages(
        BodyPaintEstimate $estimate,
        User $user,
        array $damages,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $user, $damages): void {
            $locked = BodyPaintEstimate::query()
                ->whereKey($estimate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $locked->status->isCustomerEditable()) {
                throw $this->stateConflict();
            }

            $kept = [];
            foreach ($damages as $sort => $data) {
                $severity = BodyPaintSeverity::from($data['severity']);
                $damage = $locked->damages()->updateOrCreate(
                    [
                        'panel_code' => $data['panel_code'],
                        'damage_type' => $data['damage_type'],
                    ],
                    [
                        'customer_severity' => $severity,
                        'customer_note' => $data['note'] ?? null,
                        'is_high_risk' => $severity->isHighRisk()
                            || in_array(
                                $data['damage_type'],
                                BodyPaintCatalog::HIGH_RISK_DAMAGE_TYPES,
                                true,
                            ),
                        'sort_order' => $sort,
                    ],
                );
                $kept[] = $damage->getKey();
            }
            if (
                $locked->status
                    === BodyPaintEstimateStatus::NeedsCustomerAction
                && $locked->photos()
                    ->where(
                        'review_status',
                        BodyPaintPhotoReviewStatus::Rejected,
                    )
                    ->whereNotNull('damage_id')
                    ->whereNotIn('damage_id', $kept)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'damages' => [
                        'Panel dengan riwayat foto ditolak tidak dapat dihapus saat koreksi.',
                    ],
                ]);
            }
            $locked->damages()->whereNotIn('id', $kept)->delete();
            $locked->items()->delete();
            $locked->forceFill([
                'engine_total_low' => null,
                'engine_total_high' => null,
                'has_high_risk_damage' => false,
            ])->save();
            $this->history(
                $locked,
                $user,
                'damages_updated',
                'Data kerusakan diperbarui',
                count($damages).' kerusakan tercatat.',
                'customer',
            );
        }, 3);

        return $this->loadCustomerRelations($estimate->refresh());
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     */
    public function attachPhotos(
        BodyPaintEstimate $estimate,
        User $user,
        array $photos,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $user, $photos): void {
            $locked = BodyPaintEstimate::query()
                ->whereKey($estimate->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $locked->status->isCustomerEditable()) {
                throw $this->stateConflict();
            }

            $newAssetIds = collect($photos)
                ->pluck('asset_id')
                ->unique()
                ->values();
            $assets = Asset::query()
                ->whereKey($newAssetIds->all())
                ->where('user_id', $user->getKey())
                ->where('category', 'body-paint-estimate-photo')
                ->where('status', 'active')
                ->where('is_protected', true)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Asset $asset): string => $asset->getKey());
            if ($assets->count() !== $newAssetIds->count()) {
                throw ValidationException::withMessages([
                    'photos' => [
                        'Satu atau lebih unggahan foto tidak valid atau bukan milik Anda.',
                    ],
                ]);
            }

            $damageIds = collect($photos)
                ->pluck('damage_id')
                ->filter()
                ->unique()
                ->values();
            $ownedDamageIds = BodyPaintEstimateDamage::query()
                ->where('estimate_id', $locked->getKey())
                ->whereKey($damageIds->all())
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            if (count($ownedDamageIds) !== $damageIds->count()) {
                throw ValidationException::withMessages([
                    'photos' => [
                        'Satu atau lebih panel foto tidak termasuk dalam estimasi ini.',
                    ],
                ]);
            }

            $existingPhotos = BodyPaintDamagePhoto::query()
                ->whereIn('asset_id', $newAssetIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('asset_id');
            foreach ($existingPhotos as $existing) {
                if ($existing->estimate_id !== $locked->getKey()) {
                    throw ValidationException::withMessages([
                        'photos' => ['Satu atau lebih foto sudah digunakan.'],
                    ]);
                }
                if (
                    $existing->review_status
                    === BodyPaintPhotoReviewStatus::Rejected
                ) {
                    throw ValidationException::withMessages([
                        'photos' => [
                            'Foto yang sudah ditolak harus diganti dengan unggahan baru.',
                        ],
                    ]);
                }
            }

            $currentCount = $locked->photos()
                ->where(
                    'review_status',
                    '!=',
                    BodyPaintPhotoReviewStatus::Rejected,
                )
                ->count();
            $newCount = $newAssetIds
                ->reject(fn (string $assetId): bool => $existingPhotos->has($assetId))
                ->count();
            $maximumPhotos = (int) config('body_paint.maximum_photos', 10);
            if (
                $currentCount + $newCount
                > $maximumPhotos
            ) {
                throw ValidationException::withMessages([
                    'photos' => [
                        "Jumlah foto aktif maksimum adalah {$maximumPhotos}.",
                    ],
                ]);
            }

            $replacementAudit = [];
            foreach ($photos as $data) {
                /** @var BodyPaintDamagePhoto|null $existing */
                $existing = $existingPhotos->get($data['asset_id']);
                if ($existing !== null) {
                    if (
                        $locked->status
                        === BodyPaintEstimateStatus::NeedsCustomerAction
                    ) {
                        throw ValidationException::withMessages([
                            'photos' => [
                                'Perbaikan foto harus menggunakan unggahan baru.',
                            ],
                        ]);
                    }
                    $existing->forceFill([
                        'damage_id' => $data['damage_id'] ?? null,
                        'photo_type' => $data['photo_type'],
                        'review_status' => BodyPaintPhotoReviewStatus::Pending,
                        'rejection_reason_code' => null,
                        'rejection_reason' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ])->save();

                    continue;
                }

                $rejectedPhoto = null;
                if (
                    $locked->status
                    === BodyPaintEstimateStatus::NeedsCustomerAction
                ) {
                    $rejectedPhoto = $this->rejectedPhotoForReplacement(
                        $locked,
                        $data,
                    );
                    if ($rejectedPhoto === null) {
                        throw ValidationException::withMessages([
                            'photos' => [
                                'Unggahan baru harus menggantikan foto yang diminta estimator.',
                            ],
                        ]);
                    }
                }

                $photo = new BodyPaintDamagePhoto([
                    'asset_id' => $data['asset_id'],
                    'photo_type' => $data['photo_type'],
                    'review_status' => BodyPaintPhotoReviewStatus::Pending,
                ]);
                $photo->estimate()->associate($locked);
                if ($rejectedPhoto !== null) {
                    $photo->replacedPhoto()->associate($rejectedPhoto);
                }
                if (isset($data['damage_id'])) {
                    $photo->damage()->associate($data['damage_id']);
                }
                $photo->save();
                if ($rejectedPhoto !== null) {
                    $replacementAudit[] = [
                        'rejected_photo_id' => $rejectedPhoto->getKey(),
                        'replacement_photo_id' => $photo->getKey(),
                    ];
                }
            }
            $this->history(
                $locked,
                $user,
                'photos_updated',
                'Foto kerusakan diperbarui',
                'Foto akan diperiksa setelah permintaan dikirim.',
                'customer',
                $replacementAudit === []
                    ? null
                    : ['replacements' => $replacementAudit],
            );
        }, 3);

        return $this->loadCustomerRelations($estimate->refresh());
    }

    public function submit(
        BodyPaintEstimate $estimate,
        User $user,
        bool $isResubmission = false,
    ): BodyPaintEstimate {
        $result = DB::transaction(function () use (
            $estimate,
            $user,
            $isResubmission,
        ): BodyPaintEstimate {
            $locked = BodyPaintEstimate::query()
                ->whereKey($estimate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $locked->status->isCustomerEditable()) {
                throw $this->stateConflict();
            }
            if (
                $isResubmission
                && $locked->status !== BodyPaintEstimateStatus::NeedsCustomerAction
            ) {
                throw $this->stateConflict();
            }

            $locked->load(['damages.photos', 'vehicle']);
            $this->assertReadyToSubmit($locked);
            if ($user->service_consent_at === null) {
                $user->forceFill(['service_consent_at' => now()])->save();
            }
            $this->runEngine($locked);
            $submittedAt = now();
            $locked->forceFill([
                'submitted_at' => $locked->submitted_at ?? $submittedAt,
                'due_at' => $submittedAt->copy()->addMinutes(
                    (int) config('body_paint.estimator_sla_minutes', 120),
                ),
                'last_status_changed_at' => $submittedAt,
            ])->save();
            $title = $isResubmission
                ? 'Perbaikan data diterima'
                : 'Permintaan estimasi diterima';
            $description = $locked->status
                === BodyPaintEstimateStatus::AutoEstimated
                ? 'Estimasi awal berhasil dihitung dan menunggu validasi estimator.'
                : 'Permintaan akan diperiksa manual oleh estimator Body & Paint.';
            $this->history(
                $locked,
                $user,
                $isResubmission ? 'request_resubmitted' : 'request_submitted',
                $title,
                $description,
                'customer',
            );
            $this->notifications->record(
                $locked,
                $title,
                "{$locked->reference_no} sedang menunggu review estimator.",
            );

            return $locked;
        }, 3);

        return $this->loadCustomerRelations($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function decide(
        BodyPaintEstimate $estimate,
        User $user,
        array $data,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $user, $data): void {
            $locked = BodyPaintEstimate::query()
                ->whereKey($estimate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== BodyPaintEstimateStatus::EstimateReady) {
                throw $this->stateConflict();
            }
            $accepted = $data['decision'] === 'accept';
            $status = $accepted
                ? BodyPaintEstimateStatus::Accepted
                : BodyPaintEstimateStatus::Declined;
            $locked->forceFill([
                'status' => $status,
                'accepted_at' => $accepted ? now() : null,
                'declined_at' => $accepted ? null : now(),
                'last_status_changed_at' => now(),
            ])->save();
            $this->history(
                $locked,
                $user,
                $accepted ? 'estimate_accepted' : 'estimate_declined',
                $accepted ? 'Estimasi diterima' : 'Estimasi tidak dilanjutkan',
                $data['reason'] ?? null,
                'customer',
            );
        }, 3);

        return $this->loadCustomerRelations($estimate->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     estimate: BodyPaintEstimate,
     *     booking: ToyotaServiceBooking,
     *     replayed: bool
     * }
     */
    public function requestBooking(
        BodyPaintEstimate $estimate,
        User $user,
        array $data,
    ): array {
        $serviceType = ToyotaServiceType::query()
            ->effective()
            ->where('code', 'body-paint')
            ->where('supports_workshop', true)
            ->firstOrFail();

        return DB::transaction(function () use (
            $estimate,
            $user,
            $data,
            $serviceType,
        ): array {
            $locked = BodyPaintEstimate::query()
                ->whereKey($estimate->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array('request_booking', $locked->status->customerActions(), true)) {
                $existing = $locked->booking()->first();
                if ($existing !== null) {
                    return [
                        'estimate' => $this->loadCustomerRelations($locked),
                        'booking' => $existing->load([
                            'vehicle.vehicleMake',
                            'vehicle.vehicleModel',
                            'serviceLocation',
                            'serviceType',
                            'assignedServiceAdvisor',
                            'photos.asset',
                            'benefitChecks',
                            'statusHistories',
                        ]),
                        'replayed' => true,
                    ];
                }
                throw $this->stateConflict();
            }

            $result = $this->bookingCreation->create($user, [
                'idempotency_key' => $data['idempotency_key'],
                'vehicle_id' => $locked->vehicle_id,
                'service_location_id' => $data['service_location_id'],
                'service_type_id' => $serviceType->getKey(),
                'fulfillment_type' => 'workshop',
                'current_mileage' => $data['current_mileage'],
                'complaint' => $data['complaint'],
                'primary_slot' => $data['primary_slot'],
                'alternative_slot' => $data['alternative_slot'],
                'contact_channel' => $data['contact_channel'],
                'photo_asset_ids' => [],
                'source_appraisal_id' => $locked->appraisal_id,
                'source_bp_estimate_id' => $locked->getKey(),
                'campaign_source' => 'body_paint_estimate',
                'campaign_metadata' => null,
                'service_consent' => true,
            ]);
            $locked->forceFill([
                'status' => BodyPaintEstimateStatus::BookingRequested,
                'accepted_at' => $locked->accepted_at ?? now(),
                'last_status_changed_at' => now(),
            ])->save();
            $this->history(
                $locked,
                $user,
                'booking_requested',
                'Booking Body & Paint diminta',
                "Booking {$result['booking']->reference_no} menunggu konfirmasi petugas.",
                'customer',
                ['booking_id' => $result['booking']->getKey()],
            );

            return [
                'estimate' => $this->loadCustomerRelations($locked),
                'booking' => $result['booking'],
                'replayed' => $result['replayed'],
            ];
        }, 3);
    }

    public function loadCustomerRelations(
        BodyPaintEstimate $estimate,
    ): BodyPaintEstimate {
        return $estimate->load([
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'appraisal',
            'serviceLocation',
            'assignedEstimator',
            'damages.photos.asset',
            'photos.asset',
            'items.damage',
            'currentPublishedVersion.publisher',
            'statusHistories' => fn ($query) => $query
                ->where('user_visible', true)
                ->oldest('created_at'),
            'booking.serviceLocation',
            'booking.serviceType',
        ]);
    }

    public function loadAdminRelations(
        BodyPaintEstimate $estimate,
    ): BodyPaintEstimate {
        return $estimate->load([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'appraisal',
            'serviceLocation',
            'assignedEstimator',
            'damages.photos.asset',
            'photos.asset',
            'items.damage',
            'items.priceItem',
            'versions.publisher',
            'currentPublishedVersion.publisher',
            'statusHistories.changedBy',
            'booking.serviceLocation',
            'booking.serviceType',
        ]);
    }

    private function runEngine(BodyPaintEstimate $estimate): void
    {
        $estimate->items()->delete();
        $totalLow = 0;
        $totalHigh = 0;
        $allMatched = true;
        $hasHighRisk = false;
        $sort = 0;

        foreach ($estimate->damages as $damage) {
            $severity = $damage->customer_severity;
            $highRisk = $damage->is_high_risk
                || $severity->isHighRisk()
                || in_array(
                    $damage->damage_type,
                    BodyPaintCatalog::HIGH_RISK_DAMAGE_TYPES,
                    true,
                );
            $hasHighRisk = $hasHighRisk || $highRisk;
            if ($severity === BodyPaintSeverity::Unsure) {
                $allMatched = false;

                continue;
            }

            $candidates = BodyPaintPriceItem::query()
                ->effective()
                ->where('panel_code', $damage->panel_code)
                ->where('damage_type', $damage->damage_type)
                ->where('severity', $severity)
                ->where(function (Builder $query) use ($estimate): void {
                    $query->whereNull('service_location_id')
                        ->when(
                            $estimate->service_location_id !== null,
                            fn (Builder $builder): Builder => $builder->orWhere(
                                'service_location_id',
                                $estimate->service_location_id,
                            ),
                        );
                })
                ->where(function (Builder $query) use ($estimate): void {
                    $query->whereNull('vehicle_make_id')
                        ->when(
                            $estimate->vehicle->vehicle_make_id !== null,
                            fn (Builder $builder): Builder => $builder->orWhere(
                                'vehicle_make_id',
                                $estimate->vehicle->vehicle_make_id,
                            ),
                        );
                })
                ->where(function (Builder $query) use ($estimate): void {
                    $query->whereNull('vehicle_model_id')
                        ->when(
                            $estimate->vehicle->vehicle_model_id !== null,
                            fn (Builder $builder): Builder => $builder->orWhere(
                                'vehicle_model_id',
                                $estimate->vehicle->vehicle_model_id,
                            ),
                        );
                })
                ->orderByRaw(
                    '(CASE WHEN service_location_id IS NULL THEN 0 ELSE 4 END
                    + CASE WHEN vehicle_make_id IS NULL THEN 0 ELSE 2 END
                    + CASE WHEN vehicle_model_id IS NULL THEN 0 ELSE 1 END) DESC',
                )
                ->orderByDesc('version')
                ->get()
                ->unique(fn (BodyPaintPriceItem $item): string => $item->work_type->value);
            if ($candidates->isEmpty()) {
                $allMatched = false;

                continue;
            }

            foreach ($candidates as $priceItem) {
                $hasHighRisk = $hasHighRisk || $priceItem->is_high_risk;
                $item = new BodyPaintEstimateItem([
                    'price_item_id' => $priceItem->getKey(),
                    'matrix_code' => $priceItem->matrix_code,
                    'matrix_version' => $priceItem->version,
                    'panel_code' => $damage->panel_code,
                    'damage_type' => $damage->damage_type,
                    'severity' => $severity,
                    'work_type' => $priceItem->work_type,
                    'labor_low' => $priceItem->labor_low,
                    'labor_high' => $priceItem->labor_high,
                    'material_low' => $priceItem->material_low,
                    'material_high' => $priceItem->material_high,
                    'parts_low' => $priceItem->parts_low,
                    'parts_high' => $priceItem->parts_high,
                    'other_low' => $priceItem->other_low,
                    'other_high' => $priceItem->other_high,
                    'duration_min_hours' => $priceItem->duration_min_hours,
                    'duration_max_hours' => $priceItem->duration_max_hours,
                    'is_engine_item' => true,
                    'sort_order' => $sort++,
                ]);
                $item->estimate()->associate($estimate);
                $item->damage()->associate($damage);
                $item->save();
                $totalLow += $item->totalLow();
                $totalHigh += $item->totalHigh();
            }
        }

        $estimate->forceFill([
            'status' => $allMatched && ! $hasHighRisk
                ? BodyPaintEstimateStatus::AutoEstimated
                : BodyPaintEstimateStatus::ManualReview,
            'engine_total_low' => $allMatched ? $totalLow : null,
            'engine_total_high' => $allMatched ? $totalHigh : null,
            'has_high_risk_damage' => $hasHighRisk,
        ])->save();
    }

    private function assertReadyToSubmit(BodyPaintEstimate $estimate): void
    {
        if (
            $estimate->status === BodyPaintEstimateStatus::NeedsCustomerAction
            && $estimate->photos()
                ->where(
                    'review_status',
                    BodyPaintPhotoReviewStatus::Rejected,
                )
                ->whereDoesntHave('replacement')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'photos' => [
                    'Ganti seluruh foto yang ditolak sebelum mengirim ulang.',
                ],
            ]);
        }

        if ($estimate->damages->isEmpty()) {
            throw ValidationException::withMessages([
                'damages' => ['Tambahkan minimal satu panel yang rusak.'],
            ]);
        }
        $missing = $estimate->damages
            ->filter(fn (BodyPaintEstimateDamage $damage): bool => ! $damage
                ->photos
                ->contains(
                    fn (BodyPaintDamagePhoto $photo): bool => $photo->photo_type
                        === BodyPaintPhotoType::Close
                        && $photo->review_status
                            !== BodyPaintPhotoReviewStatus::Rejected,
                ))
            ->map(fn (BodyPaintEstimateDamage $damage): string => $damage->panel_code)
            ->values()
            ->all();
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'photos' => [
                    'Setiap panel wajib memiliki foto dekat yang dapat direview.',
                ],
            ]);
        }
        $hasContext = $estimate->photos->contains(
            fn (BodyPaintDamagePhoto $photo): bool => $photo->photo_type
                === BodyPaintPhotoType::Context
                && $photo->review_status
                    !== BodyPaintPhotoReviewStatus::Rejected,
        );
        if (! $hasContext) {
            throw ValidationException::withMessages([
                'photos' => ['Tambahkan minimal satu foto konteks kendaraan.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rejectedPhotoForReplacement(
        BodyPaintEstimate $estimate,
        array $data,
    ): ?BodyPaintDamagePhoto {
        return BodyPaintDamagePhoto::query()
            ->where('estimate_id', $estimate->getKey())
            ->where(
                'review_status',
                BodyPaintPhotoReviewStatus::Rejected,
            )
            ->where('photo_type', $data['photo_type'])
            ->when(
                isset($data['damage_id']),
                fn (Builder $query): Builder => $query->where(
                    'damage_id',
                    $data['damage_id'],
                ),
                fn (Builder $query): Builder => $query->whereNull('damage_id'),
            )
            ->whereDoesntHave('replacement')
            ->oldest('reviewed_at')
            ->oldest('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sourceAppraisal(
        User $user,
        Vehicle $vehicle,
        array $data,
    ): ?Appraisal {
        if (! isset($data['appraisal_id'])) {
            return null;
        }
        $appraisal = Appraisal::query()
            ->whereKey($data['appraisal_id'])
            ->where('user_id', $user->getKey())
            ->where('vehicle_id', $vehicle->getKey())
            ->firstOrFail();
        if (! in_array($appraisal->status, [
            AppraisalStatus::ResultReady,
            AppraisalStatus::AcceptedByCustomer,
            AppraisalStatus::RejectedByCustomer,
            AppraisalStatus::InspectionScheduled,
            AppraisalStatus::Converted,
            AppraisalStatus::Expired,
        ], true)) {
            throw ValidationException::withMessages([
                'appraisal_id' => [
                    'Appraisal sumber belum memiliki hasil yang dapat dilanjutkan.',
                ],
            ]);
        }

        return $appraisal;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function history(
        BodyPaintEstimate $estimate,
        ?User $actor,
        string $event,
        string $title,
        ?string $description,
        string $actorType,
        ?array $metadata = null,
    ): void {
        $history = new BodyPaintStatusHistory([
            'status' => $estimate->status,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'user_visible' => true,
            'actor_type' => $actorType,
            'metadata' => $metadata,
        ]);
        $history->estimate()->associate($estimate);
        if ($actor !== null) {
            $history->changedBy()->associate($actor);
        }
        $history->save();
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'BPE-'.now('Asia/Jakarta')->format('Ymd').'-'
                .strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
        } while (BodyPaintEstimate::query()->where('reference_no', $reference)->exists());

        return $reference;
    }

    private function idempotencyConflict(): BodyPaintConflictException
    {
        return new BodyPaintConflictException(
            'Idempotency-Key sudah digunakan untuk payload estimasi yang berbeda.',
            'BODY_PAINT_IDEMPOTENCY_CONFLICT',
        );
    }

    private function stateConflict(): BodyPaintConflictException
    {
        return new BodyPaintConflictException(
            'Aksi tidak tersedia pada status estimasi saat ini.',
            'BODY_PAINT_INVALID_STATE',
        );
    }
}
