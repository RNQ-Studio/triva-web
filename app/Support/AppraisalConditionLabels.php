<?php

namespace App\Support;

/**
 * Label bahasa Indonesia untuk jawaban kondisi kendaraan pada appraisal.
 *
 * Nilai lama (mis. `partial`, `more`, `unknown`) tetap punya label karena
 * appraisal yang sudah tersimpan dan pemasangan aplikasi lama masih
 * mengirimkannya; pilihan yang ditawarkan aplikasi terbaru hanyalah subset
 * yang diminta cabang pada revisi 4 September 2026.
 */
final class AppraisalConditionLabels
{
    /** @var array<string, string> */
    public const CONDITION_GRADE = [
        'a' => 'Istimewa, siap pakai',
        'b' => 'Perlu perbaikan ringan',
        'c' => 'Perlu perbaikan mesin dan transmisi',
        'd' => 'Perlu perbaikan berat dan rangka body kendaraan',
    ];

    /** @var array<string, string> */
    public const SERVICE_HISTORY = [
        'authorized' => 'Bengkel authorized',
        'general' => 'Bengkel umum',
        'complete' => 'Lengkap',
        'partial' => 'Sebagian',
        'none' => 'Tidak ada',
        'unknown' => 'Tidak tahu',
    ];

    /** @var array<string, string> */
    public const OWNERSHIP = [
        'first' => 'Tangan pertama',
        'second_or_more' => 'Tangan kedua atau lebih',
        'second' => 'Tangan kedua',
        'more' => 'Lebih dari dua',
        'unknown' => 'Tidak tahu',
    ];

    /** @var array<string, string> */
    public const TYRE_CONDITION = [
        'normal' => 'Normal',
        'damaged' => 'Aus',
    ];

    /** @var array<string, string> */
    public const ENGINE_CONDITION = [
        'normal' => 'Normal',
        'wet' => 'Basah / rembes',
    ];

    /** @var array<string, string> */
    public const TAX_STATUS = [
        'active' => 'Aktif',
        'overdue' => 'Menunggak',
        'unknown' => 'Tidak tahu',
    ];

    /** @var array<string, string> */
    public const YES_NO = [
        'yes' => 'Ya',
        'no' => 'Tidak',
        'unknown' => 'Tidak tahu',
    ];

    /** @param array<string, string> $map */
    public static function label(array $map, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $map[strtolower($value)] ?? $value;
    }
}
