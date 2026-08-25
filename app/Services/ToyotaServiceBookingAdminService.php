<?php

namespace App\Services;

use App\Exceptions\ToyotaServiceConflictException;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Models\User;
use App\Models\VehicleBenefitCheck;
use App\Support\Enums\BenefitVerificationSource;
use App\Support\Enums\ToyotaServiceAdminAction;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToyotaServiceBookingAdminService
{
    public function __construct(
        private readonly ToyotaServiceAvailabilityService $availability,
        private readonly ToyotaServiceNotificationService $notifications,
        private readonly ToyotaServiceAlternativeExpiryService $alternativeExpiry,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(
        ToyotaServiceBooking $booking,
        User $actor,
        ToyotaServiceAdminAction $action,
        array $data,
    ): ToyotaServiceBooking {
        $expired = false;
        try {
            DB::transaction(function () use (
                $booking,
                $actor,
                $action,
                $data,
                &$expired,
            ): void {
                /** @var ToyotaServiceBooking $locked */
                $locked = ToyotaServiceBooking::query()
                    ->with(['serviceLocation', 'serviceType', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($booking->getKey());

                if (
                    $this->alternativeExpiry->reconcileLocked($locked)
                    && $locked->status === ToyotaServiceBookingStatus::Expired
                ) {
                    $expired = true;

                    return;
                }

                if (! in_array($action, $locked->availableAdminActions(), true)) {
                    throw new ToyotaServiceConflictException(
                        "Aksi {$action->value} tidak diizinkan pada status {$locked->status->value}.",
                    );
                }

                if (
                    $locked->hasPendingReschedule()
                    && in_array($action, [
                        ToyotaServiceAdminAction::CheckIn,
                        ToyotaServiceAdminAction::MarkNoShow,
                    ], true)
                ) {
                    $this->closePendingRescheduleForOldAppointment($locked, $actor);
                }
                if (in_array($action, [
                    ToyotaServiceAdminAction::CheckIn,
                    ToyotaServiceAdminAction::MarkNoShow,
                ], true)) {
                    $locked->update([
                        'reason_code' => null,
                        'reason' => null,
                    ]);
                }

                $slot = null;
                if (in_array($action, [
                    ToyotaServiceAdminAction::Confirm,
                    ToyotaServiceAdminAction::ConfirmReschedule,
                ], true)) {
                    $slot = $this->availability->validateAndParseSlot(
                        $data['confirmed_slot'],
                        $locked->serviceLocation,
                        $locked->serviceType,
                        $locked->fulfillment_type,
                        'confirmed_slot',
                        false,
                    );
                } elseif ($action === ToyotaServiceAdminAction::ProposeAlternative) {
                    $slot = $this->availability->validateAndParseSlot(
                        $data['proposed_slot'],
                        $locked->serviceLocation,
                        $locked->serviceType,
                        $locked->fulfillment_type,
                        'proposed_slot',
                    );
                }

                match ($action) {
                    ToyotaServiceAdminAction::Assign => $this->assign($locked, $actor, $data),
                    ToyotaServiceAdminAction::Confirm => $this->confirm($locked, $actor, $data, $slot, false),
                    ToyotaServiceAdminAction::ProposeAlternative => $this->proposeAlternative(
                        $locked,
                        $actor,
                        $data,
                        $slot,
                    ),
                    ToyotaServiceAdminAction::Reject => $this->reject($locked, $actor, $data),
                    ToyotaServiceAdminAction::ConfirmReschedule => $this->confirm(
                        $locked,
                        $actor,
                        $data,
                        $slot,
                        true,
                    ),
                    ToyotaServiceAdminAction::CheckIn => $this->simpleTransition(
                        $locked,
                        $actor,
                        ToyotaServiceBookingStatus::CheckedIn,
                        'checked_in',
                        'Kendaraan sudah check-in',
                        'Kendaraan telah diterima di lokasi servis.',
                    ),
                    ToyotaServiceAdminAction::StartService => $this->simpleTransition(
                        $locked,
                        $actor,
                        ToyotaServiceBookingStatus::InService,
                        'service_started',
                        'Servis dimulai',
                        'Kendaraan sedang dikerjakan.',
                    ),
                    ToyotaServiceAdminAction::Complete => $this->complete($locked, $actor, $data),
                    ToyotaServiceAdminAction::MarkNoShow => $this->markNoShow(
                        $locked,
                        $actor,
                        $data,
                    ),
                    ToyotaServiceAdminAction::Cancel => $this->terminalWithReason(
                        $locked,
                        $actor,
                        ToyotaServiceBookingStatus::Cancelled,
                        'cancelled_by_staff',
                        'Booking dibatalkan petugas',
                        $data,
                    ),
                    ToyotaServiceAdminAction::VerifyBenefit => $this->verifyBenefit(
                        $locked,
                        $actor,
                        $data,
                    ),
                };
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw new ToyotaServiceConflictException(
                    'Sudah ada booking aktif untuk kendaraan, layanan, dan waktu yang sama.',
                    'TOYOTA_SERVICE_DUPLICATE_ACTIVE',
                );
            }

            throw $exception;
        }

        if ($expired) {
            throw new ToyotaServiceConflictException(
                'Jadwal alternatif sudah kedaluwarsa; status booking telah diperbarui.',
                'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED',
            );
        }

        return $this->loadAdminRelations($booking->refresh());
    }

    private function closePendingRescheduleForOldAppointment(
        ToyotaServiceBooking $booking,
        User $actor,
    ): void {
        $booking->update([
            'status' => ToyotaServiceBookingStatus::Confirmed,
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
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'reschedule_closed_for_old_appointment',
            'Jadwal lama tetap digunakan',
            'Proses jadwal ulang ditutup karena jadwal lama dijalankan.',
            $actor,
        );
    }

    public function loadAdminRelations(ToyotaServiceBooking $booking): ToyotaServiceBooking
    {
        return $booking->load([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'serviceLocation',
            'serviceType',
            'assignedServiceAdvisor',
            'photos.asset',
            'benefitChecks.verifiedBy',
            'statusHistories' => fn ($query) => $query
                ->with('changedBy')
                ->oldest('created_at'),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function assign(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
    ): void {
        $advisor = User::query()
            ->whereKey($data['advisor_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $advisor->can('service_bookings.update')) {
            throw ValidationException::withMessages([
                'advisor_id' => ['User yang dipilih bukan Service Advisor/staf booking berwenang.'],
            ]);
        }

        $booking->update(['assigned_service_advisor_id' => $advisor->getKey()]);
        $this->history(
            $booking,
            'advisor_assigned',
            'Service Advisor ditetapkan',
            null,
            $actor,
            false,
            metadata: ['advisor_id' => $advisor->getKey()],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: Carbon, 1: Carbon}|null  $slot
     */
    private function confirm(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
        ?array $slot,
        bool $isReschedule,
    ): void {
        if ($slot === null) {
            throw new ToyotaServiceConflictException('Jadwal konfirmasi tidak tersedia.');
        }

        [$start, $end] = $slot;
        if ($start->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'confirmed_slot' => ['Jadwal yang dikonfirmasi belum boleh dimulai atau berlalu.'],
            ]);
        }
        $matchesRequested = $isReschedule
            ? $this->matchesSlotPair(
                $start,
                $end,
                $booking->reschedule_primary_start_at,
                $booking->reschedule_primary_end_at,
                $booking->reschedule_alternative_start_at,
                $booking->reschedule_alternative_end_at,
            )
            : $this->matchesSlotPair(
                $start,
                $end,
                $booking->primary_start_at,
                $booking->primary_end_at,
                $booking->alternative_start_at,
                $booking->alternative_end_at,
            );

        if (! $matchesRequested) {
            throw ValidationException::withMessages([
                'confirmed_slot' => [
                    'Jadwal berbeda dari preferensi pelanggan harus diajukan sebagai alternatif.',
                ],
            ]);
        }

        $previousConfirmedSlot = $this->slotAudit(
            $booking->confirmed_start_at,
            $booking->confirmed_end_at,
        );
        $booking->update([
            'status' => ToyotaServiceBookingStatus::Confirmed,
            'confirmed_start_at' => $start,
            'confirmed_end_at' => $end,
            'active_slot_start_at' => $start,
            'active_slot_end_at' => $end,
            'assigned_service_advisor_id' => $booking->assigned_service_advisor_id
                ?? $actor->getKey(),
            'pic_name' => $data['pic_name'],
            'arrival_instructions' => $data['arrival_instructions'],
            'external_booking_number' => $data['external_booking_number']
                ?? ($isReschedule ? $booking->external_booking_number : null),
            'confirmed_at' => now(),
            'reason_code' => null,
            'reason' => null,
            'proposal_context' => null,
            'proposed_start_at' => null,
            'proposed_end_at' => null,
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
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            $isReschedule ? 'reschedule_confirmed' : 'booking_confirmed',
            $isReschedule ? 'Jadwal ulang dikonfirmasi' : 'Booking dikonfirmasi',
            'Jadwal, lokasi, PIC, dan instruksi kedatangan telah dikonfirmasi.',
            $actor,
            metadata: [
                'previous_confirmed_slot' => $previousConfirmedSlot,
                'confirmed_slot' => $this->slotAudit($start, $end),
            ],
        );
        if (filled($data['note'] ?? null)) {
            $this->history(
                $booking,
                'confirmation_internal_note',
                'Catatan internal konfirmasi',
                (string) $data['note'],
                $actor,
                false,
            );
        }
        $this->notifyAfterCommit(
            $booking,
            $isReschedule ? 'Jadwal ulang dikonfirmasi' : 'Booking Toyota dikonfirmasi',
            $this->confirmationBody($booking),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: Carbon, 1: Carbon}|null  $slot
     */
    private function proposeAlternative(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
        ?array $slot,
    ): void {
        if ($slot === null) {
            throw new ToyotaServiceConflictException('Jadwal alternatif tidak tersedia.');
        }

        [$start, $end] = $slot;
        $expiresAt = Carbon::parse($data['proposal_expires_at'])->utc();
        if ($expiresAt->greaterThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'proposal_expires_at' => [
                    'Batas respons harus lebih awal dari waktu mulai jadwal alternatif.',
                ],
            ]);
        }
        $context = $booking->proposal_context === 'reschedule'
            || in_array($booking->status, [
                ToyotaServiceBookingStatus::Confirmed,
                ToyotaServiceBookingStatus::RescheduleRequested,
            ], true)
                ? 'reschedule'
                : 'initial';
        if (
            $context === 'reschedule'
            && $booking->confirmed_start_at !== null
            && $expiresAt->greaterThanOrEqualTo($booking->confirmed_start_at)
        ) {
            throw ValidationException::withMessages([
                'proposal_expires_at' => [
                    'Batas respons harus lebih awal dari jadwal lama yang masih berlaku.',
                ],
            ]);
        }
        $booking->update([
            'status' => ToyotaServiceBookingStatus::AlternativeProposed,
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
            'proposal_context' => $context,
            'proposal_reason' => $data['proposal_reason'],
            'proposal_expires_at' => $expiresAt,
            'assigned_service_advisor_id' => $booking->assigned_service_advisor_id
                ?? $actor->getKey(),
            'proposed_pic_name' => $data['pic_name'],
            'proposed_arrival_instructions' => $data['arrival_instructions'],
            'proposed_external_booking_number' => $data['external_booking_number'] ?? null,
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'alternative_proposed',
            'Jadwal alternatif diajukan',
            $data['proposal_reason'],
            $actor,
            reasonCode: 'schedule_unavailable',
            metadata: [
                'proposal_context' => $context,
                'previous_confirmed_slot' => $this->slotAudit(
                    $booking->confirmed_start_at,
                    $booking->confirmed_end_at,
                ),
                'proposed_slot' => $this->slotAudit($start, $end),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        );
        $this->notifyAfterCommit(
            $booking,
            'Jadwal alternatif diajukan',
            "Booking {$booking->reference_no} memerlukan keputusan Anda.",
        );
    }

    /** @param array<string, mixed> $data */
    private function reject(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
    ): void {
        $booking->update([
            'status' => ToyotaServiceBookingStatus::Rejected,
            'reason_code' => $data['reason_code'],
            'reason' => $data['reason'],
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'request_rejected',
            'Permintaan servis ditolak',
            $data['reason'],
            $actor,
            reasonCode: $data['reason_code'],
        );
        $this->notifyAfterCommit(
            $booking,
            'Permintaan servis belum dapat diproses',
            "Lihat alasan pada {$booking->reference_no} dan buat permintaan baru bila diperlukan.",
        );
    }

    private function simpleTransition(
        ToyotaServiceBooking $booking,
        User $actor,
        ToyotaServiceBookingStatus $status,
        string $event,
        string $title,
        string $description,
    ): void {
        $booking->update([
            'status' => $status,
            'last_status_changed_at' => now(),
        ]);
        $this->history($booking, $event, $title, $description, $actor);
        $this->notifyAfterCommit($booking, $title, "{$booking->reference_no}: {$description}");
    }

    /** @param array<string, mixed> $data */
    private function complete(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
    ): void {
        $booking->update([
            'status' => ToyotaServiceBookingStatus::Completed,
            'completed_at' => now(),
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'service_completed',
            'Servis selesai',
            'Detail pekerjaan dan harga final mengikuti dokumen bengkel.',
            $actor,
        );
        if (filled($data['note'] ?? null)) {
            $this->history(
                $booking,
                'completion_internal_note',
                'Catatan internal penyelesaian',
                (string) $data['note'],
                $actor,
                false,
            );
        }
        $this->notifyAfterCommit(
            $booking,
            'Servis selesai',
            "Servis untuk {$booking->reference_no} telah selesai.",
        );
    }

    /** @param array<string, mixed> $data */
    private function terminalWithReason(
        ToyotaServiceBooking $booking,
        User $actor,
        ToyotaServiceBookingStatus $status,
        string $event,
        string $title,
        array $data,
    ): void {
        $booking->update([
            'status' => $status,
            'reason_code' => $data['reason_code'],
            'reason' => $data['reason'],
            'cancelled_at' => $status === ToyotaServiceBookingStatus::Cancelled ? now() : null,
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            $event,
            $title,
            $data['reason'],
            $actor,
            reasonCode: $data['reason_code'],
        );
        $this->notifyAfterCommit(
            $booking,
            $title,
            "Buka {$booking->reference_no} untuk melihat alasan dan tindak lanjut.",
        );
    }

    /** @param array<string, mixed> $data */
    private function markNoShow(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
    ): void {
        if ($booking->confirmed_end_at === null || now()->lt($booking->confirmed_end_at)) {
            throw ValidationException::withMessages([
                'action' => ['No-show hanya dapat dicatat setelah rentang jadwal terkonfirmasi berakhir.'],
            ]);
        }

        $this->terminalWithReason(
            $booking,
            $actor,
            ToyotaServiceBookingStatus::NoShow,
            'marked_no_show',
            'Tidak hadir',
            $data,
        );
    }

    /** @param array<string, mixed> $data */
    private function verifyBenefit(
        ToyotaServiceBooking $booking,
        User $actor,
        array $data,
    ): void {
        $benefitType = VehicleBenefitType::from($data['benefit_type']);
        $status = VehicleBenefitStatus::from($data['benefit_status']);
        $source = isset($data['verification_source'])
            ? BenefitVerificationSource::from($data['verification_source'])
            : null;

        if ($status->requiresVerificationEvidence() && $source === null) {
            throw ValidationException::withMessages([
                'verification_source' => ['Sumber verifikasi wajib untuk status aktif/tidak aktif.'],
            ]);
        }

        /** @var VehicleBenefitCheck $check */
        $check = VehicleBenefitCheck::query()
            ->where('service_booking_id', $booking->getKey())
            ->where('benefit_type', $benefitType)
            ->lockForUpdate()
            ->firstOrFail();
        $check->update([
            'status' => $status,
            'valid_until' => $data['benefit_valid_until'] ?? null,
            'verification_source' => $source,
            'verified_by' => $status->requiresVerificationEvidence() ? $actor->getKey() : null,
            'verified_at' => $status->requiresVerificationEvidence() ? now() : null,
            'notes' => $data['benefit_notes'] ?? null,
        ]);
        $this->history(
            $booking,
            'benefit_verified',
            "{$benefitType->label()} diperbarui",
            "Status: {$status->label()}.",
            $actor,
            metadata: [
                'benefit_type' => $benefitType->value,
                'benefit_status' => $status->value,
            ],
        );
        $this->notifyAfterCommit(
            $booking,
            'Verifikasi benefit diperbarui',
            "{$benefitType->label()} untuk {$booking->reference_no}: {$status->label()}.",
        );
    }

    private function history(
        ToyotaServiceBooking $booking,
        string $event,
        string $title,
        ?string $description,
        User $actor,
        bool $userVisible = true,
        ?string $reasonCode = null,
        ?array $metadata = null,
    ): void {
        $history = new ToyotaServiceBookingStatusHistory([
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

    private function notifyAfterCommit(
        ToyotaServiceBooking $booking,
        string $title,
        string $body,
    ): void {
        $this->notifications->record($booking, $title, $body);
    }

    private function matchesSlotPair(
        Carbon $start,
        Carbon $end,
        ?Carbon $firstStart,
        ?Carbon $firstEnd,
        ?Carbon $secondStart,
        ?Carbon $secondEnd,
    ): bool {
        return ($firstStart !== null && $firstEnd !== null
                && $start->equalTo($firstStart) && $end->equalTo($firstEnd))
            || ($secondStart !== null && $secondEnd !== null
                && $start->equalTo($secondStart) && $end->equalTo($secondEnd));
    }

    /**
     * @return array{start_at: string, end_at: string}|null
     */
    /**
     * Isi notifikasi konfirmasi memuat tanggal, jam, dan lokasi.
     *
     * Pertanyaan Bp. Iyan pada 20 Agustus 2026 -- "kalau customer booking,
     * caranya dia tahu kalau dia sudah terjadwal bagaimana?" -- dijawab dengan
     * memindahkan jadwalnya ke badan notifikasi, sehingga pelanggan tahu tanpa
     * perlu membuka aplikasi lebih dulu.
     */
    private function confirmationBody(ToyotaServiceBooking $booking): string
    {
        $start = $booking->confirmed_start_at;
        $end = $booking->confirmed_end_at;
        if ($start === null) {
            return "Buka {$booking->reference_no} untuk melihat jadwal dan instruksi.";
        }

        $localStart = $start->copy()->timezone('Asia/Jakarta');
        $schedule = $localStart->translatedFormat('l, d F Y').' pukul '
            .$localStart->format('H:i');
        if ($end !== null) {
            $schedule .= '-'.$end->copy()->timezone('Asia/Jakarta')->format('H:i');
        }

        $booking->loadMissing('serviceLocation');
        $place = trim($booking->serviceLocation->name);

        return $place === ''
            ? "{$booking->reference_no} terjadwal {$schedule} WIB."
            : "{$booking->reference_no} terjadwal {$schedule} WIB di {$place}.";
    }

    private function slotAudit(mixed $start, mixed $end): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        $startAt = $start instanceof Carbon ? $start : Carbon::parse((string) $start);
        $endAt = $end instanceof Carbon ? $end : Carbon::parse((string) $end);

        return [
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
        ];
    }
}
