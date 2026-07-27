<?php

namespace App\Support\Enums;

enum VehicleBenefitStatus: string
{
    case Unknown = 'unknown';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Unknown, self::PendingVerification => 'Perlu diverifikasi petugas',
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
        };
    }

    public function requiresVerificationEvidence(): bool
    {
        return in_array($this, [self::Active, self::Inactive], true);
    }
}
