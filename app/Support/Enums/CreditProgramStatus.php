<?php

namespace App\Support\Enums;

enum CreditProgramStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Disetujui',
            self::Inactive => 'Nonaktif',
        };
    }
}
