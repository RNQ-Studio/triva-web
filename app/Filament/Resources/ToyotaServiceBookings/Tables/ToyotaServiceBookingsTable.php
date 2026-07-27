<?php

namespace App\Filament\Resources\ToyotaServiceBookings\Tables;

use App\Models\ToyotaServiceBooking;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToyotaServiceBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Referensi')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->description(fn (ToyotaServiceBooking $record): string => $record->vehicle->license_plate),
                TextColumn::make('serviceType.name')
                    ->label('Layanan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (ToyotaServiceBookingStatus $state): string => $state->customerLabel()
                    )
                    ->color(fn (ToyotaServiceBookingStatus $state): string => match ($state) {
                        ToyotaServiceBookingStatus::Confirmed,
                        ToyotaServiceBookingStatus::Completed => 'success',
                        ToyotaServiceBookingStatus::AlternativeProposed,
                        ToyotaServiceBookingStatus::RescheduleRequested => 'warning',
                        ToyotaServiceBookingStatus::Rejected,
                        ToyotaServiceBookingStatus::Cancelled,
                        ToyotaServiceBookingStatus::Expired,
                        ToyotaServiceBookingStatus::NoShow => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('active_slot_start_at')
                    ->label('Jadwal lokal')
                    ->state(fn (ToyotaServiceBooking $record): string => $record
                        ->active_slot_start_at
                        ->timezone($record->serviceLocation->timezone)
                        ->format('d M Y H:i'))
                    ->description(fn (ToyotaServiceBooking $record): string => $record
                        ->active_slot_end_at
                        ->timezone($record->serviceLocation->timezone)
                        ->format('H:i').' '.$record->serviceLocation->timezone)
                    ->sortable(),
                TextColumn::make('assignedServiceAdvisor.name')
                    ->label('Advisor')
                    ->placeholder('Belum ditetapkan')
                    ->searchable(),
                TextColumn::make('due_at')
                    ->label('SLA')
                    ->state(fn (ToyotaServiceBooking $record): string => $record->isSlaOverdue()
                        ? 'Terlambat '.$record->due_at->diffForHumans()
                        : $record->due_at->diffForHumans())
                    ->color(fn (ToyotaServiceBooking $record): string => $record->isSlaOverdue()
                        ? 'danger'
                        : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ToyotaServiceBookingStatus::cases())->mapWithKeys(
                        fn (ToyotaServiceBookingStatus $status): array => [
                            $status->value => $status->customerLabel(),
                        ]
                    )),
                SelectFilter::make('service_type_id')
                    ->label('Layanan')
                    ->relationship('serviceType', 'name'),
                SelectFilter::make('service_location_id')
                    ->label('Lokasi')
                    ->relationship('serviceLocation', 'name'),
                SelectFilter::make('fulfillment_type')
                    ->label('Cara servis')
                    ->options(collect(ToyotaServiceFulfillmentType::cases())->mapWithKeys(
                        fn (ToyotaServiceFulfillmentType $type): array => [
                            $type->value => $type->label(),
                        ]
                    )),
                SelectFilter::make('assigned_service_advisor_id')
                    ->label('Advisor')
                    ->relationship('assignedServiceAdvisor', 'name'),
                Filter::make('service_date')
                    ->label('Tanggal servis')
                    ->form([
                        DatePicker::make('date')->label('Tanggal lokal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['date'] ?? null)
                        ? ToyotaServiceBooking::constrainToLocalDate(
                            $query,
                            (string) $data['date'],
                        )
                        : $query),
                SelectFilter::make('sla')
                    ->label('SLA')
                    ->options([
                        'overdue' => 'Terlambat',
                        'within_sla' => 'Dalam SLA',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'overdue' => $query
                                ->where('status', ToyotaServiceBookingStatus::AwaitingConfirmation)
                                ->where('due_at', '<', now()),
                            'within_sla' => $query->where(function (Builder $builder): void {
                                $builder
                                    ->where('status', '!=', ToyotaServiceBookingStatus::AwaitingConfirmation)
                                    ->orWhere('due_at', '>=', now());
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }
}
