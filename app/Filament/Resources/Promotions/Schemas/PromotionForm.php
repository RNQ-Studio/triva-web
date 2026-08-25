<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Support\Enums\PromotionCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category')
                    ->label('Kategori')
                    ->options(PromotionCategory::options())
                    ->required(),
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(150),
                TextInput::make('subtitle')
                    ->label('Subjudul')
                    ->maxLength(200),
                Textarea::make('description')
                    ->label('Penjelasan')
                    ->rows(4)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Gambar promo')
                    ->image()
                    ->disk('public')
                    ->directory('promotions')
                    ->maxSize(5120)
                    ->helperText('Format JPG, PNG, atau WEBP. Maksimal 5 MB.')
                    ->columnSpanFull(),
                TextInput::make('cta_label')
                    ->label('Teks tombol')
                    ->maxLength(60),
                TextInput::make('cta_url')
                    ->label('Tautan tombol')
                    ->url()
                    ->maxLength(500),
                DatePicker::make('starts_on')
                    ->label('Mulai tayang')
                    ->required()
                    ->default(now()->startOfMonth()),
                DatePicker::make('ends_on')
                    ->label('Berakhir')
                    ->helperText('Kosongkan bila promo berjalan tanpa batas.')
                    ->default(now()->endOfMonth()),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Toggle::make('show_as_popup')
                    ->label('Tampilkan sebagai pop-up')
                    ->helperText('Pakai hanya untuk promo unggulan bulan ini.'),
            ]);
    }
}
