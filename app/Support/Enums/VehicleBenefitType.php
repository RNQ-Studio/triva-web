<?php

namespace App\Support\Enums;

enum VehicleBenefitType: string
{
    case TCare = 't_care';
    case Ssc = 'ssc';
    case Warranty = 'warranty';

    public function label(): string
    {
        return match ($this) {
            self::TCare => 'T-Care',
            self::Ssc => 'SSC',
            self::Warranty => 'Warranty',
        };
    }
}
