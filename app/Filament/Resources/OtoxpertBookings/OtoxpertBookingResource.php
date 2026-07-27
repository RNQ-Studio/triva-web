<?php

namespace App\Filament\Resources\OtoxpertBookings;

use App\Filament\Resources\OtoxpertBookings\Pages\ListOtoxpertBookings;
use App\Filament\Resources\OtoxpertBookings\Pages\ViewOtoxpertBooking;
use App\Models\OtoxpertBooking;
use App\Support\Enums\OtoxpertBookingStatus;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OtoxpertBookingResource extends Resource
{
    protected static ?string $model = OtoxpertBooking::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'OtoXpert';

    protected static ?string $navigationLabel = 'Booking Queue';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('service_bookings.viewAny') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user',
            'vehicle',
            'workshop',
            'service',
            'assignedOperator',
            'photos.asset',
            'statusHistories.changedBy',
        ]);
        $user = auth()->user();
        if ($user !== null
            && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $query->whereHas(
                'workshop.operators',
                fn (Builder $operator): Builder => $operator
                    ->whereKey($user->getKey())
                    ->where(
                        'otoxpert_workshop_operators.is_active',
                        true,
                    ),
            );
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Referensi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('workshop.name')
                    ->label('Workshop')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                TextColumn::make('vehicle.license_plate')
                    ->label('Nomor polisi')
                    ->searchable(),
                TextColumn::make('service.name')->label('Layanan'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (OtoxpertBookingStatus $state): string => $state
                            ->customerLabel()
                    ),
                IconColumn::make('sla_overdue')
                    ->label('Overdue')
                    ->state(fn (OtoxpertBooking $record): bool => $record
                        ->isSlaOverdue())
                    ->boolean(),
                TextColumn::make('primary_start_at')
                    ->label('Jadwal diminta')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(OtoxpertBookingStatus::cases())
                        ->mapWithKeys(fn (OtoxpertBookingStatus $status): array => [
                            $status->value => $status->customerLabel(),
                        ])->all()
                ),
                SelectFilter::make('workshop_id')
                    ->label('Workshop')
                    ->relationship('workshop', 'name'),
                SelectFilter::make('service_id')
                    ->label('Layanan')
                    ->relationship('service', 'name'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')->columns(3)->schema([
                TextEntry::make('reference_no')->label('Referensi'),
                TextEntry::make('status')
                    ->label('Status')
                    ->formatStateUsing(
                        fn (OtoxpertBookingStatus $state): string => $state
                            ->customerLabel()
                    ),
                TextEntry::make('workshop.name')->label('Workshop'),
                TextEntry::make('service.name')->label('Layanan'),
                TextEntry::make('primary_start_at')
                    ->label('Pilihan utama')
                    ->dateTime('d M Y H:i'),
                TextEntry::make('alternative_start_at')
                    ->label('Pilihan alternatif')
                    ->dateTime('d M Y H:i'),
                TextEntry::make('confirmed_start_at')
                    ->label('Terkonfirmasi')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum dikonfirmasi'),
                TextEntry::make('assignedOperator.name')
                    ->label('Operator')
                    ->placeholder('Belum ditetapkan'),
                TextEntry::make('external_booking_number')
                    ->label('Nomor partner')
                    ->placeholder('-'),
            ]),
            Section::make('Pelanggan & kendaraan')->columns(3)->schema([
                TextEntry::make('user.name')->label('Pelanggan'),
                TextEntry::make('user.phone')->label('Telepon'),
                TextEntry::make('vehicle.license_plate')
                    ->label('Nomor polisi'),
                TextEntry::make('vehicle.make')->label('Merek'),
                TextEntry::make('vehicle.model')->label('Model'),
                TextEntry::make('current_mileage')
                    ->label('Kilometer')
                    ->numeric(),
                TextEntry::make('complaint')
                    ->label('Keluhan')
                    ->columnSpanFull(),
                TextEntry::make('symptom_codes')
                    ->label('Gejala')
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
            ]),
            Section::make('Operasional')->columns(2)->schema([
                TextEntry::make('reason')->label('Alasan')->placeholder('-'),
                TextEntry::make('arrival_instructions')
                    ->label('Instruksi kedatangan')
                    ->placeholder('-'),
                TextEntry::make('quoted_price_min')
                    ->label('Harga minimum')
                    ->money('IDR')
                    ->placeholder('Dikonfirmasi bengkel'),
                TextEntry::make('quoted_price_max')
                    ->label('Harga maksimum')
                    ->money('IDR')
                    ->placeholder('-'),
                TextEntry::make('campaign_source')
                    ->label('Campaign')
                    ->placeholder('-'),
                TextEntry::make('follow_up_outcome')
                    ->label('Follow up')
                    ->placeholder('-'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtoxpertBookings::route('/'),
            'view' => ViewOtoxpertBooking::route('/{record}'),
        ];
    }
}
