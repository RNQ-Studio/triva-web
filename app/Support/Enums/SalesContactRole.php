<?php

namespace App\Support\Enums;

enum SalesContactRole: string
{
    case Sales = 'sales';
    case Spv = 'spv';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Spv => 'Supervisor (SPV)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
