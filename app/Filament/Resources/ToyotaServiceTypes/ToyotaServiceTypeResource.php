<?php

namespace App\Filament\Resources\ToyotaServiceTypes;

use App\Filament\Resources\ToyotaServiceTypes\Pages\CreateToyotaServiceType;
use App\Filament\Resources\ToyotaServiceTypes\Pages\EditToyotaServiceType;
use App\Filament\Resources\ToyotaServiceTypes\Pages\ListToyotaServiceTypes;
use App\Models\ToyotaServiceType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class ToyotaServiceTypeResource extends Resource
{
    protected static ?string $model = ToyotaServiceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|UnitEnum|null $navigationGroup = 'Toyota Service';

    protected static ?string $navigationLabel = 'Jenis Layanan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jenis layanan')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(60),
                    TextInput::make('name')->label('Nama')->required()->maxLength(120),
                    Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
                    Toggle::make('supports_workshop')
                        ->label('Tersedia di workshop')
                        ->default(true),
                    Toggle::make('supports_ths')
                        ->label('Tersedia untuk THS')
                        ->default(false),
                    TextInput::make('workshop_lead_time_days')
                        ->label('Lead time workshop (hari)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(2),
                    TextInput::make('ths_lead_time_days')
                        ->label('Lead time THS (hari)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(1),
                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),
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
                TextColumn::make('name')->label('Layanan')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->searchable(),
                IconColumn::make('supports_workshop')->label('Workshop')->boolean(),
                IconColumn::make('supports_ths')->label('THS')->boolean(),
                TextColumn::make('workshop_lead_time_days')
                    ->label('Lead workshop')
                    ->suffix(' hari'),
                TextColumn::make('ths_lead_time_days')->label('Lead THS')->suffix(' hari'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaServiceTypes::route('/'),
            'create' => CreateToyotaServiceType::route('/create'),
            'edit' => EditToyotaServiceType::route('/{record}/edit'),
        ];
    }
}
