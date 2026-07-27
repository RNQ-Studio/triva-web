<?php

namespace App\Support\Enums;

enum OtoxpertBookingStatus: string
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
            self::AwaitingConfirmation => 'Menunggu konfirmasi bengkel',
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

    /** @return list<string> */
    public function customerActions(): array
    {
        return match ($this) {
            self::AwaitingConfirmation => ['cancel'],
            self::AlternativeProposed => [
                'accept_alternative',
                'reject_alternative',
                'cancel',
            ],
            self::Confirmed => ['reschedule', 'cancel'],
            self::RescheduleRequested => ['cancel'],
            default => [],
        };
    }

    /** @return list<OtoxpertAdminAction> */
    public function adminActions(): array
    {
        return match ($this) {
            self::AwaitingConfirmation => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::Confirm,
                OtoxpertAdminAction::ProposeAlternative,
                OtoxpertAdminAction::Reject,
                OtoxpertAdminAction::Cancel,
            ],
            self::AlternativeProposed => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::ProposeAlternative,
                OtoxpertAdminAction::Reject,
                OtoxpertAdminAction::Cancel,
            ],
            self::Confirmed => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::ProposeAlternative,
                OtoxpertAdminAction::CheckIn,
                OtoxpertAdminAction::MarkNoShow,
                OtoxpertAdminAction::Cancel,
            ],
            self::RescheduleRequested => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::ProposeAlternative,
                OtoxpertAdminAction::ConfirmReschedule,
                OtoxpertAdminAction::Cancel,
            ],
            self::CheckedIn => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::StartService,
            ],
            self::InService => [
                OtoxpertAdminAction::Assign,
                OtoxpertAdminAction::Complete,
            ],
            default => [],
        };
    }
}
