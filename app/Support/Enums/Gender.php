<?php

namespace App\Support\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Undisclosed = 'undisclosed';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
            self::Undisclosed => 'Tidak disebutkan',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
