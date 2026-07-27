<?php

namespace App\Support\Enums;

enum ToyotaServiceContactChannel: string
{
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Telepon',
            self::Email => 'Email',
        };
    }
}
