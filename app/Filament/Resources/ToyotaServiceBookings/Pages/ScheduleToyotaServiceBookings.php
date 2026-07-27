<?php

namespace App\Filament\Resources\ToyotaServiceBookings\Pages;

use App\Filament\Resources\ToyotaServiceBookings\ToyotaServiceBookingResource;
use App\Models\ToyotaServiceBooking;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

class ScheduleToyotaServiceBookings extends Page
{
    protected static string $resource = ToyotaServiceBookingResource::class;

    protected string $view = 'filament.resources.toyota-service-bookings.pages.schedule';

    public string $date = '';

    /** @var array<string, mixed> */
    protected $queryString = [
        'date' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->date = $this->validDate($this->date)
            ? $this->date
            : now('Asia/Jakarta')->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = $this->selectedDate()->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = $this->selectedDate()->addDay()->toDateString();
    }

    public function today(): void
    {
        $this->date = now('Asia/Jakarta')->toDateString();
    }

    /** @return Collection<int, ToyotaServiceBooking> */
    public function getScheduleProperty(): Collection
    {
        return ToyotaServiceBooking::query()
            ->forLocalDate($this->selectedDate()->toDateString())
            ->with([
                'user',
                'vehicle',
                'serviceType',
                'serviceLocation',
                'assignedServiceAdvisor',
            ])
            ->orderBy('active_slot_start_at')
            ->get();
    }

    public function getHeading(): string
    {
        return 'Kalender Booking Toyota Service';
    }

    public function getFormattedDateProperty(): string
    {
        return $this->selectedDate()->translatedFormat('l, d F Y');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('queue')
                ->label('Kembali ke booking queue')
                ->icon('heroicon-o-list-bullet')
                ->url(ToyotaServiceBookingResource::getUrl('index')),
        ];
    }

    private function selectedDate(): Carbon
    {
        if (! $this->validDate($this->date)) {
            return now('Asia/Jakarta')->startOfDay();
        }

        return Carbon::createFromFormat('!Y-m-d', $this->date, 'Asia/Jakarta')
            ?: now('Asia/Jakarta')->startOfDay();
    }

    private function validDate(string $value): bool
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        } catch (Throwable) {
            return false;
        }

        return $date !== null && $date->format('Y-m-d') === $value;
    }
}
