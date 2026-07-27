<?php

namespace App\Services;

use App\Exceptions\ToyotaServiceConflictException;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Models\User;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToyotaServiceBookingService
{
    public function __construct(
        private readonly ToyotaServiceAvailabilityService $availability,
        private readonly ToyotaServiceNotificationService $notifications,
        private readonly ToyotaServiceAlternativeExpiryService $alternativeExpiry,
    ) {}

    public function acceptAlternative(
        ToyotaServiceBooking $booking,
        User $customer,
    ): ToyotaServiceBooking {
        $expired = false;

        try {
            DB::transaction(function () use ($booking, $customer, &$expired): void {
                /** @var ToyotaServiceBooking $locked */
                $locked = ToyotaServiceBooking::query()
                    ->with(['serviceLocation', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($booking->getKey());
                $this->assertStatus(
                    $locked,
                    ToyotaServiceBookingStatus::AlternativeProposed,
                    'Tidak ada jadwal alternatif yang dapat diterima.',
                );

                if ($this->alternativeExpiry->reconcileLocked($locked)) {
                    $expired = true;

                    return;
                }

                if ($locked->proposed_start_at === null || $locked->proposed_end_at === null) {
                    throw new ToyotaServiceConflictException('Data jadwal alternatif tidak lengkap.');
                }

                $acceptedStart = $locked->proposed_start_at;
                $acceptedEnd = $locked->proposed_end_at;
                $proposalContext = $locked->proposal_context;
                $this->transition($locked, ToyotaServiceBookingStatus::Confirmed, [
                    'confirmed_start_at' => $acceptedStart,
                    'confirmed_end_at' => $acceptedEnd,
                    'active_slot_start_at' => $acceptedStart,
                    'active_slot_end_at' => $acceptedEnd,
                    'confirmed_at' => now(),
                    'pic_name' => $locked->proposed_pic_name ?? $locked->pic_name,
                    'arrival_instructions' => $locked->proposed_arrival_instructions
                        ?? $locked->arrival_instructions,
                    'external_booking_number' => $locked->proposed_external_booking_number
                        ?? ($proposalContext === 'reschedule'
                            ? $locked->external_booking_number
                            : null),
                    'proposed_start_at' => null,
                    'proposed_end_at' => null,
                    'proposal_context' => null,
                    'proposal_reason' => null,
                    'proposal_expires_at' => null,
                    'proposed_pic_name' => null,
                    'proposed_arrival_instructions' => null,
                    'proposed_external_booking_number' => null,
                    'reschedule_primary_start_at' => null,
                    'reschedule_primary_end_at' => null,
                    'reschedule_alternative_start_at' => null,
                    'reschedule_alternative_end_at' => null,
                    'reschedule_reason' => null,
                    'reason_code' => null,
                    'reason' => null,
                ]);
                $this->history(
                    $locked,
                    'alternative_accepted',
                    'Jadwal alternatif diterima',
                    'Booking telah dikonfirmasi pada jadwal yang disetujui.',
                    $customer,
                    'customer',
                    metadata: [
                        'accepted_slot' => $this->slotAudit(
                            $acceptedStart,
                            $acceptedEnd,
                        ),
                        'proposal_context' => $proposalContext,
                    ],
                );
                $this->notifyAfterCommit(
                    $locked,
                    'Booking Toyota dikonfirmasi',
                    "Jadwal {$locked->reference_no} telah dikonfirmasi.",
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->throwMappedUniqueViolation($exception);
        }

        if ($expired) {
            throw new ToyotaServiceConflictException(
                'Jadwal alternatif sudah kedaluwarsa.',
                'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED',
            );
        }

        return $this->loadCustomerRelations($booking->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rejectAlternative(
        ToyotaServiceBooking $booking,
        User $customer,
        array $data,
    ): ToyotaServiceBooking {
        $expired = false;

        try {
            DB::transaction(function () use (
                $booking,
                $customer,
                $data,
                &$expired,
            ): void {
                /** @var ToyotaServiceBooking $locked */
                $locked = ToyotaServiceBooking::query()
                    ->with(['serviceLocation', 'serviceType', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($booking->getKey());
                $this->assertStatus(
                    $locked,
                    ToyotaServiceBookingStatus::AlternativeProposed,
                    'Tidak ada jadwal alternatif yang dapat ditolak.',
                );

                if ($this->alternativeExpiry->reconcileLocked($locked)) {
                    $expired = true;

                    return;
                }

                [$primaryStart, $primaryEnd] = $this->availability->validateAndParseSlot(
                    $data['primary_slot'],
                    $locked->serviceLocation,
                    $locked->serviceType,
                    $locked->fulfillment_type,
                    'primary_slot',
                );
                [$alternativeStart, $alternativeEnd] = $this->availability->validateAndParseSlot(
                    $data['alternative_slot'],
                    $locked->serviceLocation,
                    $locked->serviceType,
                    $locked->fulfillment_type,
                    'alternative_slot',
                );
                $this->assertDifferentSlots(
                    $primaryStart,
                    $primaryEnd,
                    $alternativeStart,
                    $alternativeEnd,
                );

                $isConfirmedReschedule = $locked->proposal_context === 'reschedule'
                    && $locked->confirmed_start_at !== null;
                $proposalContext = $locked->proposal_context;
                $proposedSlot = $this->slotAudit(
                    $locked->proposed_start_at,
                    $locked->proposed_end_at,
                );
                $nextStatus = $isConfirmedReschedule
                    ? ToyotaServiceBookingStatus::RescheduleRequested
                    : ToyotaServiceBookingStatus::AwaitingConfirmation;
                $updates = [
                    'proposed_start_at' => null,
                    'proposed_end_at' => null,
                    'proposal_context' => null,
                    'proposal_reason' => null,
                    'proposal_expires_at' => null,
                    'proposed_pic_name' => null,
                    'proposed_arrival_instructions' => null,
                    'proposed_external_booking_number' => null,
                    'due_at' => now()->addMinutes($locked->serviceLocation->confirmation_sla_minutes),
                ];

                if ($isConfirmedReschedule) {
                    $updates += [
                        'reschedule_primary_start_at' => $primaryStart,
                        'reschedule_primary_end_at' => $primaryEnd,
                        'reschedule_alternative_start_at' => $alternativeStart,
                        'reschedule_alternative_end_at' => $alternativeEnd,
                        'reschedule_reason' => $data['reason'] ?? 'Jadwal usulan petugas belum sesuai.',
                    ];
                } else {
                    $updates += [
                        'primary_start_at' => $primaryStart,
                        'primary_end_at' => $primaryEnd,
                        'alternative_start_at' => $alternativeStart,
                        'alternative_end_at' => $alternativeEnd,
                        'active_slot_start_at' => $primaryStart,
                        'active_slot_end_at' => $primaryEnd,
                    ];
                }

                $this->transition($locked, $nextStatus, $updates);
                $this->history(
                    $locked,
                    'alternative_rejected',
                    'Jadwal alternatif ditolak',
                    $isConfirmedReschedule
                        ? 'Preferensi jadwal ulang pelanggan menunggu konfirmasi petugas.'
                        : 'Preferensi baru telah dikirim dan menunggu konfirmasi petugas.',
                    $customer,
                    'customer',
                    metadata: [
                        'primary_slot' => $this->slotAudit($primaryStart, $primaryEnd),
                        'alternative_slot' => $this->slotAudit($alternativeStart, $alternativeEnd),
                        'rejected_proposed_slot' => $proposedSlot,
                        'proposal_context' => $proposalContext,
                    ],
                );
                $this->notifyAfterCommit(
                    $locked,
                    'Preferensi jadwal diperbarui',
                    "Permintaan {$locked->reference_no} kembali menunggu konfirmasi.",
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->throwMappedUniqueViolation($exception);
        }

        if ($expired) {
            throw new ToyotaServiceConflictException(
                'Jadwal alternatif sudah kedaluwarsa.',
                'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED',
            );
        }

        return $this->loadCustomerRelations($booking->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestReschedule(
        ToyotaServiceBooking $booking,
        User $customer,
        array $data,
    ): ToyotaServiceBooking {
        $location = $booking->serviceLocation;
        $serviceType = $booking->serviceType;
        [$primaryStart, $primaryEnd] = $this->availability->validateAndParseSlot(
            $data['primary_slot'],
            $location,
            $serviceType,
            $booking->fulfillment_type,
            'primary_slot',
        );
        [$alternativeStart, $alternativeEnd] = $this->availability->validateAndParseSlot(
            $data['alternative_slot'],
            $location,
            $serviceType,
            $booking->fulfillment_type,
            'alternative_slot',
        );
        $this->assertDifferentSlots($primaryStart, $primaryEnd, $alternativeStart, $alternativeEnd);

        DB::transaction(function () use (
            $booking,
            $customer,
            $data,
            $primaryStart,
            $primaryEnd,
            $alternativeStart,
            $alternativeEnd,
        ): void {
            /** @var ToyotaServiceBooking $locked */
            $locked = ToyotaServiceBooking::query()
                ->with('serviceLocation')
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->assertStatus(
                $locked,
                ToyotaServiceBookingStatus::Confirmed,
                'Booking tidak dapat dijadwal ulang pada status saat ini.',
            );

            $this->transition($locked, ToyotaServiceBookingStatus::RescheduleRequested, [
                'reschedule_primary_start_at' => $primaryStart,
                'reschedule_primary_end_at' => $primaryEnd,
                'reschedule_alternative_start_at' => $alternativeStart,
                'reschedule_alternative_end_at' => $alternativeEnd,
                'reschedule_reason' => $data['reason'],
                'due_at' => now()->addMinutes($locked->serviceLocation->confirmation_sla_minutes),
            ]);
            $this->history(
                $locked,
                'reschedule_requested',
                'Permintaan jadwal ulang dikirim',
                'Jadwal lama tetap berlaku sampai perubahan dikonfirmasi petugas.',
                $customer,
                'customer',
                metadata: [
                    'previous_confirmed_slot' => $this->slotAudit(
                        $locked->confirmed_start_at,
                        $locked->confirmed_end_at,
                    ),
                    'primary_slot' => $this->slotAudit($primaryStart, $primaryEnd),
                    'alternative_slot' => $this->slotAudit($alternativeStart, $alternativeEnd),
                ],
            );
            $this->notifyAfterCommit(
                $locked,
                'Permintaan jadwal ulang diterima',
                "Petugas akan memeriksa preferensi baru {$locked->reference_no}.",
            );
        }, 3);

        return $this->loadCustomerRelations($booking->refresh());
    }

    public function cancel(
        ToyotaServiceBooking $booking,
        User $customer,
        string $reason,
    ): ToyotaServiceBooking {
        DB::transaction(function () use ($booking, $customer, $reason): void {
            /** @var ToyotaServiceBooking $locked */
            $locked = ToyotaServiceBooking::query()
                ->with('serviceLocation')
                ->lockForUpdate()
                ->findOrFail($booking->getKey());

            if ($locked->user_id !== $customer->getKey() || ! $locked->canCustomerCancel()) {
                throw new ToyotaServiceConflictException(
                    'Booking tidak dapat dibatalkan pada status atau batas waktu saat ini.',
                    'TOYOTA_SERVICE_CANCELLATION_NOT_ALLOWED',
                );
            }

            $this->transition($locked, ToyotaServiceBookingStatus::Cancelled, [
                'reason_code' => 'cancelled_by_customer',
                'reason' => $reason,
                'cancelled_at' => now(),
            ]);
            $this->history(
                $locked,
                'cancelled_by_customer',
                'Booking dibatalkan',
                $reason,
                $customer,
                'customer',
                'cancelled_by_customer',
            );
            $this->notifyAfterCommit(
                $locked,
                'Booking dibatalkan',
                "Booking {$locked->reference_no} telah dibatalkan.",
            );
        }, 3);

        return $this->loadCustomerRelations($booking->refresh());
    }

    public function loadCustomerRelations(ToyotaServiceBooking $booking): ToyotaServiceBooking
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

    private function assertStatus(
        ToyotaServiceBooking $booking,
        ToyotaServiceBookingStatus $expected,
        string $message,
    ): void {
        if ($booking->status !== $expected) {
            throw new ToyotaServiceConflictException($message);
        }
    }

    private function transition(
        ToyotaServiceBooking $booking,
        ToyotaServiceBookingStatus $status,
        array $attributes = [],
    ): void {
        $booking->update([
            ...$attributes,
            'status' => $status,
            'last_status_changed_at' => now(),
        ]);
    }

    private function history(
        ToyotaServiceBooking $booking,
        string $event,
        string $title,
        ?string $description,
        ?User $actor,
        string $actorType,
        ?string $reasonCode = null,
        bool $userVisible = true,
        ?array $metadata = null,
    ): void {
        $history = new ToyotaServiceBookingStatusHistory([
            'status' => $booking->status,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'reason_code' => $reasonCode,
            'user_visible' => $userVisible,
            'actor_type' => $actorType,
            'metadata' => $metadata,
        ]);
        $history->booking()->associate($booking);
        if ($actor !== null) {
            $history->changedBy()->associate($actor);
        }
        $history->save();
    }

    private function notifyAfterCommit(
        ToyotaServiceBooking $booking,
        string $title,
        string $body,
    ): void {
        $this->notifications->record($booking, $title, $body);
    }

    private function duplicateConflict(): ToyotaServiceConflictException
    {
        return new ToyotaServiceConflictException(
            'Sudah ada booking aktif untuk kendaraan, layanan, dan waktu yang sama.',
            'TOYOTA_SERVICE_DUPLICATE_ACTIVE',
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505';
    }

    private function throwMappedUniqueViolation(QueryException $exception): never
    {
        if ($this->isUniqueViolation($exception)) {
            throw $this->duplicateConflict();
        }

        throw $exception;
    }

    private function assertDifferentSlots(
        mixed $primaryStart,
        mixed $primaryEnd,
        mixed $alternativeStart,
        mixed $alternativeEnd,
    ): void {
        if (
            $primaryStart->equalTo($alternativeStart)
            && $primaryEnd->equalTo($alternativeEnd)
        ) {
            throw ValidationException::withMessages([
                'alternative_slot' => [
                    'Jadwal alternatif harus berbeda dari jadwal utama.',
                ],
            ]);
        }
    }

    /**
     * @return array{start_at: string, end_at: string}|null
     */
    private function slotAudit(mixed $start, mixed $end): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        return [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ];
    }
}
