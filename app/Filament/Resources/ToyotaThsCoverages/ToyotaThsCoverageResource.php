<?php

namespace App\Filament\Resources\ToyotaThsCoverages;

use App\Filament\Resources\ToyotaThsCoverages\Pages\CreateToyotaThsCoverage;
use App\Filament\Resources\ToyotaThsCoverages\Pages\EditToyotaThsCoverage;
use App\Filament\Resources\ToyotaThsCoverages\Pages\ListToyotaThsCoverages;
use App\Models\ToyotaThsCoverage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ToyotaThsCoverageResource extends Resource
{
    protected static ?string $model = ToyotaThsCoverage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Toyota Service';

    protected static ?string $navigationLabel = 'Cakupan THS';

    protected static ?string $recordTitleAttribute = 'city';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Wilayah Toyota Home Service')
                ->columns(2)
                ->schema([
                    Select::make('service_location_id')
                        ->label('Lokasi layanan')
                        ->relationship('serviceLocation', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('city')->label('Kota')->required()->maxLength(100),
                    TextInput::make('latitude_min')
                        ->label('Latitude minimum')
                        ->numeric()
                        ->required(fn (Get $get): bool => (bool) $get('is_active'))
                        ->rules([
                            'nullable',
                            'required_with:latitude_max,longitude_min,longitude_max',
                            'between:-90,90',
                            'lte:latitude_max',
                        ]),
                    TextInput::make('latitude_max')
                        ->label('Latitude maksimum')
                        ->numeric()
                        ->required(fn (Get $get): bool => (bool) $get('is_active'))
                        ->rules([
                            'nullable',
                            'required_with:latitude_min,longitude_min,longitude_max',
                            'between:-90,90',
                            'gte:latitude_min',
                        ]),
                    TextInput::make('longitude_min')
                        ->label('Longitude minimum')
                        ->numeric()
                        ->required(fn (Get $get): bool => (bool) $get('is_active'))
                        ->rules([
                            'nullable',
                            'required_with:latitude_min,latitude_max,longitude_max',
                            'between:-180,180',
                            'lte:longitude_max',
                        ]),
                    TextInput::make('longitude_max')
                        ->label('Longitude maksimum')
                        ->numeric()
                        ->required(fn (Get $get): bool => (bool) $get('is_active'))
                        ->rules([
                            'nullable',
                            'required_with:latitude_min,latitude_max,longitude_min',
                            'between:-180,180',
                            'gte:longitude_min',
                        ]),
                    TextInput::make('verification_source')
                        ->label('Sumber verifikasi')
                        ->placeholder('Contoh: keputusan operasional 2026-08-01')
                        ->required()
                        ->maxLength(100),
                ]),
            Section::make('Masa berlaku')
                ->columns(3)
                ->schema([
                    Toggle::make('is_active')->label('Aktif')->default(false)->live(),
                    DatePicker::make('effective_from')
                        ->label('Berlaku mulai')
                        ->default(now('Asia/Jakarta')->toDateString())
                        ->required(),
                    DatePicker::make('effective_to')
                        ->label('Berlaku sampai')
                        ->afterOrEqual('effective_from'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('city')->label('Kota')->searchable()->sortable(),
                TextColumn::make('serviceLocation.name')->label('Lokasi')->searchable(),
                TextColumn::make('verification_source')->label('Sumber'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('effective_from')->label('Mulai')->date(),
                TextColumn::make('effective_to')->label('Sampai')->date()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('service_location_id')
                    ->label('Lokasi')
                    ->relationship('serviceLocation', 'name'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('city');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaThsCoverages::route('/'),
            'create' => CreateToyotaThsCoverage::route('/create'),
            'edit' => EditToyotaThsCoverage::route('/{record}/edit'),
        ];
    }
}
