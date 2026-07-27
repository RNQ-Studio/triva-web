<?php

namespace App\Filament\Resources\ToyotaServiceHolidays;

use App\Filament\Resources\ToyotaServiceHolidays\Pages\CreateToyotaServiceHoliday;
use App\Filament\Resources\ToyotaServiceHolidays\Pages\EditToyotaServiceHoliday;
use App\Filament\Resources\ToyotaServiceHolidays\Pages\ListToyotaServiceHolidays;
use App\Models\ToyotaServiceHoliday;
use App\Rules\ValidToyotaServiceWindows;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class ToyotaServiceHolidayResource extends Resource
{
    protected static ?string $model = ToyotaServiceHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Toyota Service';

    protected static ?string $navigationLabel = 'Hari Libur';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kalender operasional')
                ->columns(2)
                ->schema([
                    Select::make('service_location_id')
                        ->label('Lokasi')
                        ->relationship('serviceLocation', 'name')
                        ->helperText('Kosongkan untuk berlaku pada semua lokasi.')
                        ->searchable()
                        ->preload(),
                    DatePicker::make('holiday_date')
                        ->label('Tanggal')
                        ->required(),
                    TextInput::make('name')
                        ->label('Nama libur / pengecualian')
                        ->required()
                        ->maxLength(160),
                    Toggle::make('is_closed')
                        ->label('Tutup penuh')
                        ->default(true)
                        ->live(),
                    TagsInput::make('time_windows')
                        ->label('Jam pengganti')
                        ->helperText('Isi HH:mm-HH:mm bila tidak tutup penuh.')
                        ->required(fn (Get $get): bool => ! (bool) $get('is_closed'))
                        ->rules([new ValidToyotaServiceWindows])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('holiday_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('serviceLocation.name')
                    ->label('Lokasi')
                    ->placeholder('Semua lokasi'),
                IconColumn::make('is_closed')->label('Tutup')->boolean(),
                TextColumn::make('time_windows')
                    ->label('Jam pengganti')
                    ->formatStateUsing(fn (?array $state): string => $state === null
                        ? '-'
                        : implode(', ', $state)),
            ])
            ->filters([
                SelectFilter::make('service_location_id')
                    ->label('Lokasi')
                    ->relationship('serviceLocation', 'name'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('holiday_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaServiceHolidays::route('/'),
            'create' => CreateToyotaServiceHoliday::route('/create'),
            'edit' => EditToyotaServiceHoliday::route('/{record}/edit'),
        ];
    }
}
