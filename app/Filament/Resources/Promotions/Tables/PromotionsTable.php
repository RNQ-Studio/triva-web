<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Support\Enums\PromotionCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->disk('public'),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(
                        fn (PromotionCategory $state): string => $state->label(),
                    ),
                TextColumn::make('starts_on')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                IconColumn::make('show_as_popup')->label('Pop-up')->boolean(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(PromotionCategory::options()),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}
