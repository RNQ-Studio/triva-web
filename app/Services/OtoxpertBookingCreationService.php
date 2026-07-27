<?php

namespace App\Services;

use App\Exceptions\OtoxpertConflictException;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertBookingPhoto;
use App\Models\OtoxpertBookingStatusHistory;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\OtoxpertWorkshopServicePrice;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\OtoxpertBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtoxpertBookingCreationService
{
    public function __construct(
        private readonly OtoxpertAvailabilityService $availability,
        private readonly OtoxpertNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{booking: OtoxpertBooking, replayed: bool}
     */
    public function create(User $user, array $data): array
    {
        $fingerprint = $this->fingerprint($data);
        $existing = OtoxpertBooking::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();
        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw new OtoxpertConflictException(
                    'Idempotency-Key sudah dipakai untuk payload berbeda.',
                    'OTOXPERT_IDEMPOTENCY_CONFLICT',
                );
            }

            return [
                'booking' => $this->load($existing),
                'replayed' => true,
            ];
        }

        if (blank($user->phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor ponsel profil wajib dilengkapi.'],
            ]);
        }

        /** @var Vehicle $vehicle */
        $vehicle = $user->vehicles()->findOrFail($data['vehicle_id']);
        /** @var OtoxpertWorkshop $workshop */
        $workshop = OtoxpertWorkshop::query()->effective()->findOrFail(
            $data['workshop_id']
        );
        /** @var OtoxpertService $service */
        $service = OtoxpertService::query()->effective()->findOrFail(
            $data['service_id']
        );
        if (! $workshop->supportsVehicle($vehicle)) {
            throw ValidationException::withMessages([
                'workshop_id' => [
                    'Workshop belum mendukung merek kendaraan ini.',
                ],
            ]);
        }
        if (! $workshop->supportsService($service)) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'Layanan tidak tersedia pada workshop yang dipilih.',
                ],
            ]);
        }
        if (($data['pickup_delivery_requested'] ?? false)
            && ! $workshop->supports_pickup_delivery) {
            throw ValidationException::withMessages([
                'pickup_delivery_requested' => [
                    'Pickup/delivery belum tersedia pada workshop ini.',
                ],
            ]);
        }

        [$primaryStart, $primaryEnd] = $this->availability
            ->validateAndParseSlot(
                $data['primary_slot'],
                $workshop,
                $service,
                'primary_slot',
            );
        [$alternativeStart, $alternativeEnd] = $this->availability
            ->validateAndParseSlot(
                $data['alternative_slot'],
                $workshop,
                $service,
                'alternative_slot',
            );
        if ($primaryStart->equalTo($alternativeStart)
            && $primaryEnd->equalTo($alternativeEnd)) {
            throw ValidationException::withMessages([
                'alternative_slot' => [
                    'Jadwal alternatif harus berbeda dari jadwal utama.',
                ],
            ]);
        }

        try {
            /** @var OtoxpertBooking $booking */
            $booking = DB::transaction(function () use (
                $user,
                $vehicle,
                $workshop,
                $service,
                $data,
                $fingerprint,
                $primaryStart,
                $primaryEnd,
                $alternativeStart,
                $alternativeEnd,
            ): OtoxpertBooking {
                $price = OtoxpertWorkshopServicePrice::query()
                    ->effective()
                    ->where('workshop_id', $workshop->getKey())
                    ->where('service_id', $service->getKey())
                    ->latest('verified_at')
                    ->first();
                $booking = new OtoxpertBooking([
                    'reference_no' => $this->referenceNumber(),
                    'vehicle_id' => $vehicle->getKey(),
                    'workshop_id' => $workshop->getKey(),
                    'service_id' => $service->getKey(),
                    'status' => OtoxpertBookingStatus::AwaitingConfirmation,
                    'current_mileage' => $data['current_mileage'],
                    'last_service_date' => $data['last_service_date'] ?? null,
                    'complaint' => $data['complaint'],
                    'symptom_codes' => array_values($data['symptom_codes']),
                    'pickup_delivery_requested' => (bool) (
                        $data['pickup_delivery_requested'] ?? false
                    ),
                    'contact_channel' => ToyotaServiceContactChannel::from(
                        $data['contact_channel']
                    ),
                    'primary_start_at' => $primaryStart,
                    'primary_end_at' => $primaryEnd,
                    'alternative_start_at' => $alternativeStart,
                    'alternative_end_at' => $alternativeEnd,
                    'quoted_price_min' => $price?->minimum_amount,
                    'quoted_price_max' => $price?->maximum_amount,
                    'quoted_price_type' => $price?->price_type,
                    'quoted_price_currency' => $price?->currency,
                    'quoted_price_source' => $price?->source_url,
                    'quoted_price_valid_until' => $price?->effective_to,
                    'partner_consent_at' => now(),
                    'partner_consent_version' => $data[
                        'partner_consent_version'
                    ],
                    'campaign_source' => $data['campaign_source'] ?? null,
                    'campaign_metadata' => $data['campaign_metadata'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'request_fingerprint' => $fingerprint,
                    'submitted_at' => now(),
                    'due_at' => now()->addMinutes(
                        $workshop->confirmation_sla_minutes
                    ),
                    'last_status_changed_at' => now(),
                ]);
                $booking->user()->associate($user);
                $booking->save();

                foreach (
                    array_values($data['photo_asset_ids'] ?? []) as $index => $assetId
                ) {
                    $photo = new OtoxpertBookingPhoto([
                        'asset_id' => $assetId,
                        'sort_order' => $index,
                    ]);
                    $photo->booking()->associate($booking);
                    $photo->save();
                }

                $history = new OtoxpertBookingStatusHistory([
                    'status' => OtoxpertBookingStatus::AwaitingConfirmation,
                    'event' => 'submitted',
                    'title' => 'Permintaan booking diterima',
                    'description' => 'Workshop akan memeriksa layanan dan dua preferensi jadwal.',
                    'user_visible' => true,
                    'actor_type' => 'customer',
                    'metadata' => [
                        'primary_slot' => $this->slotAudit(
                            $primaryStart,
                            $primaryEnd,
                        ),
                        'alternative_slot' => $this->slotAudit(
                            $alternativeStart,
                            $alternativeEnd,
                        ),
                    ],
                ]);
                $history->booking()->associate($booking);
                $history->changedBy()->associate($user);
                $history->save();
                $this->notifications->record(
                    $booking,
                    'Booking OtoXpert diterima',
                    "Permintaan {$booking->reference_no} menunggu konfirmasi bengkel.",
                );

                return $booking;
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                $replay = OtoxpertBooking::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($replay !== null
                    && hash_equals($replay->request_fingerprint, $fingerprint)) {
                    return [
                        'booking' => $this->load($replay),
                        'replayed' => true,
                    ];
                }

                throw new OtoxpertConflictException(
                    'Sudah ada booking aktif untuk kendaraan, workshop, layanan, dan waktu yang sama.',
                    'OTOXPERT_DUPLICATE_ACTIVE',
                );
            }

            throw $exception;
        }

        return ['booking' => $this->load($booking), 'replayed' => false];
    }

    private function load(OtoxpertBooking $booking): OtoxpertBooking
    {
        return $booking->load([
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'workshop',
            'service',
            'assignedOperator',
            'photos.asset',
            'statusHistories' => fn ($query) => $query
                ->where('user_visible', true)
                ->oldest('created_at'),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        $canonical = $data;
        unset($canonical['idempotency_key']);
        ksort($canonical);

        return hash(
            'sha256',
            (string) json_encode(
                $canonical,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    private function referenceNumber(): string
    {
        return 'OX-'.now('Asia/Jakarta')->format('ymd').'-'
            .strtoupper(substr((string) Str::ulid(), -8));
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
