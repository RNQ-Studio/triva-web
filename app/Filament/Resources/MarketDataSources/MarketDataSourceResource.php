<?php

namespace App\Filament\Resources\MarketDataSources;

use App\Filament\Resources\MarketDataSources\Pages\EditMarketDataSource;
use App\Filament\Resources\MarketDataSources\Pages\ListMarketDataSources;
use App\Models\MarketDataSource;
use App\Support\Enums\MarketDataSourceStatus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MarketDataSourceResource extends Resource
{
    protected static ?string $model = MarketDataSource::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'TRIVA Operations';

    protected static ?string $navigationLabel = 'Provider Data Pasar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas provider')
                ->description('Kode dan tipe ditetapkan oleh kode aplikasi dan tidak dapat diubah dari back-office.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->disabled()->dehydrated(false),
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('type')->disabled()->dehydrated(false),
                    TextInput::make('base_url')
                        ->label('Base URL')
                        ->url()
                        ->required()
                        ->maxLength(500),
                ]),
            Section::make('Governance izin')
                ->description(
                    'Status Aktif ditolak oleh backend jika bukti izin, tanggal persetujuan, atau masa berlaku belum valid.',
                )
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options(collect(MarketDataSourceStatus::cases())->mapWithKeys(
                            fn (MarketDataSourceStatus $status): array => [
                                $status->value => $status->label(),
                            ],
                        ))
                        ->required(),
                    TextInput::make('approval_reference')
                        ->label('Referensi izin/kontrak')
                        ->maxLength(255),
                    DateTimePicker::make('approved_at')
                        ->label('Disetujui pada')
                        ->seconds(false),
                    DateTimePicker::make('approval_expires_at')
                        ->label('Izin berlaku hingga')
                        ->after('approved_at')
                        ->seconds(false),
                    TextInput::make('rate_limit_per_minute')
                        ->label('Batas request/menit')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60)
                        ->required(),
                    TextInput::make('retention_days')
                        ->label('Retensi snapshot (hari)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->required(),
                    Textarea::make('settings')
                        ->label('Settings JSON')
                        ->formatStateUsing(fn (mixed $state): string => is_array($state)
                            ? (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                            : (string) $state)
                        ->dehydrateStateUsing(function (mixed $state): array {
                            $decoded = json_decode((string) $state, true);

                            return is_array($decoded) ? $decoded : [];
                        })
                        ->helperText('Tidak boleh memuat credential atau data personal.')
                        ->rows(6)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Provider')->searchable(),
                TextColumn::make('type')->label('Tipe')->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (MarketDataSourceStatus $state): string => $state->label(),
                    ),
                TextColumn::make('approval_expires_at')
                    ->label('Izin hingga')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum ada'),
                TextColumn::make('last_success_at')
                    ->label('Sukses terakhir')
                    ->since()
                    ->placeholder('Belum pernah'),
                TextColumn::make('last_error_code')
                    ->label('Health')
                    ->placeholder('Sehat'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketDataSources::route('/'),
            'edit' => EditMarketDataSource::route('/{record}/edit'),
        ];
    }
}
