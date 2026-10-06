<?php

namespace App\Filament\Resources\PartnerLogos\Schemas;

use App\Models\PartnerLogo;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PartnerLogoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama mitra')
                    ->required()
                    ->maxLength(100),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory(PartnerLogo::LOGO_DIRECTORY)
                    ->maxSize(5120)
                    ->helperText('PNG berlatar transparan dengan margin tipis paling rapi. Maksimal 5 MB.')
                    ->columnSpanFull(),
                TextInput::make('link_url')
                    ->label('Tautan saat diketuk')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Opsional. Dibuka di browser.'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
