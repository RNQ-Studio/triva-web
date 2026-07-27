<?php

namespace App\Filament\Resources\BodyPaintEstimates\Pages;

use App\Exceptions\BodyPaintConflictException;
use App\Filament\Resources\BodyPaintEstimates\BodyPaintEstimateResource;
use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintEstimateItem;
use App\Models\User;
use App\Services\BodyPaintEstimatorService;
use App\Support\Enums\BodyPaintAdminAction;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class ViewBodyPaintEstimate extends ViewRecord
{
    protected static string $resource = BodyPaintEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label('Tetapkan estimator')
                ->icon('heroicon-o-user-plus')
                ->authorize(fn (BodyPaintEstimate $record): bool => auth()->user()?->can('manage', $record) ?? false)
                ->hidden(fn (BodyPaintEstimate $record): bool => ! $this->hasAction(
                    $record,
                    BodyPaintAdminAction::Assign,
                ))
                ->form([
                    Select::make('estimator_id')
                        ->label('Estimator')
                        ->options(fn (): array => User::permission('bp_estimates.update')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (BodyPaintEstimate $record, array $data) => $this->execute(
                    $record,
                    BodyPaintAdminAction::Assign,
                    $data,
                )),
            Action::make('start_review')
                ->label('Mulai review')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->authorize(fn (BodyPaintEstimate $record): bool => auth()->user()?->can('manage', $record) ?? false)
                ->hidden(fn (BodyPaintEstimate $record): bool => ! $this->hasAction(
                    $record,
                    BodyPaintAdminAction::StartReview,
                ))
                ->requiresConfirmation()
                ->action(fn (BodyPaintEstimate $record) => $this->execute(
                    $record,
                    BodyPaintAdminAction::StartReview,
                )),
            Action::make('request_photos')
                ->label('Minta foto tambahan')
                ->icon('heroicon-o-camera')
                ->color('warning')
                ->authorize(fn (BodyPaintEstimate $record): bool => auth()->user()?->can('manage', $record) ?? false)
                ->hidden(fn (BodyPaintEstimate $record): bool => ! $this->hasAction(
                    $record,
                    BodyPaintAdminAction::RequestPhotos,
                ))
                ->form(fn (BodyPaintEstimate $record): array => [
                    Select::make('rejected_photo_ids')
                        ->label('Foto yang harus diganti')
                        ->options($record->photos->mapWithKeys(
                            fn ($photo): array => [
                                $photo->getKey() => implode(' - ', array_filter([
                                    $photo->asset->original_filename,
                                    $photo->damage?->panel_code,
                                ])),
                            ],
                        ))
                        ->multiple()
                        ->required()
                        ->minItems(1),
                    Select::make('reason_code')
                        ->label('Kode alasan')
                        ->options([
                            'blurred' => 'Foto buram',
                            'too_dark' => 'Pencahayaan kurang',
                            'wrong_angle' => 'Sudut tidak sesuai',
                            'damage_not_visible' => 'Kerusakan tidak terlihat',
                            'missing_context' => 'Foto konteks kurang',
                            'other' => 'Lainnya',
                        ])
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan dan panduan')
                        ->required()
                        ->minLength(5)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(fn (BodyPaintEstimate $record, array $data) => $this->execute(
                    $record,
                    BodyPaintAdminAction::RequestPhotos,
                    $data,
                )),
            Action::make('publish')
                ->label('Terbitkan estimasi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->authorize(fn (BodyPaintEstimate $record): bool => auth()->user()?->can('manage', $record) ?? false)
                ->hidden(fn (BodyPaintEstimate $record): bool => ! $this->hasAction(
                    $record,
                    BodyPaintAdminAction::Publish,
                ))
                ->form(fn (BodyPaintEstimate $record): array => $this->publishForm($record))
                ->action(fn (BodyPaintEstimate $record, array $data) => $this->execute(
                    $record,
                    BodyPaintAdminAction::Publish,
                    $data,
                )),
            Action::make('schedule_inspection')
                ->label('Jadwalkan inspeksi')
                ->icon('heroicon-o-calendar-days')
                ->authorize(fn (BodyPaintEstimate $record): bool => auth()->user()?->can('manage', $record) ?? false)
                ->hidden(fn (BodyPaintEstimate $record): bool => ! $this->hasAction(
                    $record,
                    BodyPaintAdminAction::ScheduleInspection,
                ))
                ->form([
                    DateTimePicker::make('inspection_at')
                        ->label('Jadwal inspeksi')
                        ->required()
                        ->after('now')
                        ->seconds(false),
                    Textarea::make('inspection_note')
                        ->label('Instruksi pelanggan')
                        ->maxLength(1000),
                ])
                ->action(fn (BodyPaintEstimate $record, array $data) => $this->execute(
                    $record,
                    BodyPaintAdminAction::ScheduleInspection,
                    $data,
                )),
        ];
    }

    private function hasAction(
        BodyPaintEstimate $estimate,
        BodyPaintAdminAction $action,
    ): bool {
        return in_array($action, $estimate->availableAdminActions(), true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function execute(
        BodyPaintEstimate $estimate,
        BodyPaintAdminAction $action,
        array $data = [],
    ): void {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            app(BodyPaintEstimatorService::class)->execute(
                $estimate,
                $actor,
                ['action' => $action->value, ...$data],
            );
            Notification::make()
                ->title($action->label().' berhasil')
                ->success()
                ->send();
            $this->refreshFormData([]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Data estimator belum valid')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        } catch (BodyPaintConflictException $exception) {
            Notification::make()
                ->title('Aksi tidak dapat diproses')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Aksi gagal diproses')
                ->body('Periksa data lalu coba kembali.')
                ->danger()
                ->send();
        }
    }

    /** @return array<int, mixed> */
    private function publishForm(BodyPaintEstimate $estimate): array
    {
        $defaults = $this->publishItemDefaults($estimate);

        return [
            Repeater::make('items')
                ->label('Item pekerjaan')
                ->default($defaults)
                ->minItems(1)
                ->maxItems(50)
                ->columns(3)
                ->schema([
                    Select::make('damage_id')
                        ->label('Panel / kerusakan')
                        ->options($estimate->damages->mapWithKeys(
                            fn ($damage): array => [
                                $damage->getKey() => "{$damage->panel_code} - {$damage->damage_type}",
                            ],
                        ))
                        ->required(),
                    Select::make('severity')
                        ->options(collect(BodyPaintSeverity::cases())
                            ->reject(fn (BodyPaintSeverity $severity): bool => $severity === BodyPaintSeverity::Unsure)
                            ->mapWithKeys(fn (BodyPaintSeverity $severity): array => [
                                $severity->value => $severity->label(),
                            ]))
                        ->required(),
                    Select::make('work_type')
                        ->label('Pekerjaan')
                        ->options(collect(BodyPaintWorkType::cases())->mapWithKeys(
                            fn (BodyPaintWorkType $type): array => [
                                $type->value => $type->label(),
                            ],
                        ))
                        ->required(),
                    TextInput::make('labor_low')
                        ->label('Jasa rendah')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('labor_high')
                        ->label('Jasa tinggi')
                        ->numeric()
                        ->minValue(0)
                        ->gte('labor_low')
                        ->required(),
                    TextInput::make('material_low')
                        ->label('Material rendah')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('material_high')
                        ->label('Material tinggi')
                        ->numeric()
                        ->minValue(0)
                        ->gte('material_low')
                        ->required(),
                    TextInput::make('parts_low')
                        ->label('Parts rendah')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('parts_high')
                        ->label('Parts tinggi')
                        ->numeric()
                        ->minValue(0)
                        ->gte('parts_low')
                        ->required(),
                    TextInput::make('other_low')
                        ->label('Lainnya rendah')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('other_high')
                        ->label('Lainnya tinggi')
                        ->numeric()
                        ->minValue(0)
                        ->gte('other_low')
                        ->required(),
                    TextInput::make('duration_min_hours')
                        ->label('Durasi min. (jam)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required(),
                    TextInput::make('duration_max_hours')
                        ->label('Durasi maks. (jam)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->gte('duration_min_hours')
                        ->required(),
                    Textarea::make('recommendation')
                        ->label('Rekomendasi')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            TagsInput::make('assumptions')
                ->label('Asumsi')
                ->required(),
            Textarea::make('disclaimer')
                ->label('Disclaimer pelanggan')
                ->default('Estimasi bersifat indikatif dan nilai final ditentukan setelah inspeksi fisik kendaraan.')
                ->required()
                ->minLength(20)
                ->maxLength(3000)
                ->columnSpanFull(),
            TextInput::make('valid_days')
                ->label('Berlaku (hari)')
                ->numeric()
                ->default(7)
                ->minValue(1)
                ->maxValue(90)
                ->required(),
            Select::make('override_reason_code')
                ->label('Kode override')
                ->options([
                    'manual_fallback' => 'Fallback manual',
                    'severity_adjustment' => 'Koreksi severity',
                    'scope_adjustment' => 'Koreksi lingkup kerja',
                    'price_adjustment' => 'Koreksi harga',
                    'high_risk_review' => 'Review risiko tinggi',
                    'revision' => 'Revisi hasil',
                ])
                ->helperText('Wajib untuk fallback manual, risiko tinggi, atau perubahan dari engine.'),
            Textarea::make('override_reason')
                ->label('Alasan override')
                ->minLength(5)
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function publishItemDefaults(BodyPaintEstimate $estimate): array
    {
        $items = $estimate->current_version > 0
            ? $estimate->items->where('estimate_version', $estimate->current_version)
            : $estimate->items->whereNull('estimate_version');

        if ($items->isEmpty()) {
            return $estimate->damages->map(fn ($damage): array => [
                'damage_id' => $damage->getKey(),
                'severity' => $damage->getRawOriginal('estimator_severity')
                    ?: ($damage->customer_severity === BodyPaintSeverity::Unsure
                        ? BodyPaintSeverity::Medium->value
                        : $damage->customer_severity->value),
                'work_type' => BodyPaintWorkType::Inspect->value,
                'labor_low' => 0,
                'labor_high' => 0,
                'material_low' => 0,
                'material_high' => 0,
                'parts_low' => 0,
                'parts_high' => 0,
                'other_low' => 0,
                'other_high' => 0,
                'duration_min_hours' => 1,
                'duration_max_hours' => 1,
                'recommendation' => null,
            ])->values()->all();
        }

        return $items->map(fn (BodyPaintEstimateItem $item): array => [
            'damage_id' => $item->damage_id,
            'severity' => $item->severity->value,
            'work_type' => $item->work_type->value,
            'labor_low' => $item->labor_low,
            'labor_high' => $item->labor_high,
            'material_low' => $item->material_low,
            'material_high' => $item->material_high,
            'parts_low' => $item->parts_low,
            'parts_high' => $item->parts_high,
            'other_low' => $item->other_low,
            'other_high' => $item->other_high,
            'duration_min_hours' => $item->duration_min_hours,
            'duration_max_hours' => $item->duration_max_hours,
            'recommendation' => $item->recommendation,
        ])->values()->all();
    }
}
