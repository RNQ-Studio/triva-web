<?php

namespace App\Filament\Resources\Appraisals;

use App\Filament\Resources\Appraisals\Pages\ListAppraisals;
use App\Filament\Resources\Appraisals\Pages\ViewAppraisal;
use App\Filament\Resources\Appraisals\Schemas\AppraisalInfolist;
use App\Filament\Resources\Appraisals\Tables\AppraisalsTable;
use App\Models\Appraisal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AppraisalResource extends Resource
{
    protected static ?string $model = Appraisal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'TRIVA Operations';

    protected static ?string $navigationLabel = 'Appraisal';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function infolist(Schema $schema): Schema
    {
        return AppraisalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppraisalsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user',
                'vehicle',
                'latestResult',
                'latestMarketEstimate.comparables',
            ])
            ->withCount('currentPhotos');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppraisals::route('/'),
            'view' => ViewAppraisal::route('/{record}'),
        ];
    }
}
