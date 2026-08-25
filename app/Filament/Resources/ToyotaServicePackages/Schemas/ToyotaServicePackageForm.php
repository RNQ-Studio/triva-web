<?php

namespace App\Filament\Resources\ToyotaServicePackages\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ToyotaServicePackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode paket')
                    ->required()
                    ->maxLength(40),
                TextInput::make('name')
                    ->label('Nama paket')
                    ->required()
                    ->maxLength(120),
                Textarea::make('description')
                    ->label('Penjelasan')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('vehicle_model')
                    ->label('Model kendaraan')
                    ->helperText('Kosongkan bila paket berlaku untuk semua model Toyota.')
                    ->maxLength(100),
                TextInput::make('km_interval')
                    ->label('Kelipatan kilometer')
                    ->numeric()
                    ->required()
                    ->minValue(1000),
                TextInput::make('parts_cost')
                    ->label('Biaya part')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                TextInput::make('labor_cost')
                    ->label('Biaya jasa')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Repeater::make('includes')
                    ->label('Cakupan pekerjaan')
                    ->simple(
                        TextInput::make('item')
                            ->required()
                            ->maxLength(120),
                    )
                    ->columnSpanFull(),
                TextInput::make('duration_min_minutes')
                    ->label('Durasi minimum (menit)')
                    ->numeric()
                    ->required()
                    ->default(60),
                TextInput::make('duration_max_minutes')
                    ->label('Durasi maksimum (menit)')
                    ->numeric()
                    ->required()
                    ->default(180),
                DatePicker::make('effective_from')
                    ->label('Berlaku dari')
                    ->required()
                    ->default(now()->startOfMonth()),
                DatePicker::make('effective_to')
                    ->label('Berlaku sampai')
                    ->helperText('Kosongkan bila belum ada batas akhir.'),
                TextInput::make('source_reference')
                    ->label('Rujukan sumber')
                    ->required()
                    ->helperText('Dokumen pricelist paket reguler cabang.')
                    ->maxLength(200)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
