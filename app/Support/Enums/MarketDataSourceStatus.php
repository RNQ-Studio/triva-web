<?php

namespace App\Support\Enums;

enum MarketDataSourceStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Disetujui',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
