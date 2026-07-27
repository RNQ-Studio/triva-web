<?php

namespace App\Filament\Resources\OtoxpertBookings\Pages;

use App\Exceptions\OtoxpertConflictException;
use App\Filament\Resources\OtoxpertBookings\OtoxpertBookingResource;
use App\Models\OtoxpertBooking;
use App\Models\User;
use App\Services\OtoxpertAvailabilityService;
use App\Services\OtoxpertBookingAdminService;
use App\Support\Enums\OtoxpertAdminAction;
use App\Support\Enums\OtoxpertReasonCode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class ViewOtoxpertBooking extends ViewRecord
{
    protected static string $resource = OtoxpertBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->assignAction(),
            $this->slotAction(OtoxpertAdminAction::Confirm),
            $this->slotAction(OtoxpertAdminAction::ConfirmReschedule),
            $this->slotAction(OtoxpertAdminAction::ProposeAlternative),
            $this->reasonAction(OtoxpertAdminAction::Reject),
            $this->simpleAction(OtoxpertAdminAction::CheckIn),
            $this->simpleAction(OtoxpertAdminAction::StartService),
            $this->simpleAction(OtoxpertAdminAction::Complete),
            $this->reasonAction(OtoxpertAdminAction::MarkNoShow),
            $this->reasonAction(OtoxpertAdminAction::Cancel),
        ];
    }

    private function assignAction(): Action
    {
        return $this->baseAction(OtoxpertAdminAction::Assign)
            ->form([
                Select::make('operator_id')
                    ->label('Operator')
                    ->options(fn () => User::permission(
                        'service_bookings.update'
                    )->where('is_active', true)->orderBy('name')->pluck(
                        'name',
                        'id',
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('internal_note')
                    ->label('Catatan internal')
                    ->maxLength(3000),
            ])
            ->action(fn (
                OtoxpertBooking $record,
                array $data,
                OtoxpertBookingAdminService $service,
            ) => $this->execute($record, $data, $service));
    }

    private function slotAction(OtoxpertAdminAction $action): Action
    {
        return $this->baseAction($action)
            ->form([
                DatePicker::make('slot_date')
                    ->label('Tanggal')
                    ->minDate(now())
                    ->live()
                    ->required(),
                Select::make('slot_window')
                    ->label('Rentang waktu')
                    ->options(fn (
                        Get $get,
                        OtoxpertBooking $record,
                    ): array => $this->windows($get, $record))
                    ->required(),
                TextInput::make('pic_name')->label('Nama PIC')->maxLength(255),
                Textarea::make('arrival_instructions')
                    ->label('Instruksi kedatangan')
                    ->maxLength(2000),
                TextInput::make('external_booking_number')
                    ->label('Nomor booking partner')
                    ->maxLength(100),
                Select::make('reason_code')
                    ->label('Alasan')
                    ->options($this->reasonOptions())
                    ->required($action === OtoxpertAdminAction::ProposeAlternative),
                Textarea::make('reason')
                    ->required($action === OtoxpertAdminAction::ProposeAlternative)
                    ->maxLength(1000),
                TextInput::make('quoted_price_min')
                    ->label('Harga minimum')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('quoted_price_max')
                    ->label('Harga maksimum')
                    ->numeric()
                    ->gte('quoted_price_min'),
                Select::make('quoted_price_type')
                    ->options(['from' => 'Mulai dari', 'range' => 'Rentang']),
                DatePicker::make('quoted_price_valid_until')
                    ->label('Harga berlaku sampai')
                    ->minDate(now()),
            ])
            ->action(function (
                OtoxpertBooking $record,
                array $data,
                OtoxpertBookingAdminService $service,
            ) use ($action): void {
                $data['action'] = $action->value;
                $data['slot'] = [
                    'date' => $data['slot_date'],
                    'time_window' => $data['slot_window'],
                ];
                unset($data['slot_date'], $data['slot_window']);
                $this->execute($record, $data, $service);
            });
    }

    private function reasonAction(OtoxpertAdminAction $action): Action
    {
        return $this->baseAction($action)
            ->color('danger')
            ->requiresConfirmation()
            ->form([
                Select::make('reason_code')
                    ->options($this->reasonOptions())
                    ->required(),
                Textarea::make('reason')->required()->minLength(5)->maxLength(1000),
                Textarea::make('internal_note')
                    ->label('Catatan internal')
                    ->maxLength(3000),
            ])
            ->action(function (
                OtoxpertBooking $record,
                array $data,
                OtoxpertBookingAdminService $service,
            ) use ($action): void {
                $data['action'] = $action->value;
                $this->execute($record, $data, $service);
            });
    }

    private function simpleAction(OtoxpertAdminAction $action): Action
    {
        return $this->baseAction($action)
            ->requiresConfirmation()
            ->action(function (
                OtoxpertBooking $record,
                OtoxpertBookingAdminService $service,
            ) use ($action): void {
                $this->execute(
                    $record,
                    ['action' => $action->value],
                    $service,
                );
            });
    }

    private function baseAction(OtoxpertAdminAction $action): Action
    {
        return Action::make($action->value)
            ->label($action->label())
            ->authorize(fn (OtoxpertBooking $record): bool => auth()
                ->user()
                ?->can('manage', $record) ?? false)
            ->visible(fn (OtoxpertBooking $record): bool => in_array(
                $action,
                $record->availableAdminActions(),
                true,
            ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function execute(
        OtoxpertBooking $record,
        array $data,
        OtoxpertBookingAdminService $service,
    ): void {
        /** @var User $actor */
        $actor = auth()->user();
        try {
            $service->execute($record, $actor, $data);
            Notification::make()
                ->title('Booking OtoXpert diperbarui')
                ->success()
                ->send();
            $this->refreshFormData([]);
        } catch (OtoxpertConflictException|ValidationException $exception) {
            Notification::make()
                ->title('Aksi belum dapat diproses')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<string, string> */
    private function windows(Get $get, OtoxpertBooking $record): array
    {
        $date = $get('slot_date');
        if (! is_string($date) || $date === '') {
            return [];
        }
        $availability = app(OtoxpertAvailabilityService::class)->availability(
            $record->workshop,
            $record->service,
            $date,
            1,
        );

        return collect($availability['dates'][0]['time_windows'] ?? [])
            ->mapWithKeys(fn (string $window): array => [$window => $window])
            ->all();
    }

    /** @return array<string, string> */
    private function reasonOptions(): array
    {
        return collect(OtoxpertReasonCode::cases())
            ->mapWithKeys(fn (OtoxpertReasonCode $reason): array => [
                $reason->value => $reason->label(),
            ])->all();
    }
}
