<?php

namespace App\Services;

use App\Models\OtoxpertBooking;
use App\Models\OtoxpertBookingStatusHistory;
use App\Support\Enums\OtoxpertBookingStatus;
use Illuminate\Support\Facades\DB;

class OtoxpertAlternativeExpiryService
{
    public function __construct(
        private readonly OtoxpertNotificationService $notifications,
    ) {}

    public function expireDue(int $limit = 200): int
    {
        $ids = OtoxpertBooking::query()
            ->where('status', OtoxpertBookingStatus::AlternativeProposed)
            ->whereNotNull('proposal_expires_at')
            ->where('proposal_expires_at', '<=', now())
            ->orderBy('proposal_expires_at')
            ->limit($limit)
            ->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count): void {
                /** @var OtoxpertBooking|null $booking */
                $booking = OtoxpertBooking::query()
                    ->lockForUpdate()
                    ->find($id);
                if ($booking !== null && $this->reconcileLocked($booking)) {
                    $count++;
                }
            }, 3);
        }

        return $count;
    }

    public function reconcileLocked(OtoxpertBooking $booking): bool
    {
        if ($booking->status !== OtoxpertBookingStatus::AlternativeProposed
            || $booking->proposal_expires_at === null
            || $booking->proposal_expires_at->isFuture()) {
            return false;
        }

        $isReschedule = $booking->proposal_context === 'reschedule'
            && $booking->confirmed_start_at !== null;
        $status = $isReschedule
            ? OtoxpertBookingStatus::Confirmed
            : OtoxpertBookingStatus::Expired;
        $booking->update([
            'status' => $status,
            'proposed_start_at' => null,
            'proposed_end_at' => null,
            'proposal_context' => null,
            'proposal_reason' => null,
            'proposal_expires_at' => null,
            'last_status_changed_at' => now(),
        ]);

        $history = new OtoxpertBookingStatusHistory([
            'status' => $status,
            'event' => 'alternative_expired',
            'title' => $isReschedule
                ? 'Usulan jadwal ulang kedaluwarsa'
                : 'Jadwal alternatif kedaluwarsa',
            'description' => $isReschedule
                ? 'Jadwal terkonfirmasi sebelumnya tetap berlaku.'
                : 'Hubungi bengkel atau buat permintaan booking baru.',
            'reason_code' => 'alternative_expired',
            'user_visible' => true,
            'actor_type' => 'system',
        ]);
        $history->booking()->associate($booking);
        $history->save();
        $this->notifications->record(
            $booking,
            $history->title,
            $history->description ?? '',
        );

        return true;
    }
}
