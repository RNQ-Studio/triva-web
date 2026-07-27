<?php

namespace App\Support\Enums;

enum BodyPaintWorkType: string
{
    case Inspect = 'inspect';
    case Repair = 'repair';
    case Replace = 'replace';
    case Paint = 'paint';
    case Polish = 'polish';

    public function label(): string
    {
        return match ($this) {
            self::Inspect => 'Inspeksi',
            self::Repair => 'Perbaikan',
            self::Replace => 'Penggantian',
            self::Paint => 'Pengecatan',
            self::Polish => 'Poles',
        };
    }
}
