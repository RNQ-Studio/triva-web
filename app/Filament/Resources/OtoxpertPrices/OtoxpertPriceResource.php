<?php

namespace App\Filament\Resources\OtoxpertPrices;

use App\Filament\Resources\OtoxpertPrices\Pages\CreateOtoxpertPrice;
use App\Filament\Resources\OtoxpertPrices\Pages\EditOtoxpertPrice;
use App\Filament\Resources\OtoxpertPrices\Pages\ListOtoxpertPrices;
use App\Models\OtoxpertWorkshopServicePrice;
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

class OtoxpertPriceResource extends Resource
{
    protected static ?string $model = OtoxpertWorkshopServicePrice::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'OtoXpert';

    protected static ?string $navigationLabel = 'Harga Indikatif';

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
            Section::make('Master harga')->columns(2)->schema([
                Select::make('workshop_id')
                    ->relationship('workshop', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('price_type')
                    ->options(['from' => 'Mulai dari', 'range' => 'Rentang'])
                    ->required(),
                TextInput::make('currency')->default('IDR')->required(),
                TextInput::make('minimum_amount')
                    ->label('Harga minimum')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('maximum_amount')
                    ->label('Harga maksimum')
                    ->numeric()
                    ->gte('minimum_amount'),
                TagsInput::make('included_items')->label('Termasuk'),
                TagsInput::make('excluded_items')->label('Tidak termasuk'),
                Textarea::make('disclaimer')->required()->columnSpanFull(),
                TextInput::make('source_url')
                    ->label('Sumber resmi')
                    ->url()
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('verified_at')->required(),
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
                TextColumn::make('workshop.name')->label('Workshop')->searchable(),
                TextColumn::make('service.name')->label('Layanan')->searchable(),
                TextColumn::make('minimum_amount')
                    ->label('Mulai')
                    ->money('IDR'),
                TextColumn::make('maximum_amount')->label('Maks')->money('IDR'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('effective_to')->label('Berlaku s.d.')->date(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('verified_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtoxpertPrices::route('/'),
            'create' => CreateOtoxpertPrice::route('/create'),
            'edit' => EditOtoxpertPrice::route('/{record}/edit'),
        ];
    }
}
