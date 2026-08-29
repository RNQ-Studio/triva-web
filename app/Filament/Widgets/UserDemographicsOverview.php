<?php

namespace App\Filament\Widgets;

use App\Models\VisitEvent;
use App\Services\UserDemographicsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** Gender dan rentang usia pengguna terdaftar. */
class UserDemographicsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Gender & usia pengguna';

    protected static ?int $sort = -20;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', VisitEvent::class) ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $summary = app(UserDemographicsService::class)->summarize();
        $total = (int) $summary['total_users'];

        $genderLine = collect($summary['gender'])
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->map(fn (array $row): string => $row['label'].' '.Number::format(
                (int) $row['total'],
            ))
            ->implode(' · ');

        $topAge = collect($summary['age_groups'])
            ->reject(fn (array $row): bool => $row['key'] === 'unknown')
            ->sortByDesc('total')
            ->first();

        $ageLine = collect($summary['age_groups'])
            ->reject(fn (array $row): bool => $row['key'] === 'unknown')
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->map(fn (array $row): string => $row['label'].' '.Number::format(
                (int) $row['total'],
            ))
            ->implode(' · ');

        $completionRate = round(((float) $summary['completion_rate']) * 100, 1);

        return [
            Stat::make('Pengguna terdaftar', Number::format($total))
                ->description(Number::format(
                    (int) $summary['completed_profiles'],
                )." lengkap ({$completionRate}%)")
                ->descriptionIcon('heroicon-m-identification')
                ->color('primary'),
            Stat::make('Gender', $genderLine === '' ? 'Belum ada data' : $genderLine)
                ->description('Sebaran gender pengguna')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make(
                'Usia terbanyak',
                $topAge === null || $topAge['total'] === 0
                    ? 'Belum ada data'
                    : $topAge['label'],
            )
                ->description($ageLine === '' ? 'Belum ada data usia' : $ageLine)
                ->descriptionIcon('heroicon-m-cake')
                ->color('warning'),
        ];
    }
}
