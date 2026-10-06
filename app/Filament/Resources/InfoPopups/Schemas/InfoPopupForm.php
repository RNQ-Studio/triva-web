<?php

namespace App\Filament\Resources\InfoPopups\Schemas;

use App\Models\InfoPopup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InfoPopupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(150)
                    ->helperText('Keterangan gambar untuk admin dan pembaca layar; tidak ditampilkan besar di aplikasi.'),
                FileUpload::make('image_path')
                    ->label('Gambar popup')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory(InfoPopup::IMAGE_DIRECTORY)
                    ->maxSize(5120)
                    ->helperText('Disarankan potret 4:5 (mis. 1080x1350 px). Format JPG, PNG, atau WEBP, maksimal 5 MB.')
                    ->columnSpanFull(),
                TextInput::make('button_label')
                    ->label('Label tombol')
                    ->maxLength(40)
                    ->requiredWith('button_url')
                    ->helperText('Opsional, mis. "Lihat promo". Kosongkan bila tanpa tombol.'),
                TextInput::make('button_url')
                    ->label('Tautan tombol')
                    ->url()
                    ->maxLength(500)
                    ->requiredWith('button_label')
                    ->helperText('Dibuka di browser saat tombol diketuk.'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->default(0),
                TextInput::make('interval_hours')
                    ->label('Tampil setiap (jam)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(720)
                    ->default(24)
                    ->helperText('Popup muncul lagi di perangkat yang sama setelah sekian jam.'),
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
