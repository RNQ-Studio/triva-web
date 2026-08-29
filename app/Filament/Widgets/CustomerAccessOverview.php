<?php

namespace App\Filament\Widgets;

use App\Models\VisitEvent;
use App\Services\VisitStatisticsService;
use App\Support\Enums\VisitSource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Berapa banyak pelanggan yang mengakses TRIVA, dipecah per kanal:
 * aplikasi Android (Play Store), aplikasi web, dan landing page.
 */
class CustomerAccessOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Akses pelanggan';

    protected static ?int $sort = -30;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', VisitEvent::class) ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $summary = app(VisitStatisticsService::class)->summarize();
        $overall = $summary['periods']['overall']['by_source'];
        $monthly = $summary['periods']['monthly']['by_source'];

        $labels = [
            VisitSource::Android->value => ['Aplikasi Android', 'heroicon-m-device-phone-mobile', 'success'],
            VisitSource::Web->value => ['Aplikasi Web', 'heroicon-m-globe-alt', 'info'],
            VisitSource::LandingPage->value => ['Landing Page', 'heroicon-m-cursor-arrow-rays', 'warning'],
        ];

        $stats = [
            Stat::make(
                'Total akses',
                Number::format((int) $summary['periods']['overall']['total']),
            )
                ->description(Number::format(
                    (int) $summary['periods']['monthly']['total'],
                ).' bulan ini')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];

        foreach ($labels as $source => [$label, $icon, $color]) {
            $stats[] = Stat::make($label, Number::format((int) $overall[$source]))
                ->description(Number::format((int) $monthly[$source]).' bulan ini')
                ->descriptionIcon($icon)
                ->color($color);
        }

        return $stats;
    }
}
