<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ToyotaServiceWindowRules
{
    public static function error(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }
        if (! is_array($value)) {
            return 'Jam operasional harus berupa daftar rentang waktu.';
        }

        $intervals = [];
        foreach ($value as $window) {
            if (
                ! is_string($window)
                || preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $window,
                ) !== 1
            ) {
                return 'Gunakan format waktu HH:mm-HH:mm yang valid.';
            }

            [$start, $end] = explode('-', $window, 2);
            $startMinute = self::minutes($start);
            $endMinute = self::minutes($end);
            if ($startMinute >= $endMinute) {
                return 'Waktu mulai harus lebih awal dari waktu selesai.';
            }
            $intervals[] = [$startMinute, $endMinute];
        }

        usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $previousEnd = null;
        foreach ($intervals as [$start, $end]) {
            if ($previousEnd !== null && $start < $previousEnd) {
                return 'Rentang waktu tidak boleh saling tumpang tindih.';
            }
            $previousEnd = $end;
        }

        return null;
    }

    public static function assertValid(mixed $value, string $attribute): void
    {
        $error = self::error($value);
        if ($error !== null) {
            throw ValidationException::withMessages([$attribute => [$error]]);
        }
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time, 2));

        return ($hour * 60) + $minute;
    }
}
