<?php

namespace App\Support\Enums;

enum AppraisalConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public static function fromComparableCount(int $count): self
    {
        return match (true) {
            $count >= 12 => self::High,
            $count >= 6 => self::Medium,
            default => self::Low,
        };
    }
}
