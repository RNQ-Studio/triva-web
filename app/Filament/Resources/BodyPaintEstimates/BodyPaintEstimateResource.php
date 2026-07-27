<?php

namespace App\Filament\Resources\BodyPaintEstimates;

use App\Filament\Resources\BodyPaintEstimates\Pages\ListBodyPaintEstimates;
use App\Filament\Resources\BodyPaintEstimates\Pages\ViewBodyPaintEstimate;
use App\Filament\Resources\BodyPaintEstimates\Schemas\BodyPaintEstimateInfolist;
use App\Filament\Resources\BodyPaintEstimates\Tables\BodyPaintEstimatesTable;
use App\Models\BodyPaintEstimate;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BodyPaintEstimateResource extends Resource
{
    protected static ?string $model = BodyPaintEstimate::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'TRIVA Operations';

    protected static ?string $navigationLabel = 'Estimasi Body & Paint';

    protected static ?string $modelLabel = 'estimasi Body & Paint';

    protected static ?string $pluralModelLabel = 'estimasi Body & Paint';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('bp_estimates.viewAny') ?? false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return BodyPaintEstimateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BodyPaintEstimatesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user->hasAnyRole(['super-admin', 'admin'])) {
            $query->where('assigned_estimator_id', $user->getKey());
        }

        return $query->with([
            'user',
            'vehicle',
            'serviceLocation',
            'assignedEstimator',
            'damages',
            'photos.asset',
            'items',
            'versions.publisher',
            'statusHistories.changedBy',
            'booking',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBodyPaintEstimates::route('/'),
            'view' => ViewBodyPaintEstimate::route('/{record}'),
        ];
    }
}
