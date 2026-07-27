<?php

namespace App\Filament\Resources\CreditSimulations\Widgets;

use App\Filament\Resources\CreditSimulations\CreditSimulationResource;
use App\Support\Enums\CreditLeadStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class CreditSimulationStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $query = CreditSimulationResource::getEloquentQuery();
        $total = (clone $query)->count();
        $followUps = (clone $query)->whereHas('followUpLead')->count();
        $converted = (clone $query)->whereHas(
            'followUpLead',
            fn ($lead) => $lead->where(
                'status',
                CreditLeadStatus::Converted,
            ),
        )->count();
        $conversion = $followUps === 0
            ? 0
            : round(($converted / $followUps) * 100, 1);

        return [
            Stat::make('Simulasi tersimpan', Number::format($total))
                ->description('Dalam scope akses saat ini')
                ->color('info'),
            Stat::make('Meminta follow-up', Number::format($followUps))
                ->description('Lead unik per simulasi')
                ->color('warning'),
            Stat::make('Konversi lead', "{$conversion}%")
                ->description(Number::format($converted).' lead converted')
                ->color('success'),
        ];
    }
}
