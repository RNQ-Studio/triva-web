<?php

namespace App\Filament\Resources\ToyotaServiceLocations;

use App\Filament\Resources\ToyotaServiceLocations\Pages\CreateToyotaServiceLocation;
use App\Filament\Resources\ToyotaServiceLocations\Pages\EditToyotaServiceLocation;
use App\Filament\Resources\ToyotaServiceLocations\Pages\ListToyotaServiceLocations;
use App\Models\ToyotaServiceLocation;
use App\Rules\ValidToyotaServiceWindows;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ToyotaServiceLocationResource extends Resource
{
    protected static ?string $model = ToyotaServiceLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Toyota Service';

    protected static ?string $navigationLabel = 'Lokasi & Jam';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas lokasi')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(60),
                    TextInput::make('name')->label('Nama')->required()->maxLength(160),
                    Textarea::make('address')->label('Alamat resmi')->columnSpanFull(),
                    TextInput::make('city')->label('Kota')->required()->maxLength(100),
                    TextInput::make('phone')->label('Telepon resmi')->tel()->maxLength(40),
                    TextInput::make('directions_url')
                        ->label('URL petunjuk arah')
                        ->url()
                        ->columnSpanFull(),
                    TextInput::make('timezone')
                        ->label('Timezone IANA')
                        ->default('Asia/Jakarta')
                        ->required()
                        ->rules(['timezone:all']),
                    TextInput::make('provenance_url')
                        ->label('Sumber data resmi')
                        ->url(),
                    DateTimePicker::make('verified_at')
                        ->label('Terakhir diverifikasi')
                        ->seconds(false),
                ]),
            Section::make('Koordinat dan layanan')
                ->columns(2)
                ->schema([
                    TextInput::make('latitude')
                        ->numeric()
                        ->rules(['nullable', 'required_with:longitude', 'between:-90,90']),
                    TextInput::make('longitude')
                        ->numeric()
                        ->rules(['nullable', 'required_with:latitude', 'between:-180,180']),
                    Toggle::make('supports_workshop')
                        ->label('Melayani workshop')
                        ->default(true),
                    Toggle::make('supports_ths')
                        ->label('Melayani THS')
                        ->default(false),
                    TextInput::make('confirmation_sla_minutes')
                        ->label('SLA konfirmasi (menit)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(120),
                    TextInput::make('cancellation_cutoff_hours')
                        ->label('Cutoff pembatalan (jam)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(4),
                ]),
            Section::make('Jam operasional')
                ->description('Isi setiap rentang sebagai HH:mm-HH:mm. Kosong berarti tutup.')
                ->columns(2)
                ->schema([
                    TagsInput::make('operating_hours.1')->label('Senin')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.2')->label('Selasa')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.3')->label('Rabu')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.4')->label('Kamis')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.5')->label('Jumat')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.6')->label('Sabtu')
                        ->rules([new ValidToyotaServiceWindows]),
                    TagsInput::make('operating_hours.7')->label('Minggu')
                        ->rules([new ValidToyotaServiceWindows]),
                ]),
            Section::make('Masa berlaku')
                ->columns(3)
                ->schema([
                    Toggle::make('is_active')->label('Aktif')->default(true),
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
                TextColumn::make('name')->label('Lokasi')->searchable()->sortable(),
                TextColumn::make('city')->label('Kota')->searchable(),
                TextColumn::make('phone')->label('Telepon'),
                IconColumn::make('supports_workshop')->label('Workshop')->boolean(),
                IconColumn::make('supports_ths')->label('THS')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('effective_from')->label('Berlaku')->date(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaServiceLocations::route('/'),
            'create' => CreateToyotaServiceLocation::route('/create'),
            'edit' => EditToyotaServiceLocation::route('/{record}/edit'),
        ];
    }
}
