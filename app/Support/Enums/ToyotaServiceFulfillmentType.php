<?php

namespace App\Support\Enums;

enum ToyotaServiceFulfillmentType: string
{
    case Workshop = 'workshop';
    case Ths = 'ths';

    public function label(): string
    {
        return match ($this) {
            self::Workshop => 'Workshop Auto2000',
            self::Ths => 'Toyota Home Service (THS)',
        };
    }
}
