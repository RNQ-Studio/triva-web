<?php

namespace App\Filament\Resources\ToyotaServiceBookings\Pages;

use App\Filament\Resources\ToyotaServiceBookings\ToyotaServiceBookingResource;
use App\Models\ToyotaServiceBooking;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListToyotaServiceBookings extends ListRecords
{
    protected static string $resource = ToyotaServiceBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Kalender / jadwal harian')
                ->icon('heroicon-o-calendar-days')
                ->url(ToyotaServiceBookingResource::getUrl('schedule')),
            Action::make('exportDaily')
                ->label('Export CSV harian')
                ->icon('heroicon-o-arrow-down-tray')
                ->authorize(fn (): bool => auth()->user()?->can('service_bookings.viewAny') ?? false)
                ->form([
                    DatePicker::make('date')
                        ->label('Tanggal servis lokal')
                        ->default(now('Asia/Jakarta')->toDateString())
                        ->required(),
                ])
                ->action(fn (array $data): StreamedResponse => $this->dailyExport(
                    (string) $data['date']
                )),
        ];
    }

    private function dailyExport(string $date): StreamedResponse
    {
        $filename = "toyota-service-bookings-{$date}.csv";

        return response()->streamDownload(function () use ($date): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'reference_no',
                'status',
                'customer',
                'phone',
                'license_plate',
                'service_type',
                'fulfillment',
                'local_start',
                'local_end',
                'location',
                'advisor',
                'sla_due_at',
            ]);
            ToyotaServiceBooking::query()
                ->forLocalDate($date)
                ->with(['user', 'vehicle', 'serviceType', 'serviceLocation', 'assignedServiceAdvisor'])
                ->orderBy('active_slot_start_at')
                ->each(function (ToyotaServiceBooking $booking) use ($stream): void {
                    $timezone = $booking->serviceLocation->timezone;
                    fputcsv($stream, array_map($this->csvCell(...), [
                        $booking->reference_no,
                        $booking->status->value,
                        $booking->user->name,
                        $booking->user->phone,
                        $booking->vehicle->license_plate,
                        $booking->serviceType->name,
                        $booking->fulfillment_type->value,
                        $booking->active_slot_start_at->timezone($timezone)->toIso8601String(),
                        $booking->active_slot_end_at->timezone($timezone)->toIso8601String(),
                        $booking->serviceLocation->name,
                        $booking->assignedServiceAdvisor?->name,
                        $booking->due_at->toIso8601String(),
                    ]));
                });
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $cell) === 1 ? "'{$cell}" : $cell;
    }
}
