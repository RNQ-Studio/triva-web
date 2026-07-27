<?php

namespace App\Filament\Resources\CreditSimulations\Tables;

use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditSimulationStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreditSimulationsTable
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
                    ->state(
                        fn (CreditSimulation $record): string => implode(
                            ' ',
                            [
                                (string) data_get(
                                    $record->program_snapshot,
                                    'vehicle_model',
                                ),
                                (string) data_get(
                                    $record->program_snapshot,
                                    'vehicle_variant',
                                ),
                            ],
                        )
                    ),
                TextColumn::make('tenor_months')
                    ->label('Tenor')
                    ->suffix(' bln')
                    ->sortable(),
                TextColumn::make('monthly_installment')
                    ->label('Cicilan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (CreditSimulationStatus $state): string => $state
                            ->label()
                    ),
                TextColumn::make('appraisal.reference_no')
                    ->label('Appraisal')
                    ->placeholder('Manual/tanpa trade-in'),
                TextColumn::make('followUpLead.assignedSales.name')
                    ->label('Sales')
                    ->placeholder('Belum ditetapkan'),
                TextColumn::make('saved_at')
                    ->label('Disimpan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(CreditSimulationStatus::cases())
                        ->mapWithKeys(
                            fn (CreditSimulationStatus $status): array => [
                                $status->value => $status->label(),
                            ]
                        )
                        ->all()
                ),
                SelectFilter::make('credit_program_id')
                    ->label('Program')
                    ->options(
                        fn (): array => CreditProgram::query()
                            ->orderBy('program_name')
                            ->pluck('program_name', 'id')
                            ->all()
                    ),
                SelectFilter::make('tenor_months')
                    ->label('Tenor')
                    ->options(
                        fn (): array => CreditSimulation::query()
                            ->distinct()
                            ->orderBy('tenor_months')
                            ->pluck('tenor_months', 'tenor_months')
                            ->mapWithKeys(
                                fn (int $months): array => [
                                    $months => "{$months} bulan",
                                ]
                            )
                            ->all()
                    ),
                SelectFilter::make('vehicle_model')
                    ->label('Model')
                    ->options(
                        fn (): array => CreditProgram::query()
                            ->distinct()
                            ->orderBy('vehicle_model')
                            ->pluck('vehicle_model', 'vehicle_model')
                            ->all()
                    )
                    ->query(
                        fn (Builder $query, array $data): Builder => filled(
                            $data['value'] ?? null,
                        )
                            ? $query->whereHas(
                                'program',
                                fn (Builder $program): Builder => $program
                                    ->where(
                                        'vehicle_model',
                                        $data['value'],
                                    ),
                            )
                            : $query
                    ),
                SelectFilter::make('assigned_sales_id')
                    ->label('Sales')
                    ->options(
                        fn (): array => User::permission(
                            'credit_leads.update',
                        )
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->query(
                        fn (Builder $query, array $data): Builder => filled(
                            $data['value'] ?? null,
                        )
                            ? $query->whereHas(
                                'followUpLead',
                                fn (Builder $lead): Builder => $lead->where(
                                    'assigned_sales_id',
                                    $data['value'],
                                ),
                            )
                            : $query
                    ),
                TernaryFilter::make('has_appraisal')
                    ->label('Sumber appraisal')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereNotNull('appraisal_id'),
                        false: fn (Builder $query): Builder => $query
                            ->whereNull('appraisal_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('saved_at', 'desc');
    }
}
