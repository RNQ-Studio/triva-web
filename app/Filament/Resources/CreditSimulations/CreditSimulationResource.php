<?php

namespace App\Filament\Resources\CreditSimulations;

use App\Filament\Resources\CreditSimulations\Pages\ListCreditSimulations;
use App\Filament\Resources\CreditSimulations\Pages\ViewCreditSimulation;
use App\Filament\Resources\CreditSimulations\Schemas\CreditSimulationInfolist;
use App\Filament\Resources\CreditSimulations\Tables\CreditSimulationsTable;
use App\Models\CreditSimulation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CreditSimulationResource extends Resource
{
    protected static ?string $model = CreditSimulation::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup =
        'Simulasi Kredit';

    protected static ?string $navigationLabel = 'Simulasi Tersimpan';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('credit_leads.viewAny') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CreditSimulationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditSimulationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user',
            'program',
            'appraisal',
            'followUpLead.assignedSales',
        ]);
        $user = auth()->user();
        if ($user !== null
            && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $query->whereHas(
                'followUpLead',
                fn (Builder $lead): Builder => $lead->where(
                    'assigned_sales_id',
                    $user->getKey(),
                ),
            );
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditSimulations::route('/'),
            'view' => ViewCreditSimulation::route('/{record}'),
        ];
    }
}
