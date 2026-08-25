<?php

namespace App\Services;

use App\Exceptions\OtoxpertConflictException;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertBookingStatusHistory;
use App\Models\User;
use App\Support\Enums\OtoxpertAdminAction;
use App\Support\Enums\OtoxpertBookingStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtoxpertBookingAdminService
{
    public function __construct(
        private readonly OtoxpertAvailabilityService $availability,
        private readonly OtoxpertNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        OtoxpertBooking $booking,
        User $actor,
        array $data,
    ): OtoxpertBooking {
        $action = OtoxpertAdminAction::from($data['action']);
        DB::transaction(function () use (
            $booking,
            $actor,
            $data,
            $action,
        ): void {
            /** @var OtoxpertBooking $locked */
            $locked = OtoxpertBooking::query()
                ->with(['workshop', 'service'])
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            if (! $locked->workshop->canBeManagedBy($actor)) {
                abort(403);
            }
            if (! in_array($action, $locked->availableAdminActions(), true)) {
                throw new OtoxpertConflictException(
                    'Aksi tidak tersedia pada status booking saat ini.'
                );
            }

            match ($action) {
                OtoxpertAdminAction::Assign => $this->assign(
                    $locked,
                    $actor,
                    $data,
                ),
                OtoxpertAdminAction::Confirm => $this->confirm(
                    $locked,
                    $actor,
                    $data,
                    false,
                ),
                OtoxpertAdminAction::ConfirmReschedule => $this->confirm(
                    $locked,
                    $actor,
                    $data,
                    true,
                ),
                OtoxpertAdminAction::ProposeAlternative => $this
                    ->proposeAlternative($locked, $actor, $data),
                OtoxpertAdminAction::Reject => $this->terminal(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::Rejected,
                    'rejected',
                    'Booking OtoXpert ditolak',
                    $data,
                ),
                OtoxpertAdminAction::CheckIn => $this->simpleTransition(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::Confirmed,
                    OtoxpertBookingStatus::CheckedIn,
                    'checked_in',
                    'Kendaraan sudah check-in',
                    ['checked_in_at' => now()],
                    $data,
                ),
                OtoxpertAdminAction::StartService => $this->simpleTransition(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::CheckedIn,
                    OtoxpertBookingStatus::InService,
                    'service_started',
                    'Servis sedang dikerjakan',
                    ['service_started_at' => now()],
                    $data,
                ),
                OtoxpertAdminAction::Complete => $this->simpleTransition(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::InService,
                    OtoxpertBookingStatus::Completed,
                    'completed',
                    'Servis selesai',
                    ['completed_at' => now()],
                    $data,
                ),
                OtoxpertAdminAction::MarkNoShow => $this->terminal(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::NoShow,
                    'no_show',
                    'Pelanggan tidak hadir',
                    $data,
                ),
                OtoxpertAdminAction::Cancel => $this->terminal(
                    $locked,
                    $actor,
                    OtoxpertBookingStatus::Cancelled,
                    'cancelled_by_staff',
                    'Booking OtoXpert dibatalkan',
                    $data,
                ),
            };
        }, 3);

        return $this->loadAdminRelations($booking->refresh());
    }

    /** @param array<string, mixed> $data */
    private function assign(
        OtoxpertBooking $booking,
        User $actor,
        array $data,
    ): void {
        /** @var User $operator */
        $operator = User::query()->findOrFail($data['operator_id']);
        if (! $operator->can('service_bookings.update')
            || (! $operator->hasAnyRole(['super-admin', 'admin'])
                && ! $booking->workshop->operators()
                    ->whereKey($operator->getKey())
                    ->wherePivot('is_active', true)
                    ->exists())) {
            throw ValidationException::withMessages([
                'operator_id' => [
                    'Operator tidak aktif pada workshop booking ini.',
                ],
            ]);
        }

        $booking->update([
            'assigned_operator_id' => $operator->getKey(),
            'internal_note' => $data['internal_note']
                ?? $booking->internal_note,
            'follow_up_outcome' => $data['follow_up_outcome']
                ?? $booking->follow_up_outcome,
        ]);
        $this->history(
            $booking,
            'operator_assigned',
            'Operator ditetapkan',
            "Ditangani oleh {$operator->name}.",
            $actor,
            false,
        );
    }

    /** @param array<string, mixed> $data */
    private function confirm(
        OtoxpertBooking $booking,
        User $actor,
        array $data,
        bool $reschedule,
    ): void {
        $expected = $reschedule
            ? OtoxpertBookingStatus::RescheduleRequested
            : OtoxpertBookingStatus::AwaitingConfirmation;
        if ($booking->status !== $expected) {
            throw new OtoxpertConflictException(
                'Booking tidak dapat dikonfirmasi pada status saat ini.'
            );
        }
        [$start, $end] = $this->availability->validateAndParseSlot(
            $data['slot'],
            $booking->workshop,
            $booking->service,
            'slot',
        );
        $allowed = $reschedule
            ? [
                [$booking->reschedule_primary_start_at, $booking->reschedule_primary_end_at],
                [$booking->reschedule_alternative_start_at, $booking->reschedule_alternative_end_at],
            ]
            : [
                [$booking->primary_start_at, $booking->primary_end_at],
                [$booking->alternative_start_at, $booking->alternative_end_at],
            ];
        if (! $this->matchesAny($start, $end, $allowed)) {
            throw new OtoxpertConflictException(
                'Jadwal di luar preferensi pelanggan harus diajukan sebagai alternatif.',
                'OTOXPERT_ALTERNATIVE_REQUIRED',
            );
        }

        $updates = [
            'status' => OtoxpertBookingStatus::Confirmed,
            'confirmed_start_at' => $start,
            'confirmed_end_at' => $end,
            'confirmed_at' => now(),
            'pic_name' => $data['pic_name'] ?? $booking->pic_name,
            'arrival_instructions' => $data['arrival_instructions']
                ?? $booking->arrival_instructions,
            'external_booking_number' => $data['external_booking_number']
                ?? $booking->external_booking_number,
            'reschedule_primary_start_at' => null,
            'reschedule_primary_end_at' => null,
            'reschedule_alternative_start_at' => null,
            'reschedule_alternative_end_at' => null,
            'reschedule_reason' => null,
            'reason_code' => null,
            'reason' => null,
            'last_status_changed_at' => now(),
        ];
        $this->applyOperationalFields($updates, $data);
        $booking->update($updates);
        $this->history(
            $booking,
            $reschedule ? 'reschedule_confirmed' : 'confirmed',
            $reschedule
                ? 'Jadwal ulang dikonfirmasi'
                : 'Booking dikonfirmasi',
            'Silakan datang sesuai jadwal dan instruksi bengkel.',
            $actor,
            true,
            metadata: ['confirmed_slot' => $this->slotAudit($start, $end)],
        );
        $this->notifications->record(
            $booking,
            $reschedule
                ? 'Jadwal ulang OtoXpert dikonfirmasi'
                : 'Booking OtoXpert dikonfirmasi',
            $this->confirmationBody($booking),
        );
    }

    /**
     * Isi notifikasi konfirmasi memuat tanggal, jam, dan bengkelnya, menjawab
     * pertanyaan Bp. Iyan (20 Agustus 2026) tentang bagaimana pelanggan tahu
     * dirinya sudah terjadwal.
     */
    private function confirmationBody(OtoxpertBooking $booking): string
    {
        $start = $booking->confirmed_start_at;
        $end = $booking->confirmed_end_at;
        if ($start === null) {
            return "Jadwal {$booking->reference_no} telah dikonfirmasi.";
        }

        $localStart = $start->copy()->timezone('Asia/Jakarta');
        $schedule = $localStart->translatedFormat('l, d F Y').' pukul '
            .$localStart->format('H:i');
        if ($end !== null) {
            $schedule .= '-'.$end->copy()->timezone('Asia/Jakarta')->format('H:i');
        }

        $booking->loadMissing('workshop');
        $place = trim($booking->workshop->name);

        return $place === ''
            ? "{$booking->reference_no} terjadwal {$schedule} WIB."
            : "{$booking->reference_no} terjadwal {$schedule} WIB di {$place}.";
    }

    /** @param array<string, mixed> $data */
    private function proposeAlternative(
        OtoxpertBooking $booking,
        User $actor,
        array $data,
    ): void {
        [$start, $end] = $this->availability->validateAndParseSlot(
            $data['slot'],
            $booking->workshop,
            $booking->service,
            'slot',
        );
        $context = $booking->confirmed_start_at !== null
            ? 'reschedule'
            : 'initial';
        $updates = [
            'status' => OtoxpertBookingStatus::AlternativeProposed,
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
            'proposal_context' => $context,
            'proposal_reason' => $data['reason'],
            'proposal_expires_at' => now()->addHours(24),
            'reason_code' => $data['reason_code'],
            'reason' => $data['reason'],
            'last_status_changed_at' => now(),
        ];
        $this->applyOperationalFields($updates, $data);
        $booking->update($updates);
        $this->history(
            $booking,
            'alternative_proposed',
            'Jadwal alternatif diajukan',
            $data['reason'],
            $actor,
            true,
            $data['reason_code'],
            [
                'proposed_slot' => $this->slotAudit($start, $end),
                'proposal_context' => $context,
                'expires_at' => $booking->proposal_expires_at
                    ?->toIso8601String(),
            ],
        );
        $this->notifications->record(
            $booking,
            'Jadwal alternatif OtoXpert',
            "Bengkel mengajukan jadwal lain untuk {$booking->reference_no}.",
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function terminal(
        OtoxpertBooking $booking,
        User $actor,
        OtoxpertBookingStatus $status,
        string $event,
        string $title,
        array $data,
    ): void {
        $updates = [
            'status' => $status,
            'reason_code' => $data['reason_code'],
            'reason' => $data['reason'],
            'last_status_changed_at' => now(),
        ];
        if ($status === OtoxpertBookingStatus::Cancelled) {
            $updates['cancelled_at'] = now();
        }
        $this->applyOperationalFields($updates, $data);
        $booking->update($updates);
        $this->history(
            $booking,
            $event,
            $title,
            $data['reason'],
            $actor,
            true,
            $data['reason_code'],
        );
        $this->notifications->record(
            $booking,
            $title,
            "{$booking->reference_no}: {$data['reason']}",
        );
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $data
     */
    private function applyOperationalFields(array &$updates, array $data): void
    {
        foreach ([
            'internal_note',
            'follow_up_outcome',
            'quoted_price_min',
            'quoted_price_max',
            'quoted_price_type',
            'quoted_price_valid_until',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }
        if (array_key_exists('quoted_price_min', $data)) {
            $updates['quoted_price_currency'] = 'IDR';
            $updates['quoted_price_source'] = 'workshop_confirmation';
        }
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $data
     */
    private function simpleTransition(
        OtoxpertBooking $booking,
        User $actor,
        OtoxpertBookingStatus $expected,
        OtoxpertBookingStatus $status,
        string $event,
        string $title,
        array $updates,
        array $data,
    ): void {
        if ($booking->status !== $expected) {
            throw new OtoxpertConflictException(
                'Transisi status tidak diizinkan.'
            );
        }
        $updates += [
            'status' => $status,
            'last_status_changed_at' => now(),
        ];
        $this->applyOperationalFields($updates, $data);
        $booking->update($updates);
        $this->history(
            $booking,
            $event,
            $title,
            null,
            $actor,
            true,
        );
        $this->notifications->record(
            $booking,
            $title,
            "Status {$booking->reference_no} diperbarui.",
        );
    }

    /**
     * @param  list<array{Carbon|null, Carbon|null}>  $slots
     */
    private function matchesAny(
        Carbon $start,
        Carbon $end,
        array $slots,
    ): bool {
        foreach ($slots as [$candidateStart, $candidateEnd]) {
            if ($candidateStart?->equalTo($start)
                && $candidateEnd?->equalTo($end)) {
                return true;
            }
        }

        return false;
    }

    private function history(
        OtoxpertBooking $booking,
        string $event,
        string $title,
        ?string $description,
        User $actor,
        bool $userVisible,
        ?string $reasonCode = null,
        ?array $metadata = null,
    ): void {
        $history = new OtoxpertBookingStatusHistory([
            'status' => $booking->status,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'reason_code' => $reasonCode,
            'user_visible' => $userVisible,
            'actor_type' => 'staff',
            'metadata' => $metadata,
        ]);
        $history->booking()->associate($booking);
        $history->changedBy()->associate($actor);
        $history->save();
    }

    public function loadAdminRelations(
        OtoxpertBooking $booking,
    ): OtoxpertBooking {
        return $booking->load([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'workshop',
            'service',
            'assignedOperator',
            'photos.asset',
            'statusHistories' => fn ($query) => $query->oldest('created_at'),
        ]);
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
