<?php

namespace App\Filament\Resources\AppConfigs;

use App\Filament\Resources\AppConfigs\Pages\ListAppConfigs;
use App\Models\AppConfig;
use App\Services\PlayStoreInstallsService;
use App\Support\Enums\AppConfigType;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AppConfigResource extends Resource
{
    protected static ?string $model = AppConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'App Management';

    protected static ?string $navigationLabel = 'App Config';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(100)
                ->disabled(fn ($record) => $record !== null)
                ->helperText('Snake case key, cannot be changed after creation.'),
            Select::make('type')
                ->options(array_column(AppConfigType::cases(), 'value', 'value'))
                ->required(),
            Textarea::make('value')
                ->nullable()
                ->rows(2),
            Textarea::make('description')
                ->nullable()
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->copyable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('value')
                    ->limit(60)
                    ->tooltip(fn ($record): string => (string) $record->value),
                TextColumn::make('description')->limit(50)->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()->after(function (AppConfig $record): void {
                    AppConfig::bustCache($record->key);

                    // Total download Play Store di-cache terpisah dari App
                    // Config, jadi angka baru tidak akan terlihat di panel
                    // admin sampai cache itu ikut dibuang.
                    if (str_starts_with($record->key, 'play_store_')) {
                        PlayStoreInstallsService::bustCache();
                    }
                }),
            ])
            ->defaultSort('key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppConfigs::route('/'),
        ];
    }
}
