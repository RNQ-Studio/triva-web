<?php

namespace App\Services;

use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Support\Facades\DB;

class ToyotaServiceAlternativeExpiryService
{
    public function __construct(
        private readonly ToyotaServiceNotificationService $notifications,
    ) {}

    public function expireDue(): int
    {
        $ids = ToyotaServiceBooking::query()
            ->where('status', ToyotaServiceBookingStatus::AlternativeProposed)
            ->where(function ($query): void {
                $query->where('proposal_expires_at', '<=', now())
                    ->orWhere('proposed_start_at', '<=', now());
            })
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            if ($this->expireOne((string) $id)) {
                $expired++;
            }
        }

        return $expired;
    }

    private function expireOne(string $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            /** @var ToyotaServiceBooking|null $booking */
            $booking = ToyotaServiceBooking::query()
                ->with('user')
                ->lockForUpdate()
                ->find($bookingId);

            if ($booking === null) {
                return false;
            }

            return $this->reconcileLocked($booking);
        }, 3);
    }

    /**
     * Reconcile a row that is already locked by the caller.
     */
    public function reconcileLocked(ToyotaServiceBooking $booking): bool
    {
        $isDue = ($booking->proposal_expires_at !== null
                && $booking->proposal_expires_at->lessThanOrEqualTo(now()))
            || ($booking->proposed_start_at !== null
                && $booking->proposed_start_at->lessThanOrEqualTo(now()));
        if (
            $booking->status !== ToyotaServiceBookingStatus::AlternativeProposed
            || ! $isDue
        ) {
            return false;
        }

        $proposalMetadata = [
            'proposal_context' => $booking->proposal_context,
            'proposed_start_at' => $booking->proposed_start_at?->toIso8601String(),
            'proposed_end_at' => $booking->proposed_end_at?->toIso8601String(),
            'proposal_expires_at' => $booking->proposal_expires_at?->toIso8601String(),
        ];
        $preserveConfirmed = $booking->proposal_context === 'reschedule'
            && $booking->confirmed_start_at !== null
            && $booking->confirmed_end_at !== null;
        $status = $preserveConfirmed
            ? ToyotaServiceBookingStatus::Confirmed
            : ToyotaServiceBookingStatus::Expired;
        $booking->update([
            'status' => $status,
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
            'reason_code' => 'alternative_expired',
            'reason' => $preserveConfirmed
                ? 'Usulan jadwal ulang kedaluwarsa; jadwal lama tetap berlaku.'
                : 'Batas waktu respons jadwal alternatif telah lewat.',
            'last_status_changed_at' => now(),
        ]);

        $history = new ToyotaServiceBookingStatusHistory([
            'status' => $status,
            'event' => $preserveConfirmed
                ? 'reschedule_alternative_expired'
                : 'alternative_expired',
            'title' => $preserveConfirmed
                ? 'Usulan jadwal ulang kedaluwarsa'
                : 'Jadwal alternatif kedaluwarsa',
            'description' => $preserveConfirmed
                ? 'Jadwal booking lama tetap berlaku.'
                : 'Hubungi petugas atau buat permintaan servis baru.',
            'reason_code' => 'alternative_expired',
            'user_visible' => true,
            'actor_type' => 'system',
            'metadata' => $proposalMetadata,
        ]);
        $history->booking()->associate($booking);
        $history->save();

        $this->notifications->record(
            $booking,
            $preserveConfirmed
                ? 'Jadwal lama tetap berlaku'
                : 'Jadwal alternatif kedaluwarsa',
            $preserveConfirmed
                ? "Usulan jadwal ulang {$booking->reference_no} kedaluwarsa."
                : "Batas respons {$booking->reference_no} telah lewat.",
        );

        return true;
    }
}
