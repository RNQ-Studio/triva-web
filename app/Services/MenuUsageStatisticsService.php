<?php

namespace App\Services;

use App\Support\Enums\MenuKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MenuUsageStatisticsService
{
    /** Jumlah menu teratas yang dilaporkan per periode. */
    private const TOP_LIMIT = 10;

    /** @return array<string, mixed> */
    public function summarize(): array
    {
        $timezone = (string) config('analytics.reporting_timezone', 'Asia/Jakarta');
        $generatedAt = CarbonImmutable::now('UTC');
        $localGeneratedAt = $generatedAt->setTimezone($timezone);

        $starts = [
            'daily' => $localGeneratedAt->startOfDay()->setTimezone('UTC'),
            'weekly' => $localGeneratedAt
                ->startOfWeek(CarbonInterface::MONDAY)
                ->setTimezone('UTC'),
            'monthly' => $localGeneratedAt->startOfMonth()->setTimezone('UTC'),
        ];

        $rows = DB::table('menu_usage_events')
            ->select('menu_key')
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS daily_count',
                [$starts['daily']],
            )
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS weekly_count',
                [$starts['weekly']],
            )
            ->selectRaw(
                'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) AS monthly_count',
                [$starts['monthly']],
            )
            ->selectRaw('COUNT(*) AS overall_count')
            ->where('occurred_at', '<=', $generatedAt)
            ->groupBy('menu_key')
            ->get();

        $counts = ['daily' => [], 'weekly' => [], 'monthly' => [], 'overall' => []];
        foreach ($rows as $row) {
            $key = (string) $row->menu_key;
            $counts['daily'][$key] = (int) $row->daily_count;
            $counts['weekly'][$key] = (int) $row->weekly_count;
            $counts['monthly'][$key] = (int) $row->monthly_count;
            $counts['overall'][$key] = (int) $row->overall_count;
        }

        $firstRecordedAt = DB::table('menu_usage_events')
            ->where('occurred_at', '<=', $generatedAt)
            ->min('occurred_at');
        $trackingStartedAt = $firstRecordedAt === null
            ? null
            : CarbonImmutable::parse((string) $firstRecordedAt)->setTimezone('UTC');

        return [
            'timezone' => $timezone,
            'tracking_started_at' => $trackingStartedAt?->toIso8601String(),
            'generated_at' => $generatedAt->toIso8601String(),
            'periods' => [
                'daily' => $this->period($starts['daily'], $generatedAt, $counts['daily']),
                'weekly' => $this->period($starts['weekly'], $generatedAt, $counts['weekly']),
                'monthly' => $this->period($starts['monthly'], $generatedAt, $counts['monthly']),
                'overall' => $this->period($trackingStartedAt, $generatedAt, $counts['overall']),
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function period(
        ?CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $counts,
    ): array {
        $counts = array_filter($counts, static fn (int $total): bool => $total > 0);
        $total = array_sum($counts);

        // Urut menurun, lalu alfabetis supaya urutan stabil saat jumlahnya sama.
        uksort($counts, static function (string $left, string $right) use ($counts): int {
            return [$counts[$right], $left] <=> [$counts[$left], $right];
        });

        $menus = [];
        foreach (array_slice($counts, 0, self::TOP_LIMIT, true) as $key => $count) {
            $menus[] = [
                'key' => $key,
                'label' => MenuKey::labelFor($key),
                'total' => $count,
                'share' => $total > 0 ? round($count / $total, 4) : 0.0,
            ];
        }

        return [
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'total' => $total,
            'distinct_menus' => count($counts),
            'menus' => $menus,
        ];
    }
}
