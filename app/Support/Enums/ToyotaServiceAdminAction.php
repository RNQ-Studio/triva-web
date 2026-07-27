<?php

namespace App\Support\Enums;

enum ToyotaServiceAdminAction: string
{
    case Assign = 'assign';
    case Confirm = 'confirm';
    case ProposeAlternative = 'propose_alternative';
    case Reject = 'reject';
    case ConfirmReschedule = 'confirm_reschedule';
    case CheckIn = 'check_in';
    case StartService = 'start_service';
    case Complete = 'complete';
    case MarkNoShow = 'mark_no_show';
    case Cancel = 'cancel';
    case VerifyBenefit = 'verify_benefit';

    public function label(): string
    {
        return match ($this) {
            self::Assign => 'Tetapkan Service Advisor',
            self::Confirm => 'Konfirmasi booking',
            self::ProposeAlternative => 'Ajukan jadwal alternatif',
            self::Reject => 'Tolak permintaan',
            self::ConfirmReschedule => 'Konfirmasi jadwal ulang',
            self::CheckIn => 'Tandai check-in',
            self::StartService => 'Mulai servis',
            self::Complete => 'Selesaikan servis',
            self::MarkNoShow => 'Tandai tidak hadir',
            self::Cancel => 'Batalkan booking',
            self::VerifyBenefit => 'Verifikasi benefit',
        };
    }
}
