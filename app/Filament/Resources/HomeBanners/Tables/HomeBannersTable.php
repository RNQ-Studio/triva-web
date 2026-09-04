<?php

namespace App\Filament\Resources\HomeBanners\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HomeBannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->disk('public')
                    ->width(120)
                    ->height(60),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('link_url')
                    ->label('Tautan')
                    ->limit(30)
                    ->placeholder('Tanpa tautan'),
                TextColumn::make('starts_on')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->placeholder('Langsung'),
                TextColumn::make('ends_on')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}
