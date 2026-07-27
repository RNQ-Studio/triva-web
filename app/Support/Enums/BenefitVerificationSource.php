<?php

namespace App\Support\Enums;

enum BenefitVerificationSource: string
{
    case OfficialApi = 'official_api';
    case StaffManual = 'staff_manual';

    public function label(): string
    {
        return match ($this) {
            self::OfficialApi => 'API resmi',
            self::StaffManual => 'Verifikasi petugas',
        };
    }
}
