<?php

namespace App\Filament\Resources\CreditFollowUpLeads;

use App\Filament\Resources\CreditFollowUpLeads\Pages\EditCreditFollowUpLead;
use App\Filament\Resources\CreditFollowUpLeads\Pages\ListCreditFollowUpLeads;
use App\Models\CreditFollowUpLead;
use App\Models\User;
use App\Support\Enums\CreditLeadStatus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CreditFollowUpLeadResource extends Resource
{
    protected static ?string $model = CreditFollowUpLead::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Simulasi Kredit';

    protected static ?string $navigationLabel = 'Lead Follow-up';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('credit_leads.viewAny') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user',
            'assignedSales',
            'simulation.program',
        ]);
        $user = auth()->user();
        if ($user !== null
            && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $query->where('assigned_sales_id', $user->getKey());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan')->columns(3)->schema([
                TextInput::make('reference_no')
                    ->label('Referensi lead')
                    ->disabled(),
                TextInput::make('simulation.reference_no')
                    ->label('Referensi simulasi')
                    ->disabled(),
                TextInput::make('user.name')
                    ->label('Pelanggan')
                    ->disabled(),
                TextInput::make('simulation.program_snapshot.program_name')
                    ->label('Program')
                    ->disabled(),
                TextInput::make('simulation.monthly_installment')
                    ->label('Cicilan bulanan')
                    ->disabled(),
                TextInput::make('contact_channel')
                    ->label('Channel')
                    ->disabled(),
            ]),
            Section::make('Follow-up')->columns(2)->schema([
                Select::make('assigned_sales_id')
                    ->label('Sales')
                    ->options(
                        fn (): array => User::permission('credit_leads.update')
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(
                        fn (): bool => ! (
                            auth()->user()
                                ?->hasAnyRole(['super-admin', 'admin'])
                            ?? false
                        )
                    ),
                Select::make('status')
                    ->options(
                        collect(CreditLeadStatus::cases())
                            ->mapWithKeys(
                                fn (CreditLeadStatus $status): array => [
                                    $status->value => $status->label(),
                                ]
                            )
                            ->all()
                    )
                    ->required(),
                TextInput::make('outcome')
                    ->label('Hasil follow-up')
                    ->maxLength(100),
                Textarea::make('internal_note')
                    ->label('Catatan internal')
                    ->maxLength(3000)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Referensi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                TextColumn::make('simulation.reference_no')
                    ->label('Simulasi')
                    ->searchable(),
                TextColumn::make('simulation.program_snapshot.program_name')
                    ->label('Program'),
                TextColumn::make('simulation.monthly_installment')
                    ->label('Cicilan')
                    ->money('IDR'),
                TextColumn::make('assignedSales.name')
                    ->label('Sales')
                    ->placeholder('Belum ditetapkan'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (CreditLeadStatus $state): string => $state->label()
                    ),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(CreditLeadStatus::cases())
                        ->mapWithKeys(
                            fn (CreditLeadStatus $status): array => [
                                $status->value => $status->label(),
                            ]
                        )
                        ->all()
                ),
                SelectFilter::make('assigned_sales_id')
                    ->label('Sales')
                    ->relationship('assignedSales', 'name'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditFollowUpLeads::route('/'),
            'edit' => EditCreditFollowUpLead::route('/{record}/edit'),
        ];
    }
}
