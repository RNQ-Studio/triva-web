<?php

namespace App\Services;

use App\Support\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UserDemographicsService
{
    /**
     * Rentang usia yang dilaporkan ke dashboard admin.
     *
     * @var list<array{key: string, label: string, min: int, max: int|null}>
     */
    private const AGE_GROUPS = [
        ['key' => 'under_25', 'label' => 'Di bawah 25 tahun', 'min' => 0, 'max' => 24],
        ['key' => '25_34', 'label' => '25–34 tahun', 'min' => 25, 'max' => 34],
        ['key' => '35_44', 'label' => '35–44 tahun', 'min' => 35, 'max' => 44],
        ['key' => '45_54', 'label' => '45–54 tahun', 'min' => 45, 'max' => 54],
        ['key' => '55_plus', 'label' => '55 tahun ke atas', 'min' => 55, 'max' => null],
    ];

    /** @return array<string, mixed> */
    public function summarize(): array
    {
        $generatedAt = CarbonImmutable::now('UTC');

        $totalUsers = (int) DB::table('users')->count();

        $genderRows = DB::table('users')
            ->select('gender')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('gender')
            ->get();

        $genderCounts = [];
        foreach (Gender::cases() as $case) {
            $genderCounts[$case->value] = 0;
        }
        $genderUnknown = 0;

        foreach ($genderRows as $row) {
            $value = $row->gender === null ? null : (string) $row->gender;
            if ($value !== null && array_key_exists($value, $genderCounts)) {
                $genderCounts[$value] = (int) $row->total;

                continue;
            }
            $genderUnknown += (int) $row->total;
        }

        $ageCounts = array_fill_keys(
            array_column(self::AGE_GROUPS, 'key'),
            0,
        );
        $ageUnknown = 0;

        $birthDates = DB::table('users')
            ->whereNotNull('birth_date')
            ->pluck('birth_date');

        foreach ($birthDates as $birthDate) {
            $age = (int) CarbonImmutable::parse((string) $birthDate)
                ->diffInYears($generatedAt);
            $key = $this->ageGroupKey($age);
            if ($key === null) {
                $ageUnknown++;

                continue;
            }
            $ageCounts[$key]++;
        }
        $ageUnknown += $totalUsers - $birthDates->count();

        $completed = (int) DB::table('users')
            ->whereNotNull('gender')
            ->whereNotNull('birth_date')
            ->count();

        $genderBreakdown = [];
        foreach ($genderCounts as $value => $total) {
            $genderBreakdown[] = [
                'key' => $value,
                'label' => Gender::from($value)->label(),
                'total' => $total,
                'share' => $this->share($total, $totalUsers),
            ];
        }
        $genderBreakdown[] = [
            'key' => 'unknown',
            'label' => 'Belum diisi',
            'total' => $genderUnknown,
            'share' => $this->share($genderUnknown, $totalUsers),
        ];

        $ageBreakdown = [];
        foreach (self::AGE_GROUPS as $group) {
            $ageBreakdown[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'total' => $ageCounts[$group['key']],
                'share' => $this->share($ageCounts[$group['key']], $totalUsers),
            ];
        }
        $ageBreakdown[] = [
            'key' => 'unknown',
            'label' => 'Belum diisi',
            'total' => $ageUnknown,
            'share' => $this->share($ageUnknown, $totalUsers),
        ];

        return [
            'generated_at' => $generatedAt->toIso8601String(),
            'total_users' => $totalUsers,
            'completed_profiles' => $completed,
            'completion_rate' => $this->share($completed, $totalUsers),
            'gender' => $genderBreakdown,
            'age_groups' => $ageBreakdown,
        ];
    }

    private function ageGroupKey(int $age): ?string
    {
        if ($age < 0) {
            return null;
        }

        foreach (self::AGE_GROUPS as $group) {
            if ($age >= $group['min'] && ($group['max'] === null || $age <= $group['max'])) {
                return $group['key'];
            }
        }

        return null;
    }

    private function share(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($count / $total, 4);
    }
}
