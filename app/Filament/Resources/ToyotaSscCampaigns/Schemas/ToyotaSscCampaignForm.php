<?php

namespace App\Filament\Resources\ToyotaSscCampaigns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ToyotaSscCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('campaign_code')
                    ->label('Kode kampanye')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(40),
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(200),
                Textarea::make('description')
                    ->label('Penjelasan untuk pelanggan')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('vehicle_model')
                    ->label('Model kendaraan')
                    ->helperText('Kosongkan hanya bila daftar awalan nomor rangka diisi.')
                    ->maxLength(100),
                TextInput::make('year_from')
                    ->label('Tahun mulai')
                    ->numeric()
                    ->minValue(1980),
                TextInput::make('year_to')
                    ->label('Tahun sampai')
                    ->numeric()
                    ->minValue(1980),
                Repeater::make('vin_prefixes')
                    ->label('Awalan nomor rangka')
                    ->helperText('Kosongkan bila seluruh unit model dan tahun di atas tercakup.')
                    ->simple(
                        TextInput::make('prefix')
                            ->required()
                            ->maxLength(20),
                    )
                    ->columnSpanFull(),
                TextInput::make('recommended_action')
                    ->label('Tindakan yang disarankan')
                    ->maxLength(200)
                    ->columnSpanFull(),
                DatePicker::make('effective_from')
                    ->label('Berlaku dari')
                    ->required(),
                DatePicker::make('effective_to')
                    ->label('Berlaku sampai')
                    ->helperText('Kosongkan bila belum ada batas akhir.'),
                TextInput::make('source_reference')
                    ->label('Rujukan sumber')
                    ->required()
                    ->helperText('Nomor surat edaran TAM atau dokumen resmi lainnya.')
                    ->maxLength(200)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
