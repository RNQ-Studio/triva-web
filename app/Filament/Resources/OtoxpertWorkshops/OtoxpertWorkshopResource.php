<?php

namespace App\Filament\Resources\OtoxpertWorkshops;

use App\Filament\Resources\OtoxpertWorkshops\Pages\CreateOtoxpertWorkshop;
use App\Filament\Resources\OtoxpertWorkshops\Pages\EditOtoxpertWorkshop;
use App\Filament\Resources\OtoxpertWorkshops\Pages\ListOtoxpertWorkshops;
use App\Models\OtoxpertWorkshop;
use App\Rules\ValidToyotaServiceWindows;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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

class OtoxpertWorkshopResource extends Resource
{
    protected static ?string $model = OtoxpertWorkshop::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'OtoXpert';

    protected static ?string $navigationLabel = 'Workshop';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('otoxpert_service_config.viewAny') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('otoxpert_service_config.create') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas workshop')->columns(2)->schema([
                TextInput::make('code')->required()->unique(ignoreRecord: true),
                TextInput::make('partner_code')->unique(ignoreRecord: true),
                TextInput::make('name')->required(),
                TextInput::make('phone')->tel(),
                Textarea::make('address')->required()->columnSpanFull(),
                TextInput::make('province')->required(),
                TextInput::make('city')->required(),
                TextInput::make('timezone')
                    ->default('Asia/Jakarta')
                    ->rules(['timezone:all'])
                    ->required(),
                TextInput::make('provenance_url')
                    ->label('Sumber verifikasi')
                    ->url()
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('verified_at')->required(),
            ]),
            Section::make('Kapabilitas')->columns(2)->schema([
                Toggle::make('supports_all_vehicle_makes')
                    ->label('Semua merek')
                    ->default(false),
                Toggle::make('supports_pickup_delivery')
                    ->label('Pickup/delivery')
                    ->default(false),
                Select::make('vehicleMakes')
                    ->relationship('vehicleMakes', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('services')
                    ->relationship('services', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                TextInput::make('confirmation_sla_minutes')
                    ->numeric()
                    ->minValue(1)
                    ->default(30)
                    ->required(),
                TextInput::make('cancellation_cutoff_hours')
                    ->numeric()
                    ->minValue(0)
                    ->default(4)
                    ->required(),
            ]),
            Section::make('Jam operasional')
                ->description('Format HH:mm-HH:mm. Kosong berarti tutup.')
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
            Section::make('Masa berlaku')->columns(3)->schema([
                Toggle::make('is_active')->default(true),
                DatePicker::make('effective_from')->required()->default(now()),
                DatePicker::make('effective_to')->afterOrEqual('effective_from'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('city')->searchable(),
                TextColumn::make('phone'),
                IconColumn::make('supports_all_vehicle_makes')
                    ->label('Semua merek')
                    ->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('verified_at')->label('Verifikasi')->dateTime(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtoxpertWorkshops::route('/'),
            'create' => CreateOtoxpertWorkshop::route('/create'),
            'edit' => EditOtoxpertWorkshop::route('/{record}/edit'),
        ];
    }
}
