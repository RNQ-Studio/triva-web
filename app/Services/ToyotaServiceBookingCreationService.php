<?php

namespace App\Services;

use App\Exceptions\ToyotaServiceConflictException;
use App\Models\Appraisal;
use App\Models\Asset;
use App\Models\BodyPaintEstimate;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingPhoto;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBenefitCheck;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ToyotaServiceBookingCreationService
{
    public function __construct(
        private readonly ToyotaServiceAvailabilityService $availability,
        private readonly ToyotaServiceNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{booking: ToyotaServiceBooking, replayed: bool}
     */
    public function create(User $user, array $data): array
    {
        $vehicle = Vehicle::query()
            ->with('vehicleMake')
            ->whereKey($data['vehicle_id'])
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $location = ToyotaServiceLocation::query()->findOrFail($data['service_location_id']);
        $serviceType = ToyotaServiceType::query()->findOrFail($data['service_type_id']);
        $fulfillmentType = ToyotaServiceFulfillmentType::from($data['fulfillment_type']);

        $this->assertCustomerReady($user);
        $this->assertToyotaVehicle(
            $vehicle,
            $serviceType,
            isset($data['source_bp_estimate_id']),
        );
        [$primaryStart, $primaryEnd] = $this->availability->validateAndParseSlot(
            $data['primary_slot'],
            $location,
            $serviceType,
            $fulfillmentType,
            'primary_slot',
        );
        [$alternativeStart, $alternativeEnd] = $this->availability->validateAndParseSlot(
            $data['alternative_slot'],
            $location,
            $serviceType,
            $fulfillmentType,
            'alternative_slot',
        );
        $this->assertDifferentSlots(
            $primaryStart,
            $primaryEnd,
            $alternativeStart,
            $alternativeEnd,
        );

        if ($fulfillmentType === ToyotaServiceFulfillmentType::Ths) {
            $this->availability->assertThsCoverage(
                $location,
                $data['ths_city'],
                (float) $data['ths_latitude'],
                (float) $data['ths_longitude'],
            );
        }

        if (isset($data['source_appraisal_id'])) {
            $validSource = Appraisal::query()
                ->whereKey($data['source_appraisal_id'])
                ->where('user_id', $user->getKey())
                ->where('vehicle_id', $vehicle->getKey())
                ->exists();
            if (! $validSource) {
                throw ValidationException::withMessages([
                    'source_appraisal_id' => [
                        'Appraisal sumber harus dimiliki pelanggan dan memakai kendaraan yang sama.',
                    ],
                ]);
            }
        }

        if (isset($data['source_bp_estimate_id'])) {
            $sourceEstimate = BodyPaintEstimate::query()
                ->whereKey($data['source_bp_estimate_id'])
                ->where('user_id', $user->getKey())
                ->where('vehicle_id', $vehicle->getKey())
                ->first();
            if (
                $sourceEstimate === null
                || ! $sourceEstimate->status->exposesPublishedEstimate()
            ) {
                throw ValidationException::withMessages([
                    'source_bp_estimate_id' => [
                        'Estimasi Body & Paint sumber tidak valid untuk booking.',
                    ],
                ]);
            }
        }

        $fingerprint = $this->fingerprint($data);

        try {
            return DB::transaction(function () use (
                $user,
                $data,
                $vehicle,
                $location,
                $serviceType,
                $fulfillmentType,
                $primaryStart,
                $primaryEnd,
                $alternativeStart,
                $alternativeEnd,
                $fingerprint,
            ): array {
                $existing = ToyotaServiceBooking::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                        throw $this->idempotencyConflict();
                    }

                    return ['booking' => $this->loadRelations($existing), 'replayed' => true];
                }

                $duplicateExists = ToyotaServiceBooking::query()
                    ->active()
                    ->where('vehicle_id', $vehicle->getKey())
                    ->where('service_type_id', $serviceType->getKey())
                    ->where('active_slot_start_at', $primaryStart)
                    ->where('active_slot_end_at', $primaryEnd)
                    ->lockForUpdate()
                    ->exists();
                if ($duplicateExists) {
                    throw $this->duplicateConflict();
                }

                $photoAssetIds = $data['photo_asset_ids'] ?? [];
                if ($photoAssetIds !== []) {
                    $lockedPhotoIds = Asset::query()
                        ->whereIn('id', $photoAssetIds)
                        ->where('user_id', $user->getKey())
                        ->where('category', 'toyota-service-photo')
                        ->where('status', 'active')
                        ->where('is_protected', true)
                        ->whereDoesntHave('toyotaServiceBookingPhoto')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();
                    sort($lockedPhotoIds);
                    sort($photoAssetIds);
                    if ($lockedPhotoIds !== $photoAssetIds) {
                        throw ValidationException::withMessages([
                            'photo_asset_ids' => [
                                'Satu atau lebih foto sudah tidak tersedia untuk booking.',
                            ],
                        ]);
                    }
                }

                $submittedAt = now();
                $booking = new ToyotaServiceBooking([
                    'reference_no' => $this->referenceNumber($location->timezone),
                    'fulfillment_type' => $fulfillmentType,
                    'status' => ToyotaServiceBookingStatus::AwaitingConfirmation,
                    'idempotency_key' => $data['idempotency_key'],
                    'idempotency_fingerprint' => $fingerprint,
                    'current_mileage' => $data['current_mileage'],
                    'complaint' => $data['complaint'],
                    'primary_start_at' => $primaryStart,
                    'primary_end_at' => $primaryEnd,
                    'alternative_start_at' => $alternativeStart,
                    'alternative_end_at' => $alternativeEnd,
                    'active_slot_start_at' => $primaryStart,
                    'active_slot_end_at' => $primaryEnd,
                    'ths_address' => $data['ths_address'] ?? null,
                    'ths_city' => $data['ths_city'] ?? null,
                    'ths_latitude' => $data['ths_latitude'] ?? null,
                    'ths_longitude' => $data['ths_longitude'] ?? null,
                    'ths_location_notes' => $data['ths_location_notes'] ?? null,
                    'contact_channel' => $data['contact_channel'],
                    'source_appraisal_id' => $data['source_appraisal_id'] ?? null,
                    'source_bp_estimate_id' => $data['source_bp_estimate_id'] ?? null,
                    'campaign_source' => $data['campaign_source'] ?? null,
                    'campaign_metadata' => $data['campaign_metadata'] ?? null,
                    'submitted_at' => $submittedAt,
                    'due_at' => $submittedAt->copy()->addMinutes(
                        $location->confirmation_sla_minutes
                    ),
                    'last_status_changed_at' => $submittedAt,
                ]);
                $booking->user()->associate($user);
                $booking->vehicle()->associate($vehicle);
                $booking->serviceLocation()->associate($location);
                $booking->serviceType()->associate($serviceType);
                $booking->save();

                foreach ($photoAssetIds as $assetId) {
                    $photo = new ToyotaServiceBookingPhoto(['asset_id' => $assetId]);
                    $photo->booking()->associate($booking);
                    $photo->save();
                }
                foreach (VehicleBenefitType::cases() as $benefitType) {
                    $check = new VehicleBenefitCheck([
                        'benefit_type' => $benefitType,
                        'status' => VehicleBenefitStatus::PendingVerification,
                    ]);
                    $check->vehicle()->associate($vehicle);
                    $check->booking()->associate($booking);
                    $check->save();
                }

                $this->history($booking, $user, $primaryStart, $primaryEnd, $alternativeStart, $alternativeEnd);
                $this->notifications->record(
                    $booking,
                    'Permintaan servis diterima',
                    "Permintaan {$booking->reference_no} sedang menunggu konfirmasi petugas.",
                );

                return ['booking' => $this->loadRelations($booking), 'replayed' => false];
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = ToyotaServiceBooking::query()
                ->where('user_id', $user->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing !== null) {
                if (hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                    return ['booking' => $this->loadRelations($existing), 'replayed' => true];
                }

                throw $this->idempotencyConflict();
            }

            throw $this->mapUniqueConflict($exception);
        }
    }

    private function assertCustomerReady(User $user): void
    {
        if ($user->phone === null || trim($user->phone) === '') {
            throw ValidationException::withMessages([
                'phone' => ['Nomor ponsel wajib dilengkapi sebelum booking servis.'],
            ]);
        }
        if ($user->service_consent_at === null) {
            throw ValidationException::withMessages([
                'service_consent' => ['Persetujuan penggunaan data servis wajib diberikan.'],
            ]);
        }
    }

    private function assertToyotaVehicle(
        Vehicle $vehicle,
        ToyotaServiceType $serviceType,
        bool $fromBodyPaintEstimate,
    ): void {
        if ($fromBodyPaintEstimate && $serviceType->code === 'body-paint') {
            return;
        }
        $make = $vehicle->vehicle_make_id !== null
            ? $vehicle->vehicleMake->name
            : $vehicle->make;
        if (! Str::contains(Str::lower((string) $make), 'toyota')) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Booking Toyota Service hanya tersedia untuk kendaraan Toyota.'],
            ]);
        }
    }

    private function history(
        ToyotaServiceBooking $booking,
        User $user,
        Carbon $primaryStart,
        Carbon $primaryEnd,
        Carbon $alternativeStart,
        Carbon $alternativeEnd,
    ): void {
        $history = new ToyotaServiceBookingStatusHistory([
            'status' => $booking->status,
            'event' => 'request_submitted',
            'title' => 'Permintaan servis diterima',
            'description' => 'Jadwal pilihan adalah preferensi dan menunggu konfirmasi petugas.',
            'user_visible' => true,
            'actor_type' => 'customer',
            'metadata' => [
                'primary_slot' => $this->slotAudit($primaryStart, $primaryEnd),
                'alternative_slot' => $this->slotAudit($alternativeStart, $alternativeEnd),
            ],
        ]);
        $history->booking()->associate($booking);
        $history->changedBy()->associate($user);
        $history->save();
    }

    private function loadRelations(ToyotaServiceBooking $booking): ToyotaServiceBooking
    {
        return $booking->load([
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'serviceLocation',
            'serviceType',
            'assignedServiceAdvisor',
            'photos.asset',
            'benefitChecks',
            'statusHistories' => fn ($query) => $query
                ->where('user_visible', true)
                ->oldest('created_at'),
        ]);
    }

    private function referenceNumber(string $timezone): string
    {
        do {
            $random = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
            $reference = 'BTS-'.now($timezone)->format('Ymd').'-'.$random;
        } while (ToyotaServiceBooking::query()->where('reference_no', $reference)->exists());

        return $reference;
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        unset($data['service_consent']);

        return hash('sha256', json_encode(
            $this->sortRecursively($data),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
    }

    private function duplicateConflict(): ToyotaServiceConflictException
    {
        return new ToyotaServiceConflictException(
            'Booking aktif yang sama sudah ada untuk kendaraan, layanan, dan jadwal tersebut.',
            'TOYOTA_SERVICE_DUPLICATE_ACTIVE',
        );
    }

    private function idempotencyConflict(): ToyotaServiceConflictException
    {
        return new ToyotaServiceConflictException(
            'Idempotency-Key sudah digunakan untuk payload booking yang berbeda.',
            'TOYOTA_SERVICE_IDEMPOTENCY_CONFLICT',
        );
    }

    private function mapUniqueConflict(QueryException $exception): ToyotaServiceConflictException
    {
        return match ($this->uniqueConstraintName($exception)) {
            'tsb_active_duplicate_unique' => $this->duplicateConflict(),
            'tsb_user_idempotency_unique' => $this->idempotencyConflict(),
            default => new ToyotaServiceConflictException(
                'Booking tidak dapat dibuat karena konflik data.',
                'TOYOTA_SERVICE_CONFLICT',
            ),
        };
    }

    private function uniqueConstraintName(QueryException $exception): ?string
    {
        $detail = $exception->errorInfo[2] ?? $exception->getMessage();

        return preg_match("/constraint [\"']([^\"']+)[\"']/i", (string) $detail, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505';
    }

    private function assertDifferentSlots(
        Carbon $primaryStart,
        Carbon $primaryEnd,
        Carbon $alternativeStart,
        Carbon $alternativeEnd,
    ): void {
        if ($primaryStart->equalTo($alternativeStart) && $primaryEnd->equalTo($alternativeEnd)) {
            throw ValidationException::withMessages([
                'alternative_slot' => ['Jadwal alternatif harus berbeda dari jadwal utama.'],
            ]);
        }
    }

    /** @return array{start_at: string, end_at: string} */
    private function slotAudit(Carbon $start, Carbon $end): array
    {
        return [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ];
    }
}
