<?php

namespace App\Filament\Resources\BodyPaintEstimates\Tables;

use App\Models\BodyPaintEstimate;
use App\Support\Enums\BodyPaintEstimateStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BodyPaintEstimatesTable
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
                    ->description(
                        fn (BodyPaintEstimate $record): string => implode(' ', array_filter([
                            $record->vehicle->make,
                            $record->vehicle->model,
                            $record->vehicle->license_plate,
                        ])),
                    ),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (BodyPaintEstimateStatus $state): string => $state->label(),
                    )
                    ->color(fn (BodyPaintEstimateStatus $state): string => match ($state) {
                        BodyPaintEstimateStatus::EstimateReady,
                        BodyPaintEstimateStatus::Accepted,
                        BodyPaintEstimateStatus::BookingRequested => 'success',
                        BodyPaintEstimateStatus::ManualReview,
                        BodyPaintEstimateStatus::NeedsCustomerAction => 'warning',
                        BodyPaintEstimateStatus::Declined,
                        BodyPaintEstimateStatus::Expired,
                        BodyPaintEstimateStatus::Cancelled => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('serviceLocation.name')
                    ->label('Cabang')
                    ->placeholder('Belum dipilih')
                    ->searchable(),
                TextColumn::make('assignedEstimator.name')
                    ->label('Estimator')
                    ->placeholder('Belum ditetapkan')
                    ->searchable(),
                IconColumn::make('has_high_risk_damage')
                    ->label('Risiko tinggi')
                    ->boolean(),
                TextColumn::make('due_at')
                    ->label('SLA')
                    ->state(fn (BodyPaintEstimate $record): string => $record->due_at === null
                        ? '-'
                        : ($record->isSlaOverdue()
                            ? 'Terlambat '.$record->due_at->diffForHumans()
                            : $record->due_at->diffForHumans()))
                    ->color(fn (BodyPaintEstimate $record): string => $record->isSlaOverdue()
                        ? 'danger'
                        : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BodyPaintEstimateStatus::cases())->mapWithKeys(
                        fn (BodyPaintEstimateStatus $status): array => [
                            $status->value => $status->label(),
                        ],
                    )),
                SelectFilter::make('service_location_id')
                    ->label('Cabang')
                    ->relationship('serviceLocation', 'name'),
                SelectFilter::make('assigned_estimator_id')
                    ->label('Estimator')
                    ->relationship('assignedEstimator', 'name'),
                SelectFilter::make('sla')
                    ->label('SLA')
                    ->options([
                        'overdue' => 'Terlambat',
                        'within_sla' => 'Dalam SLA',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $waitingStatuses = [
                            BodyPaintEstimateStatus::Submitted,
                            BodyPaintEstimateStatus::AutoEstimated,
                            BodyPaintEstimateStatus::ManualReview,
                        ];

                        return match ($data['value'] ?? null) {
                            'overdue' => $query
                                ->whereIn('status', $waitingStatuses)
                                ->where('due_at', '<', now()),
                            'within_sla' => $query->where(function (Builder $builder) use ($waitingStatuses): void {
                                $builder
                                    ->whereNotIn('status', $waitingStatuses)
                                    ->orWhereNull('due_at')
                                    ->orWhere('due_at', '>=', now());
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }
}
