<?php

namespace App\Support\Enums;

enum BodyPaintSeverity: string
{
    case Light = 'light';
    case Medium = 'medium';
    case Heavy = 'heavy';
    case Unsure = 'unsure';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Ringan',
            self::Medium => 'Sedang',
            self::Heavy => 'Berat',
            self::Unsure => 'Tidak yakin',
        };
    }

    public function isHighRisk(): bool
    {
        return $this === self::Heavy;
    }
}
