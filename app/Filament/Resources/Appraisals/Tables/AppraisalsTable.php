<?php

namespace App\Filament\Resources\Appraisals\Tables;

use App\Models\Appraisal;
use App\Support\Enums\AppraisalStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppraisalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Referensi')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                TextColumn::make('vehicle')
                    ->label('Kendaraan')
                    ->state(fn (Appraisal $record): string => implode(' ', [
                        $record->vehicle->make,
                        $record->vehicle->model,
                        $record->vehicle->variant,
                        (string) $record->vehicle->year,
                    ])),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AppraisalStatus $state): string => $state->customerLabel())
                    ->color(fn (AppraisalStatus $state): string => match ($state) {
                        AppraisalStatus::ResultReady,
                        AppraisalStatus::AcceptedByCustomer,
                        AppraisalStatus::Converted => 'success',
                        AppraisalStatus::NeedsCustomerAction,
                        AppraisalStatus::InsufficientComparables => 'warning',
                        AppraisalStatus::Cancelled,
                        AppraisalStatus::Failed,
                        AppraisalStatus::Expired => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('due_at')
                    ->label('Target SLA')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Belum disubmit'),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AppraisalStatus::cases())->mapWithKeys(
                        fn (AppraisalStatus $status): array => [$status->value => $status->customerLabel()]
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
