<?php

namespace App\Services;

use App\Models\OtoxpertHoliday;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OtoxpertAvailabilityService
{
    /**
     * @return array{
     *     is_real_time: false,
     *     request_to_confirm: true,
     *     timezone: string,
     *     lead_time_days: int,
     *     notice: string,
     *     dates: list<array{
     *         date: string,
     *         is_requestable: bool,
     *         time_windows: list<string>,
     *         reason: string|null
     *     }>
     * }
     */
    public function availability(
        OtoxpertWorkshop $workshop,
        OtoxpertService $service,
        ?string $fromDate = null,
        int $days = 14,
    ): array {
        $this->assertCompatible($workshop, $service);
        $leadTime = $this->leadTimeDays($workshop, $service);
        $today = now($workshop->timezone)->startOfDay();
        $minimum = $today->copy()->addDays($leadTime);
        $start = $fromDate === null
            ? $minimum
            : Carbon::createFromFormat(
                'Y-m-d',
                $fromDate,
                $workshop->timezone,
            )->startOfDay();
        if ($start->lt($minimum)) {
            $start = $minimum;
        }

        $end = $start->copy()->addDays($days - 1);
        $holidays = OtoxpertHoliday::query()
            ->where('workshop_id', $workshop->getKey())
            ->whereBetween('holiday_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->get()
            ->keyBy(fn (OtoxpertHoliday $holiday): string => $holiday
                ->holiday_date
                ->toDateString());

        $dates = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset);
            /** @var OtoxpertHoliday|null $holiday */
            $holiday = $holidays->get($date->toDateString());
            $windows = $holiday !== null && ! $holiday->is_closed
                ? ($holiday->time_windows ?? [])
                : ($holiday === null ? $workshop->timeWindowsFor($date) : []);
            $reason = match (true) {
                $holiday?->is_closed === true => $holiday->name,
                $windows === [] => 'Bengkel tutup.',
                default => null,
            };
            $dates[] = [
                'date' => $date->toDateString(),
                'is_requestable' => $windows !== [],
                'time_windows' => array_values($windows),
                'reason' => $reason,
            ];
        }

        return [
            'is_real_time' => false,
            'request_to_confirm' => true,
            'timezone' => $workshop->timezone,
            'lead_time_days' => $leadTime,
            'notice' => 'Waktu yang dipilih adalah preferensi. Bengkel akan mengonfirmasi ketersediaannya.',
            'dates' => $dates,
        ];
    }

    /**
     * @param  array{date: string, time_window: string}  $slot
     * @return array{Carbon, Carbon}
     */
    public function validateAndParseSlot(
        array $slot,
        OtoxpertWorkshop $workshop,
        OtoxpertService $service,
        string $field,
    ): array {
        $this->assertCompatible($workshop, $service);
        $date = Carbon::createFromFormat(
            'Y-m-d',
            $slot['date'],
            $workshop->timezone,
        )->startOfDay();
        $minimum = now($workshop->timezone)
            ->startOfDay()
            ->addDays($this->leadTimeDays($workshop, $service));

        if ($date->lt($minimum)) {
            throw ValidationException::withMessages([
                "{$field}.date" => [
                    'Tanggal belum memenuhi lead time layanan.',
                ],
            ]);
        }

        $available = $this->availability(
            $workshop,
            $service,
            $date->toDateString(),
            1,
        )['dates'][0];
        if (! $available['is_requestable']
            || ! in_array($slot['time_window'], $available['time_windows'], true)) {
            throw ValidationException::withMessages([
                "{$field}.time_window" => [
                    'Waktu tidak tersedia untuk diminta pada bengkel ini.',
                ],
            ]);
        }

        [$from, $to] = explode('-', $slot['time_window'], 2);
        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$slot['date']} {$from}",
            $workshop->timezone,
        )->utc();
        $end = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$slot['date']} {$to}",
            $workshop->timezone,
        )->utc();
        if (! $end->gt($start)) {
            throw ValidationException::withMessages([
                "{$field}.time_window" => [
                    'Akhir waktu harus setelah awal waktu.',
                ],
            ]);
        }

        return [$start, $end];
    }

    private function assertCompatible(
        OtoxpertWorkshop $workshop,
        OtoxpertService $service,
    ): void {
        if (! $workshop->is_active || ! $service->is_active
            || ! $workshop->supportsService($service)) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'Layanan tidak tersedia pada workshop yang dipilih.',
                ],
            ]);
        }
    }

    private function leadTimeDays(
        OtoxpertWorkshop $workshop,
        OtoxpertService $service,
    ): int {
        $relation = $workshop->services()
            ->whereKey($service->getKey())
            ->wherePivot('is_active', true)
            ->first();

        return (int) (
            $relation?->pivot->getAttribute('lead_time_days')
            ?? $service->default_lead_time_days
        );
    }
}
