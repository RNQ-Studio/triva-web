<?php

namespace App\Filament\Resources\BodyPaintPriceItems;

use App\Filament\Resources\BodyPaintPriceItems\Pages\ListBodyPaintPriceItems;
use App\Models\BodyPaintPriceItem;
use App\Support\BodyPaintCatalog;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BodyPaintPriceItemResource extends Resource
{
    protected static ?string $model = BodyPaintPriceItem::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup =
        'TRIVA Configuration';

    protected static ?string $navigationLabel = 'Price Matrix Body & Paint';

    protected static ?string $modelLabel = 'item price matrix Body & Paint';

    protected static ?string $pluralModelLabel =
        'item price matrix Body & Paint';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('bp_price_matrix.viewAny') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matrix_code')
                    ->label('Matrix')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')->label('Versi')->sortable(),
                TextColumn::make('serviceLocation.name')
                    ->label('Cabang')
                    ->placeholder('Semua cabang'),
                TextColumn::make('panel_code')
                    ->label('Panel')
                    ->formatStateUsing(
                        fn (string $state): string => BodyPaintCatalog::PANELS[$state] ?? $state,
                    ),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->formatStateUsing(
                        fn (BodyPaintSeverity $state): string => $state->label(),
                    ),
                TextColumn::make('work_type')
                    ->label('Pekerjaan')
                    ->formatStateUsing(
                        fn (BodyPaintWorkType $state): string => $state->label(),
                    ),
                TextColumn::make('total_low')
                    ->label('Rendah')
                    ->state(fn (BodyPaintPriceItem $record): int => $record->totalLow())
                    ->money('IDR'),
                TextColumn::make('total_high')
                    ->label('Tinggi')
                    ->state(fn (BodyPaintPriceItem $record): int => $record->totalHigh())
                    ->money('IDR'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('effective_from')
                    ->label('Mulai')
                    ->date(),
                TextColumn::make('effective_to')
                    ->label('Berakhir')
                    ->date()
                    ->placeholder('Tanpa batas'),
            ])
            ->filters([
                SelectFilter::make('matrix_code')
                    ->label('Matrix')
                    ->options(fn (): array => BodyPaintPriceItem::query()
                        ->distinct()
                        ->orderBy('matrix_code')
                        ->pluck('matrix_code', 'matrix_code')
                        ->all()),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ]),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (BodyPaintPriceItem $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->hidden(fn (BodyPaintPriceItem $record): bool => ! $record->is_active)
                    ->action(fn (BodyPaintPriceItem $record): bool => $record->update([
                        'is_active' => false,
                        'effective_to' => $record->effective_to ?? today(),
                    ])),
            ])
            ->defaultSort('approved_at', 'desc')
            ->striped();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['serviceLocation', 'vehicleMake', 'vehicleModel', 'approver']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBodyPaintPriceItems::route('/'),
        ];
    }
}
