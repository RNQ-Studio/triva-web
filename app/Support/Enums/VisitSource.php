<?php

namespace App\Support\Enums;

enum VisitSource: string
{
    case Android = 'android';
    case Web = 'web';
    case LandingPage = 'landing_page';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function clientValues(): array
    {
        return [self::Android->value, self::Web->value];
    }
}
