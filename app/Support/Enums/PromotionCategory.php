<?php

namespace App\Support\Enums;

/**
 * Empat kategori promo yang diminta notulensi 19 Agustus 2026.
 */
enum PromotionCategory: string
{
    case Sales = 'sales';
    case ServiceGr = 'service_gr';
    case ServiceBp = 'service_bp';
    case Otoxpert = 'otoxpert';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::ServiceGr => 'Service General Repair',
            self::ServiceBp => 'Service Body & Paint',
            self::Otoxpert => 'OtoXpert',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
