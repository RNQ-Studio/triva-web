<?php

namespace App\Support;

final class BodyPaintCatalog
{
    /** @var array<string, string> */
    public const PANELS = [
        'front_bumper' => 'Bumper depan',
        'rear_bumper' => 'Bumper belakang',
        'hood' => 'Kap mesin',
        'trunk' => 'Bagasi',
        'left_fender' => 'Fender kiri',
        'right_fender' => 'Fender kanan',
        'front_left_door' => 'Pintu depan kiri',
        'front_right_door' => 'Pintu depan kanan',
        'rear_left_door' => 'Pintu belakang kiri',
        'rear_right_door' => 'Pintu belakang kanan',
        'left_quarter_panel' => 'Quarter panel kiri',
        'right_quarter_panel' => 'Quarter panel kanan',
        'roof' => 'Atap',
        'side_mirror' => 'Spion',
        'lamp' => 'Lampu',
        'glass' => 'Kaca',
        'other' => 'Lainnya',
    ];

    /** @var array<string, string> */
    public const DAMAGE_TYPES = [
        'scratch' => 'Baret',
        'dent' => 'Penyok',
        'paint_damage' => 'Cat terkelupas atau pudar',
        'crack' => 'Retak atau pecah',
        'corrosion' => 'Korosi',
        'missing_component' => 'Komponen hilang',
        'collision' => 'Bekas tabrakan',
        'other' => 'Lainnya',
    ];

    /** @var list<string> */
    public const HIGH_RISK_DAMAGE_TYPES = [
        'crack',
        'corrosion',
        'missing_component',
        'collision',
    ];

    /** @return list<string> */
    public static function panelCodes(): array
    {
        return array_keys(self::PANELS);
    }

    /** @return list<string> */
    public static function damageTypeCodes(): array
    {
        return array_keys(self::DAMAGE_TYPES);
    }
}
