<?php

namespace App\Services;

use App\Support\Enums\VisitSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VisitStatisticsService
{
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

        $counts = [
            'daily' => $this->emptySourceCounts(),
            'weekly' => $this->emptySourceCounts(),
            'monthly' => $this->emptySourceCounts(),
            'overall' => $this->emptySourceCounts(),
        ];

        $rows = DB::table('visit_events')
            ->select('source')
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
            ->groupBy('source')
            ->get();

        foreach ($rows as $row) {
            $source = (string) $row->source;
            if (! in_array($source, VisitSource::values(), true)) {
                continue;
            }

            $counts['daily'][$source] = (int) $row->daily_count;
            $counts['weekly'][$source] = (int) $row->weekly_count;
            $counts['monthly'][$source] = (int) $row->monthly_count;
            $counts['overall'][$source] = (int) $row->overall_count;
        }

        $firstRecordedAt = DB::table('visit_events')
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

    /** @return array{android: int, web: int, landing_page: int} */
    private function emptySourceCounts(): array
    {
        return [
            VisitSource::Android->value => 0,
            VisitSource::Web->value => 0,
            VisitSource::LandingPage->value => 0,
        ];
    }

    /**
     * @param  array{android: int, web: int, landing_page: int}  $bySource
     * @return array{starts_at: string|null, ends_at: string, total: int, by_source: array{android: int, web: int, landing_page: int}}
     */
    private function period(
        ?CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $bySource,
    ): array {
        return [
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'total' => array_sum($bySource),
            'by_source' => $bySource,
        ];
    }
}
