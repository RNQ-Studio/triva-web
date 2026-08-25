<?php

namespace App\Filament\Resources\ToyotaServicePackages\Tables;

use App\Models\ToyotaServicePackage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class ToyotaServicePackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Paket')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle_model')
                    ->label('Model')
                    ->placeholder('Semua model')
                    ->searchable(),
                TextColumn::make('km_interval')
                    ->label('Kelipatan')
                    ->formatStateUsing(fn (int $state): string => Number::format($state).' km')
                    ->sortable(),
                TextColumn::make('parts_cost')->label('Part')->money('IDR'),
                TextColumn::make('labor_cost')->label('Jasa')->money('IDR'),
                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (ToyotaServicePackage $record): int => $record->totalCost())
                    ->money('IDR'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('km_interval');
    }
}
