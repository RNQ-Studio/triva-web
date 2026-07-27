<?php

namespace App\Filament\Resources\ToyotaServiceBookings\Pages;

use App\Exceptions\ToyotaServiceConflictException;
use App\Filament\Resources\ToyotaServiceBookings\ToyotaServiceBookingResource;
use App\Models\ToyotaServiceBooking;
use App\Models\User;
use App\Services\ToyotaServiceAvailabilityService;
use App\Services\ToyotaServiceBookingAdminService;
use App\Support\Enums\BenefitVerificationSource;
use App\Support\Enums\ToyotaServiceAdminAction;
use App\Support\Enums\ToyotaServiceReasonCode;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class ViewToyotaServiceBooking extends ViewRecord
{
    protected static string $resource = ToyotaServiceBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->assignAction(),
            $this->slotAction(ToyotaServiceAdminAction::Confirm, 'Konfirmasi booking'),
            $this->proposeAlternativeAction(),
            $this->reasonAction(ToyotaServiceAdminAction::Reject, 'Tolak permintaan'),
            $this->slotAction(
                ToyotaServiceAdminAction::ConfirmReschedule,
                'Konfirmasi jadwal ulang',
            ),
            $this->simpleAction(ToyotaServiceAdminAction::CheckIn, 'Tandai check-in'),
            $this->simpleAction(ToyotaServiceAdminAction::StartService, 'Mulai servis'),
            $this->completeAction(),
            $this->reasonAction(ToyotaServiceAdminAction::MarkNoShow, 'Tandai tidak hadir'),
            $this->reasonAction(ToyotaServiceAdminAction::Cancel, 'Batalkan booking'),
            $this->verifyBenefitAction(),
        ];
    }

    private function assignAction(): Action
    {
        return $this->baseAction(ToyotaServiceAdminAction::Assign, 'Tetapkan advisor')
            ->form([
                Select::make('advisor_id')
                    ->label('Service Advisor')
                    ->options(fn () => User::query()
                        ->permission('service_bookings.update')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(fn (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) => $this->execute($record, ToyotaServiceAdminAction::Assign, $data, $service));
    }

    private function slotAction(ToyotaServiceAdminAction $action, string $label): Action
    {
        return $this->baseAction($action, $label)
            ->form([
                Select::make('confirmed_date')
                    ->label('Tanggal')
                    ->options(fn (ToyotaServiceBooking $record): array => $this
                        ->confirmationDateOptions($record, $action))
                    ->live()
                    ->required(),
                Select::make('confirmed_window')
                    ->label('Rentang waktu')
                    ->options(fn (
                        Get $get,
                        ToyotaServiceBooking $record,
                    ): array => $this->confirmationWindowOptions($get, $record, $action))
                    ->required(),
                TextInput::make('pic_name')
                    ->label('Nama PIC')
                    ->required()
                    ->maxLength(120),
                Textarea::make('arrival_instructions')
                    ->label('Instruksi kedatangan')
                    ->required()
                    ->minLength(5)
                    ->maxLength(2000),
                TextInput::make('external_booking_number')
                    ->label('Nomor booking dealer')
                    ->maxLength(120),
                Textarea::make('note')->label('Catatan internal (opsional)')->maxLength(1000),
            ])
            ->action(function (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) use ($action): void {
                $data['confirmed_slot'] = [
                    'date' => $data['confirmed_date'],
                    'time_window' => $data['confirmed_window'],
                ];
                unset($data['confirmed_date'], $data['confirmed_window']);
                $this->execute($record, $action, $data, $service);
            });
    }

    private function proposeAlternativeAction(): Action
    {
        $action = ToyotaServiceAdminAction::ProposeAlternative;

        return $this->baseAction($action, 'Ajukan jadwal alternatif')
            ->form([
                DatePicker::make('proposed_date')
                    ->label('Tanggal usulan')
                    ->native(false)
                    ->live()
                    ->required(),
                Select::make('proposed_window')
                    ->label('Rentang waktu')
                    ->options(fn (
                        Get $get,
                        ToyotaServiceBooking $record,
                    ): array => $this->availableWindows($get, $record, 'proposed_date'))
                    ->required(),
                DateTimePicker::make('proposal_expires_at')
                    ->label('Batas respons pelanggan')
                    ->timezone('Asia/Jakarta')
                    ->maxDate(fn (ToyotaServiceBooking $record) => $record->confirmed_start_at
                        ?->copy()
                        ->subMinute())
                    ->helperText(
                        'Harus sebelum jadwal usulan dan, untuk jadwal ulang, sebelum jadwal lama.'
                    )
                    ->seconds(false)
                    ->required(),
                Textarea::make('proposal_reason')
                    ->label('Alasan usulan')
                    ->required()
                    ->minLength(5)
                    ->maxLength(1000),
                TextInput::make('pic_name')
                    ->label('Nama PIC')
                    ->required()
                    ->maxLength(120),
                Textarea::make('arrival_instructions')
                    ->label('Instruksi kedatangan')
                    ->required()
                    ->minLength(5)
                    ->maxLength(2000),
                TextInput::make('external_booking_number')
                    ->label('Nomor booking dealer')
                    ->maxLength(120),
            ])
            ->action(function (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) use ($action): void {
                $data['proposed_slot'] = [
                    'date' => $data['proposed_date'],
                    'time_window' => $data['proposed_window'],
                ];
                unset($data['proposed_date'], $data['proposed_window']);
                $this->execute($record, $action, $data, $service);
            });
    }

    private function reasonAction(ToyotaServiceAdminAction $action, string $label): Action
    {
        return $this->baseAction($action, $label)
            ->color('danger')
            ->requiresConfirmation()
            ->form([
                Select::make('reason_code')
                    ->label('Kode alasan')
                    ->options(collect(ToyotaServiceReasonCode::cases())->mapWithKeys(
                        fn (ToyotaServiceReasonCode $code): array => [
                            $code->value => $code->label(),
                        ]
                    ))
                    ->required(),
                Textarea::make('reason')
                    ->label('Penjelasan')
                    ->required()
                    ->minLength(5)
                    ->maxLength(1000),
            ])
            ->action(fn (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) => $this->execute($record, $action, $data, $service));
    }

    private function simpleAction(ToyotaServiceAdminAction $action, string $label): Action
    {
        return $this->baseAction($action, $label)
            ->requiresConfirmation()
            ->action(fn (
                ToyotaServiceBooking $record,
                ToyotaServiceBookingAdminService $service,
            ) => $this->execute($record, $action, [], $service));
    }

    private function completeAction(): Action
    {
        $action = ToyotaServiceAdminAction::Complete;

        return $this->baseAction($action, 'Selesaikan servis')
            ->color('success')
            ->requiresConfirmation()
            ->form([
                Textarea::make('note')
                    ->label('Catatan internal (opsional)')
                    ->maxLength(1000),
            ])
            ->action(fn (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) => $this->execute($record, $action, $data, $service));
    }

    private function verifyBenefitAction(): Action
    {
        $action = ToyotaServiceAdminAction::VerifyBenefit;

        return $this->baseAction($action, 'Verifikasi benefit')
            ->form([
                Select::make('benefit_type')
                    ->label('Benefit')
                    ->options(collect(VehicleBenefitType::cases())->mapWithKeys(
                        fn (VehicleBenefitType $type): array => [$type->value => $type->label()]
                    ))
                    ->required(),
                Select::make('benefit_status')
                    ->label('Status')
                    ->options(collect(VehicleBenefitStatus::cases())
                        ->reject(fn (VehicleBenefitStatus $status): bool => $status === VehicleBenefitStatus::Unknown)
                        ->mapWithKeys(
                            fn (VehicleBenefitStatus $status): array => [
                                $status->value => $status->label(),
                            ]
                        ))
                    ->required()
                    ->live(),
                Select::make('verification_source')
                    ->label('Sumber verifikasi')
                    ->options(collect(BenefitVerificationSource::cases())->mapWithKeys(
                        fn (BenefitVerificationSource $source): array => [
                            $source->value => $source->label(),
                        ]
                    ))
                    ->visible(fn (Get $get): bool => in_array(
                        $get('benefit_status'),
                        [
                            VehicleBenefitStatus::Active->value,
                            VehicleBenefitStatus::Inactive->value,
                        ],
                        true,
                    ))
                    ->required(fn (Get $get): bool => in_array(
                        $get('benefit_status'),
                        [
                            VehicleBenefitStatus::Active->value,
                            VehicleBenefitStatus::Inactive->value,
                        ],
                        true,
                    )),
                DateTimePicker::make('benefit_valid_until')
                    ->label('Berlaku hingga (WIB)')
                    ->timezone('Asia/Jakarta')
                    ->seconds(false),
                Textarea::make('benefit_notes')
                    ->label('Catatan verifikasi')
                    ->maxLength(1000),
            ])
            ->action(fn (
                ToyotaServiceBooking $record,
                array $data,
                ToyotaServiceBookingAdminService $service,
            ) => $this->execute($record, $action, $data, $service));
    }

    private function baseAction(ToyotaServiceAdminAction $action, string $label): Action
    {
        return Action::make($action->value)
            ->label($label)
            ->authorize(fn (ToyotaServiceBooking $record): bool => auth()
                ->user()
                ?->can('manage', $record) ?? false)
            ->visible(fn (ToyotaServiceBooking $record): bool => in_array(
                $action,
                $record->availableAdminActions(),
                true,
            ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function execute(
        ToyotaServiceBooking $record,
        ToyotaServiceAdminAction $action,
        array $data,
        ToyotaServiceBookingAdminService $service,
    ): void {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $service->execute($record, $actor, $action, $data);
            Notification::make()
                ->title($action->label().' berhasil')
                ->success()
                ->send();
            $this->refreshFormData([]);
        } catch (ToyotaServiceConflictException|ValidationException $exception) {
            Notification::make()
                ->title('Aksi belum dapat diproses')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<string, string> */
    private function availableWindows(
        Get $get,
        ToyotaServiceBooking $record,
        string $dateField,
    ): array {
        $date = $get($dateField);
        if (! is_string($date) || $date === '') {
            return [];
        }

        $availability = app(ToyotaServiceAvailabilityService::class)->availability(
            $record->serviceLocation,
            $record->serviceType,
            $record->fulfillment_type,
            $date,
            1,
        );

        return collect($availability['dates'][0]['time_windows'] ?? [])
            ->mapWithKeys(fn (string $window): array => [$window => $window])
            ->all();
    }

    /** @return array<string, string> */
    private function confirmationDateOptions(
        ToyotaServiceBooking $record,
        ToyotaServiceAdminAction $action,
    ): array {
        return collect($this->confirmationSlots($record, $action))
            ->mapWithKeys(fn (array $slot): array => [$slot['date'] => $slot['date']])
            ->all();
    }

    /** @return array<string, string> */
    private function confirmationWindowOptions(
        Get $get,
        ToyotaServiceBooking $record,
        ToyotaServiceAdminAction $action,
    ): array {
        $date = $get('confirmed_date');
        if (! is_string($date) || $date === '') {
            return [];
        }

        return collect($this->confirmationSlots($record, $action))
            ->where('date', $date)
            ->mapWithKeys(fn (array $slot): array => [
                $slot['time_window'] => $slot['time_window'],
            ])
            ->all();
    }

    /**
     * @return list<array{date: string, time_window: string}>
     */
    private function confirmationSlots(
        ToyotaServiceBooking $record,
        ToyotaServiceAdminAction $action,
    ): array {
        $pairs = $action === ToyotaServiceAdminAction::ConfirmReschedule
            ? [
                [$record->reschedule_primary_start_at, $record->reschedule_primary_end_at],
                [$record->reschedule_alternative_start_at, $record->reschedule_alternative_end_at],
            ]
            : [
                [$record->primary_start_at, $record->primary_end_at],
                [$record->alternative_start_at, $record->alternative_end_at],
            ];
        $timezone = $record->serviceLocation->timezone;
        $slots = [];

        foreach ($pairs as [$start, $end]) {
            if ($start === null || $end === null) {
                continue;
            }
            $localStart = $start->copy()->timezone($timezone);
            $localEnd = $end->copy()->timezone($timezone);
            $slots[] = [
                'date' => $localStart->toDateString(),
                'time_window' => $localStart->format('H:i').'-'.$localEnd->format('H:i'),
            ];
        }

        return $slots;
    }
}
