<?php

namespace App\Filament\Resources\OtoxpertServices;

use App\Filament\Resources\OtoxpertServices\Pages\CreateOtoxpertService;
use App\Filament\Resources\OtoxpertServices\Pages\EditOtoxpertService;
use App\Filament\Resources\OtoxpertServices\Pages\ListOtoxpertServices;
use App\Models\OtoxpertService;
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

class OtoxpertServiceResource extends Resource
{
    protected static ?string $model = OtoxpertService::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'OtoXpert';

    protected static ?string $navigationLabel = 'Jenis Layanan';

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
            Section::make('Layanan')->columns(2)->schema([
                TextInput::make('code')->required()->unique(ignoreRecord: true),
                TextInput::make('name')->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('default_lead_time_days')
                    ->numeric()
                    ->minValue(0)
                    ->default(1)
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
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
                TextColumn::make('code'),
                TextColumn::make('default_lead_time_days')
                    ->label('Lead time')
                    ->suffix(' hari'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtoxpertServices::route('/'),
            'create' => CreateOtoxpertService::route('/create'),
            'edit' => EditOtoxpertService::route('/{record}/edit'),
        ];
    }
}
