<?php

namespace App\Services;

use App\Exceptions\OtoxpertConflictException;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertBookingStatusHistory;
use App\Models\User;
use App\Support\Enums\OtoxpertBookingStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtoxpertBookingService
{
    public function __construct(
        private readonly OtoxpertAvailabilityService $availability,
        private readonly OtoxpertNotificationService $notifications,
        private readonly OtoxpertAlternativeExpiryService $alternativeExpiry,
    ) {}

    public function acceptAlternative(
        OtoxpertBooking $booking,
        User $customer,
    ): OtoxpertBooking {
        $expired = false;
        DB::transaction(function () use (
            $booking,
            $customer,
            &$expired,
        ): void {
            /** @var OtoxpertBooking $locked */
            $locked = OtoxpertBooking::query()
                ->with('workshop')
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->assertCustomer($locked, $customer);
            $this->assertStatus(
                $locked,
                OtoxpertBookingStatus::AlternativeProposed,
                'Tidak ada jadwal alternatif yang dapat diterima.',
            );
            if ($this->alternativeExpiry->reconcileLocked($locked)) {
                $expired = true;

                return;
            }
            if ($locked->proposed_start_at === null
                || $locked->proposed_end_at === null) {
                throw new OtoxpertConflictException(
                    'Data jadwal alternatif tidak lengkap.'
                );
            }

            $start = $locked->proposed_start_at;
            $end = $locked->proposed_end_at;
            $context = $locked->proposal_context;
            $locked->update([
                'status' => OtoxpertBookingStatus::Confirmed,
                'confirmed_start_at' => $start,
                'confirmed_end_at' => $end,
                'confirmed_at' => now(),
                'proposed_start_at' => null,
                'proposed_end_at' => null,
                'proposal_context' => null,
                'proposal_reason' => null,
                'proposal_expires_at' => null,
                'reschedule_primary_start_at' => null,
                'reschedule_primary_end_at' => null,
                'reschedule_alternative_start_at' => null,
                'reschedule_alternative_end_at' => null,
                'reschedule_reason' => null,
                'reason_code' => null,
                'reason' => null,
                'last_status_changed_at' => now(),
            ]);
            $this->history(
                $locked,
                'alternative_accepted',
                'Jadwal alternatif diterima',
                'Booking telah dikonfirmasi pada jadwal yang disetujui.',
                $customer,
                'customer',
                metadata: [
                    'accepted_slot' => $this->slotAudit($start, $end),
                    'proposal_context' => $context,
                ],
            );
            $this->notifications->record(
                $locked,
                'Booking OtoXpert dikonfirmasi',
                "Jadwal {$locked->reference_no} telah dikonfirmasi.",
            );
        }, 3);

        if ($expired) {
            throw new OtoxpertConflictException(
                'Jadwal alternatif sudah kedaluwarsa.',
                'OTOXPERT_ALTERNATIVE_EXPIRED',
            );
        }

        return $this->loadCustomerRelations($booking->refresh());
    }

    /** @param array<string, mixed> $data */
    public function rejectAlternative(
        OtoxpertBooking $booking,
        User $customer,
        array $data,
    ): OtoxpertBooking {
        $expired = false;
        DB::transaction(function () use (
            $booking,
            $customer,
            $data,
            &$expired,
        ): void {
            /** @var OtoxpertBooking $locked */
            $locked = OtoxpertBooking::query()
                ->with(['workshop', 'service'])
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->assertCustomer($locked, $customer);
            $this->assertStatus(
                $locked,
                OtoxpertBookingStatus::AlternativeProposed,
                'Tidak ada jadwal alternatif yang dapat ditolak.',
            );
            if ($this->alternativeExpiry->reconcileLocked($locked)) {
                $expired = true;

                return;
            }
            [$primaryStart, $primaryEnd] = $this->availability
                ->validateAndParseSlot(
                    $data['primary_slot'],
                    $locked->workshop,
                    $locked->service,
                    'primary_slot',
                );
            [$alternativeStart, $alternativeEnd] = $this->availability
                ->validateAndParseSlot(
                    $data['alternative_slot'],
                    $locked->workshop,
                    $locked->service,
                    'alternative_slot',
                );
            $this->assertDifferent(
                $primaryStart,
                $primaryEnd,
                $alternativeStart,
                $alternativeEnd,
            );

            $isReschedule = $locked->proposal_context === 'reschedule'
                && $locked->confirmed_start_at !== null;
            $status = $isReschedule
                ? OtoxpertBookingStatus::RescheduleRequested
                : OtoxpertBookingStatus::AwaitingConfirmation;
            $updates = [
                'status' => $status,
                'proposed_start_at' => null,
                'proposed_end_at' => null,
                'proposal_context' => null,
                'proposal_reason' => null,
                'proposal_expires_at' => null,
                'due_at' => now()->addMinutes(
                    $locked->workshop->confirmation_sla_minutes
                ),
                'last_status_changed_at' => now(),
            ];
            if ($isReschedule) {
                $updates += [
                    'reschedule_primary_start_at' => $primaryStart,
                    'reschedule_primary_end_at' => $primaryEnd,
                    'reschedule_alternative_start_at' => $alternativeStart,
                    'reschedule_alternative_end_at' => $alternativeEnd,
                    'reschedule_reason' => $data['reason'],
                ];
            } else {
                $updates += [
                    'primary_start_at' => $primaryStart,
                    'primary_end_at' => $primaryEnd,
                    'alternative_start_at' => $alternativeStart,
                    'alternative_end_at' => $alternativeEnd,
                ];
            }
            $locked->update($updates);
            $this->history(
                $locked,
                'alternative_rejected',
                'Jadwal alternatif ditolak',
                $isReschedule
                    ? 'Preferensi jadwal ulang baru menunggu konfirmasi.'
                    : 'Preferensi baru menunggu konfirmasi bengkel.',
                $customer,
                'customer',
                metadata: [
                    'primary_slot' => $this->slotAudit(
                        $primaryStart,
                        $primaryEnd,
                    ),
                    'alternative_slot' => $this->slotAudit(
                        $alternativeStart,
                        $alternativeEnd,
                    ),
                ],
            );
            $this->notifications->record(
                $locked,
                'Preferensi OtoXpert diperbarui',
                "Permintaan {$locked->reference_no} kembali menunggu konfirmasi.",
            );
        }, 3);

        if ($expired) {
            throw new OtoxpertConflictException(
                'Jadwal alternatif sudah kedaluwarsa.',
                'OTOXPERT_ALTERNATIVE_EXPIRED',
            );
        }

        return $this->loadCustomerRelations($booking->refresh());
    }

    /** @param array<string, mixed> $data */
    public function requestReschedule(
        OtoxpertBooking $booking,
        User $customer,
        array $data,
    ): OtoxpertBooking {
        $booking->loadMissing(['workshop', 'service']);
        [$primaryStart, $primaryEnd] = $this->availability
            ->validateAndParseSlot(
                $data['primary_slot'],
                $booking->workshop,
                $booking->service,
                'primary_slot',
            );
        [$alternativeStart, $alternativeEnd] = $this->availability
            ->validateAndParseSlot(
                $data['alternative_slot'],
                $booking->workshop,
                $booking->service,
                'alternative_slot',
            );
        $this->assertDifferent(
            $primaryStart,
            $primaryEnd,
            $alternativeStart,
            $alternativeEnd,
        );
        DB::transaction(function () use (
            $booking,
            $customer,
            $data,
            $primaryStart,
            $primaryEnd,
            $alternativeStart,
            $alternativeEnd,
        ): void {
            /** @var OtoxpertBooking $locked */
            $locked = OtoxpertBooking::query()
                ->with('workshop')
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->assertCustomer($locked, $customer);
            $this->assertStatus(
                $locked,
                OtoxpertBookingStatus::Confirmed,
                'Booking tidak dapat dijadwal ulang pada status saat ini.',
            );
            $locked->update([
                'status' => OtoxpertBookingStatus::RescheduleRequested,
                'reschedule_primary_start_at' => $primaryStart,
                'reschedule_primary_end_at' => $primaryEnd,
                'reschedule_alternative_start_at' => $alternativeStart,
                'reschedule_alternative_end_at' => $alternativeEnd,
                'reschedule_reason' => $data['reason'],
                'due_at' => now()->addMinutes(
                    $locked->workshop->confirmation_sla_minutes
                ),
                'last_status_changed_at' => now(),
            ]);
            $this->history(
                $locked,
                'reschedule_requested',
                'Permintaan jadwal ulang dikirim',
                'Jadwal lama tetap berlaku sampai perubahan dikonfirmasi.',
                $customer,
                'customer',
                metadata: [
                    'primary_slot' => $this->slotAudit(
                        $primaryStart,
                        $primaryEnd,
                    ),
                    'alternative_slot' => $this->slotAudit(
                        $alternativeStart,
                        $alternativeEnd,
                    ),
                ],
            );
            $this->notifications->record(
                $locked,
                'Permintaan jadwal ulang diterima',
                "Bengkel akan memeriksa preferensi baru {$locked->reference_no}.",
            );
        }, 3);

        return $this->loadCustomerRelations($booking->refresh());
    }

    public function cancel(
        OtoxpertBooking $booking,
        User $customer,
        string $reason,
    ): OtoxpertBooking {
        DB::transaction(function () use ($booking, $customer, $reason): void {
            /** @var OtoxpertBooking $locked */
            $locked = OtoxpertBooking::query()
                ->with('workshop')
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->assertCustomer($locked, $customer);
            if (! $locked->canCustomerCancel()) {
                throw new OtoxpertConflictException(
                    'Booking tidak dapat dibatalkan pada status atau batas waktu saat ini.',
                    'OTOXPERT_CANCELLATION_NOT_ALLOWED',
                );
            }
            $locked->update([
                'status' => OtoxpertBookingStatus::Cancelled,
                'reason_code' => 'cancelled_by_customer',
                'reason' => $reason,
                'cancelled_at' => now(),
                'last_status_changed_at' => now(),
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
            $this->notifications->record(
                $locked,
                'Booking OtoXpert dibatalkan',
                "Booking {$locked->reference_no} telah dibatalkan.",
            );
        }, 3);

        return $this->loadCustomerRelations($booking->refresh());
    }

    public function loadCustomerRelations(
        OtoxpertBooking $booking,
    ): OtoxpertBooking {
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

    private function assertCustomer(
        OtoxpertBooking $booking,
        User $customer,
    ): void {
        if ($booking->user_id !== $customer->getKey()) {
            throw new OtoxpertConflictException('Booking tidak ditemukan.');
        }
    }

    private function assertStatus(
        OtoxpertBooking $booking,
        OtoxpertBookingStatus $status,
        string $message,
    ): void {
        if ($booking->status !== $status) {
            throw new OtoxpertConflictException($message);
        }
    }

    private function history(
        OtoxpertBooking $booking,
        string $event,
        string $title,
        ?string $description,
        ?User $actor,
        string $actorType,
        ?string $reasonCode = null,
        bool $userVisible = true,
        ?array $metadata = null,
    ): void {
        $history = new OtoxpertBookingStatusHistory([
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

    private function assertDifferent(
        Carbon $primaryStart,
        Carbon $primaryEnd,
        Carbon $alternativeStart,
        Carbon $alternativeEnd,
    ): void {
        if ($primaryStart->equalTo($alternativeStart)
            && $primaryEnd->equalTo($alternativeEnd)) {
            throw ValidationException::withMessages([
                'alternative_slot' => [
                    'Jadwal alternatif harus berbeda dari jadwal utama.',
                ],
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
