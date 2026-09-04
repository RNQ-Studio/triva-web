<?php

namespace App\Filament\Resources\SalesContacts\Schemas;

use App\Support\Enums\SalesContactRole;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalesContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(120),
                Select::make('role')
                    ->label('Peran')
                    ->options(SalesContactRole::options())
                    ->required(),
                TextInput::make('whatsapp_number')
                    ->label('Nomor WhatsApp')
                    ->required()
                    ->tel()
                    ->maxLength(20)
                    ->regex('/^\+?[0-9][0-9 \-]{7,18}$/')
                    ->helperText('Contoh: 0812xxxxxxx atau 62812xxxxxxx.'),
                FileUpload::make('photo_path')
                    ->label('Foto')
                    ->image()
                    ->avatar()
                    ->disk('public')
                    ->directory('sales-contacts')
                    ->maxSize(3072)
                    ->helperText('Format JPG, PNG, atau WEBP. Maksimal 3 MB.'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
