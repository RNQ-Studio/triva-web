<?php

namespace App\Filament\Resources\ToyotaSscCampaigns\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ToyotaSscCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('vehicle_model')
                    ->label('Model')
                    ->placeholder('Semua model')
                    ->searchable(),
                TextColumn::make('year_from')
                    ->label('Tahun')
                    ->formatStateUsing(
                        fn ($state, $record): string => trim(
                            ($state ?? '-').' - '.($record->year_to ?? '-'),
                        ),
                    ),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('effective_from')
                    ->label('Berlaku dari')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
