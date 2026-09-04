<?php

namespace App\Filament\Resources\HomeBanners\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HomeBannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(150)
                    ->helperText('Dipakai sebagai keterangan gambar; tidak ditampilkan besar di aplikasi.'),
                FileUpload::make('image_path')
                    ->label('Gambar banner')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory('home-banners')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('2:1')
                    ->maxSize(5120)
                    ->helperText('Rasio 2:1 (mis. 1600x800 px). Format JPG, PNG, atau WEBP, maksimal 5 MB.')
                    ->columnSpanFull(),
                TextInput::make('link_url')
                    ->label('Tautan saat diketuk')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Opsional. Kosongkan bila banner hanya informasi.'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->default(0),
                DatePicker::make('starts_on')
                    ->label('Mulai tayang')
                    ->helperText('Kosongkan bila langsung tayang.'),
                DatePicker::make('ends_on')
                    ->label('Berakhir')
                    ->helperText('Kosongkan bila tayang tanpa batas.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
