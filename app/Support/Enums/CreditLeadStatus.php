<?php

namespace App\Support\Enums;

enum CreditLeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Contacted => 'Sudah dihubungi',
            self::Converted => 'Konversi',
            self::Closed => 'Ditutup',
        };
    }
}
