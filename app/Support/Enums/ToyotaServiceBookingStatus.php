<?php

namespace App\Support\Enums;

enum ToyotaServiceBookingStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case AlternativeProposed = 'alternative_proposed';
    case Confirmed = 'confirmed';
    case RescheduleRequested = 'reschedule_requested';
    case CheckedIn = 'checked_in';
    case InService = 'in_service';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';

    public function customerLabel(): string
    {
        return match ($this) {
            self::AwaitingConfirmation => 'Menunggu konfirmasi petugas',
            self::AlternativeProposed => 'Jadwal alternatif diajukan',
            self::Confirmed => 'Booking dikonfirmasi',
            self::RescheduleRequested => 'Permintaan jadwal ulang diproses',
            self::CheckedIn => 'Sudah check-in',
            self::InService => 'Sedang dikerjakan',
            self::Completed => 'Servis selesai',
            self::Rejected => 'Permintaan ditolak',
            self::Cancelled => 'Booking dibatalkan',
            self::Expired => 'Jadwal alternatif kedaluwarsa',
            self::NoShow => 'Tidak hadir',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
            self::Expired,
            self::NoShow,
        ], true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /** @return list<string> */
    public function customerActions(): array
    {
        return match ($this) {
            self::AwaitingConfirmation => ['cancel'],
            self::AlternativeProposed => ['accept_alternative', 'reject_alternative', 'cancel'],
            self::Confirmed => ['reschedule', 'cancel'],
            self::RescheduleRequested => ['cancel'],
            default => [],
        };
    }

    /** @return list<ToyotaServiceAdminAction> */
    public function adminActions(): array
    {
        $actions = match ($this) {
            self::AwaitingConfirmation => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::Confirm,
                ToyotaServiceAdminAction::ProposeAlternative,
                ToyotaServiceAdminAction::Reject,
                ToyotaServiceAdminAction::Cancel,
            ],
            self::AlternativeProposed => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::ProposeAlternative,
                ToyotaServiceAdminAction::Reject,
                ToyotaServiceAdminAction::Cancel,
            ],
            self::Confirmed => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::ProposeAlternative,
                ToyotaServiceAdminAction::CheckIn,
                ToyotaServiceAdminAction::MarkNoShow,
                ToyotaServiceAdminAction::Cancel,
            ],
            self::RescheduleRequested => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::ProposeAlternative,
                ToyotaServiceAdminAction::ConfirmReschedule,
                ToyotaServiceAdminAction::Cancel,
            ],
            self::CheckedIn => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::StartService,
            ],
            self::InService => [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::Complete,
            ],
            default => [],
        };

        return [...$actions, ToyotaServiceAdminAction::VerifyBenefit];
    }
}
