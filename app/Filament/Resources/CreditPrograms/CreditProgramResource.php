<?php

namespace App\Filament\Resources\CreditPrograms;

use App\Filament\Resources\CreditPrograms\Pages\CreateCreditProgram;
use App\Filament\Resources\CreditPrograms\Pages\EditCreditProgram;
use App\Filament\Resources\CreditPrograms\Pages\ListCreditPrograms;
use App\Models\CreditProgram;
use App\Support\Enums\CreditProgramStatus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CreditProgramResource extends Resource
{
    protected static ?string $model = CreditProgram::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Simulasi Kredit';

    protected static ?string $navigationLabel = 'Program Kredit';

    protected static ?string $recordTitleAttribute = 'program_name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('credit_programs.viewAny') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('credit_programs.create') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?CreditProgram $record): bool => $record
            ?->simulations()->exists() ?? false;

        return $schema->components([
            Section::make('Identitas program')->columns(3)->schema([
                TextInput::make('program_code')
                    ->required()
                    ->maxLength(64)
                    ->disabled($locked),
                TextInput::make('version')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->disabled($locked),
                Select::make('status')
                    ->options(
                        collect(CreditProgramStatus::cases())
                            ->mapWithKeys(
                                fn (CreditProgramStatus $status): array => [
                                    $status->value => $status->label(),
                                ]
                            )
                            ->all()
                    )
                    ->required(),
                TextInput::make('partner_name')
                    ->label('Partner')
                    ->required()
                    ->disabled($locked),
                TextInput::make('program_name')
                    ->label('Nama program')
                    ->required()
                    ->disabled($locked)
                    ->columnSpan(2),
            ]),
            Section::make('Kendaraan dan OTR')->columns(3)->schema([
                TextInput::make('unit_key')
                    ->label('Kunci unit rekomendasi')
                    ->maxLength(40)
                    ->helperText('Isi veloz_hybrid, zenix_hybrid, innova_reborn, atau raize agar unit ini ditawarkan di hasil appraisal.'),
                FileUpload::make('image_path')
                    ->label('Gambar unit')
                    ->image()
                    ->disk('public')
                    ->directory('credit-units')
                    ->maxSize(5120)
                    ->helperText('Foto unit untuk kartu rekomendasi appraisal. JPG/PNG/WEBP, maksimal 5 MB.')
                    ->columnSpan(2),
                TextInput::make('city')
                    ->label('Kota OTR')
                    ->required()
                    ->disabled($locked),
                TextInput::make('vehicle_model')
                    ->label('Model')
                    ->required()
                    ->disabled($locked),
                TextInput::make('vehicle_variant')
                    ->label('Varian')
                    ->required()
                    ->disabled($locked),
                TextInput::make('model_year')
                    ->label('Model year')
                    ->numeric()
                    ->disabled($locked),
                TextInput::make('otr_price')
                    ->label('Harga OTR')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->disabled($locked),
                TextInput::make('approved_discount')
                    ->label('Diskon disetujui')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0)
                    ->disabled($locked),
                TextInput::make('minimum_dp_basis_points')
                    ->label('DP minimum (basis points)')
                    ->helperText('2000 = 20,00%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10000)
                    ->required()
                    ->disabled($locked),
                TextInput::make('maximum_dp_basis_points')
                    ->label('DP maksimum (basis points)')
                    ->helperText('8000 = 80,00%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10000)
                    ->gte('minimum_dp_basis_points')
                    ->required()
                    ->disabled($locked),
            ]),
            Section::make('Tenor dan biaya')->schema([
                Repeater::make('tenor_options')
                    ->minItems(1)
                    ->required()
                    ->disabled($locked)
                    ->columns(3)
                    ->schema([
                        TextInput::make('tenor_months')
                            ->label('Tenor (bulan)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->required(),
                        TextInput::make('annual_flat_rate_basis_points')
                            ->label('Bunga flat (basis points)')
                            ->helperText('525 = 5,25% per tahun')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10000)
                            ->required(),
                        TextInput::make('administration_fee')
                            ->label('Administrasi')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('provision_fee')
                            ->label('Provisi')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('upfront_insurance')
                            ->label('Asuransi awal')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('other_upfront_cost_label')
                            ->label('Label biaya lain'),
                        TextInput::make('other_upfront_costs')
                            ->label('Biaya lain')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(0),
                    ]),
            ]),
            Section::make('Masa berlaku dan sumber')->columns(2)->schema([
                DatePicker::make('effective_from')
                    ->required()
                    ->default(now())
                    ->disabled($locked),
                DatePicker::make('effective_to')
                    ->afterOrEqual('effective_from'),
                Textarea::make('source_reference')
                    ->label('Referensi sumber')
                    ->required()
                    ->maxLength(2000)
                    ->disabled($locked)
                    ->columnSpanFull(),
                Toggle::make('is_demo')
                    ->label('Data demonstrasi')
                    ->helperText(
                        'Penanda ini dikelola oleh seeder demo dan selalu dikirim ke aplikasi customer.'
                    )
                    ->default(false)
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('formula_strategy')
                    ->default('flat_rate')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('formula_version')
                    ->default('flat-v1')
                    ->disabled($locked)
                    ->dehydrated(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')->label('Versi')->sortable(),
                TextColumn::make('partner_name')
                    ->label('Partner')
                    ->searchable(),
                TextColumn::make('program_name')
                    ->label('Program')
                    ->searchable(),
                TextColumn::make('vehicle_model')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('vehicle_variant')->label('Varian'),
                TextColumn::make('city')->label('Kota')->searchable(),
                TextColumn::make('otr_price')->label('OTR')->money('IDR'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (CreditProgramStatus $state): string => $state
                            ->label()
                    ),
                TextColumn::make('effective_to')
                    ->label('Berlaku s.d.')
                    ->date(),
                TextColumn::make('is_demo')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(
                        fn (bool $state): string => $state
                            ? 'Demo'
                            : 'Program partner'
                    )
                    ->color(
                        fn (bool $state): string => $state
                            ? 'warning'
                            : 'success'
                    ),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(CreditProgramStatus::cases())
                        ->mapWithKeys(
                            fn (CreditProgramStatus $status): array => [
                                $status->value => $status->label(),
                            ]
                        )
                        ->all()
                ),
                SelectFilter::make('city')->options(
                    fn (): array => CreditProgram::query()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->all()
                ),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditPrograms::route('/'),
            'create' => CreateCreditProgram::route('/create'),
            'edit' => EditCreditProgram::route('/{record}/edit'),
        ];
    }
}
