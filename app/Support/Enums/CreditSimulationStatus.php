<?php

namespace App\Support\Enums;

enum CreditSimulationStatus: string
{
    case Saved = 'saved';
    case LeadCreated = 'lead_created';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Saved => 'Simulasi tersimpan',
            self::LeadCreated => 'Menunggu follow-up sales',
            self::Expired => 'Program telah berakhir',
        };
    }
}
