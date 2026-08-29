<?php

namespace App\Filament\Widgets;

use App\Models\VisitEvent;
use App\Services\MenuUsageStatisticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** Menu yang paling sering dipilih pelanggan. */
class TopMenuUsageOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Menu yang sering dipilih';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', VisitEvent::class) ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $summary = app(MenuUsageStatisticsService::class)->summarize();
        $overall = $summary['periods']['overall'];
        $monthly = collect($summary['periods']['monthly']['menus'])
            ->keyBy('key');

        if ($overall['total'] === 0) {
            return [
                Stat::make('Belum ada data', '0')
                    ->description('Pemakaian menu belum tercatat')
                    ->descriptionIcon('heroicon-m-squares-2x2')
                    ->color('gray'),
            ];
        }

        $stats = [
            Stat::make('Total ketukan menu', Number::format((int) $overall['total']))
                ->description(Number::format(
                    (int) $summary['periods']['monthly']['total'],
                ).' bulan ini')
                ->descriptionIcon('heroicon-m-cursor-arrow-ripple')
                ->color('primary'),
        ];

        foreach (array_slice($overall['menus'], 0, 3) as $index => $menu) {
            $share = round(((float) $menu['share']) * 100, 1);
            $thisMonth = (int) ($monthly[$menu['key']]['total'] ?? 0);

            $stats[] = Stat::make(
                '#'.($index + 1).' '.$menu['label'],
                Number::format((int) $menu['total']),
            )
                ->description("{$share}% dari total · ".Number::format(
                    $thisMonth,
                ).' bulan ini')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color($index === 0 ? 'success' : 'info');
        }

        return $stats;
    }
}
