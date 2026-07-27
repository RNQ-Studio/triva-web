<?php

namespace App\Services;

use App\Models\ToyotaServiceHoliday;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\ToyotaThsCoverage;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ToyotaServiceAvailabilityService
{
    /**
     * @return array{
     *     timezone: string,
     *     is_real_time: false,
     *     notice: string,
     *     lead_time_days: int,
     *     dates: list<array{
     *         date: string,
     *         is_requestable: bool,
     *         time_windows: list<string>,
     *         reason: string|null
     *     }>
     * }
     */
    public function availability(
        ToyotaServiceLocation $location,
        ToyotaServiceType $serviceType,
        ToyotaServiceFulfillmentType $fulfillmentType,
        ?string $fromDate = null,
        int $days = 14,
        ?string $city = null,
        ?float $latitude = null,
        ?float $longitude = null,
        bool $enforceLeadTime = true,
    ): array {
        $this->assertConfigurationSupports($location, $serviceType, $fulfillmentType);

        if (
            $fulfillmentType === ToyotaServiceFulfillmentType::Ths
            && $city !== null
            && $latitude !== null
            && $longitude !== null
        ) {
            $this->assertThsCoverage($location, $city, $latitude, $longitude);
        }

        $timezone = $location->timezone;
        $start = $fromDate === null
            ? now($timezone)->startOfDay()
            : Carbon::createFromFormat('!Y-m-d', $fromDate, $timezone);

        if ($start === null) {
            throw ValidationException::withMessages([
                'from_date' => ['Tanggal awal tidak valid.'],
            ]);
        }

        $lastDate = $start->copy()->addDays($days - 1);
        $holidays = ToyotaServiceHoliday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $lastDate->toDateString()])
            ->where(function ($query) use ($location): void {
                $query->whereNull('service_location_id')
                    ->orWhere('service_location_id', $location->getKey());
            })
            ->get()
            ->groupBy(fn (ToyotaServiceHoliday $holiday): string => $holiday->holiday_date->toDateString());

        $leadTimeDays = $serviceType->leadTimeDays($fulfillmentType);
        $nowLocal = now($timezone);
        $earliestDate = $nowLocal->copy()->startOfDay()->addDays($leadTimeDays);
        $dates = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset);
            $windows = $this->windowsForDate(
                $location,
                $date,
                $holidays->get($date->toDateString(), collect()),
            );
            $hadOperatingWindows = $windows !== [];
            $reason = null;

            if ($enforceLeadTime && $date->lt($earliestDate)) {
                $windows = [];
                $reason = "Minimum lead time H-{$leadTimeDays}.";
            } else {
                if ($date->isSameDay($nowLocal)) {
                    $windows = array_values(array_filter(
                        $windows,
                        function (string $window) use ($date, $nowLocal, $timezone): bool {
                            [$startTime] = explode('-', $window, 2);
                            $start = Carbon::createFromFormat(
                                '!Y-m-d H:i',
                                $date->toDateString().' '.$startTime,
                                $timezone,
                            );

                            return $start !== null && $start->gt($nowLocal);
                        },
                    ));
                }

                if ($windows === []) {
                    $reason = $hadOperatingWindows
                        ? 'Tidak ada rentang waktu yang tersisa pada tanggal ini.'
                        : 'Tidak beroperasi pada tanggal ini.';
                }
            }

            $dates[] = [
                'date' => $date->toDateString(),
                'is_requestable' => $windows !== [],
                'time_windows' => $windows,
                'reason' => $reason,
            ];
        }

        return [
            'timezone' => $timezone,
            'is_real_time' => false,
            'notice' => 'Waktu yang ditampilkan adalah preferensi dan belum mengunci slot.',
            'lead_time_days' => $leadTimeDays,
            'dates' => $dates,
        ];
    }

    /**
     * @param  array{date: string, time_window: string}  $slot
     * @return array{0: Carbon, 1: Carbon}
     */
    public function validateAndParseSlot(
        array $slot,
        ToyotaServiceLocation $location,
        ToyotaServiceType $serviceType,
        ToyotaServiceFulfillmentType $fulfillmentType,
        string $field,
        bool $enforceLeadTime = true,
    ): array {
        $this->assertConfigurationSupports($location, $serviceType, $fulfillmentType);

        [$startTime, $endTime] = explode('-', $slot['time_window'], 2);
        $start = Carbon::createFromFormat(
            '!Y-m-d H:i',
            $slot['date'].' '.$startTime,
            $location->timezone,
        );
        $end = Carbon::createFromFormat(
            '!Y-m-d H:i',
            $slot['date'].' '.$endTime,
            $location->timezone,
        );

        if (
            $start === null
            || $end === null
            || $start->format('Y-m-d H:i') !== $slot['date'].' '.$startTime
            || $end->format('Y-m-d H:i') !== $slot['date'].' '.$endTime
            || ! $start->lt($end)
        ) {
            throw ValidationException::withMessages([
                "{$field}.time_window" => ['Rentang waktu tidak valid.'],
            ]);
        }

        $leadTimeDays = $serviceType->leadTimeDays($fulfillmentType);
        $earliestDate = now($location->timezone)->startOfDay()->addDays($leadTimeDays);
        if (! $start->gt(now($location->timezone))) {
            throw ValidationException::withMessages([
                "{$field}.date" => ['Tanggal dan waktu harus berada di masa depan.'],
            ]);
        }

        if ($enforceLeadTime && $start->copy()->startOfDay()->lt($earliestDate)) {
            throw ValidationException::withMessages([
                "{$field}.date" => ["Tanggal harus memenuhi minimum lead time H-{$leadTimeDays}."],
            ]);
        }

        $windows = $this->windowsForDate(
            $location,
            $start,
            ToyotaServiceHoliday::query()
                ->whereDate('holiday_date', $start->toDateString())
                ->where(function ($query) use ($location): void {
                    $query->whereNull('service_location_id')
                        ->orWhere('service_location_id', $location->getKey());
                })
                ->get(),
        );

        if (! in_array($slot['time_window'], $windows, true)) {
            throw ValidationException::withMessages([
                "{$field}.time_window" => ['Waktu tidak dapat diminta pada tanggal tersebut.'],
            ]);
        }

        return [$start->utc(), $end->utc()];
    }

    public function assertThsCoverage(
        ToyotaServiceLocation $location,
        string $city,
        float $latitude,
        float $longitude,
    ): ToyotaThsCoverage {
        $coverage = ToyotaThsCoverage::query()
            ->where('service_location_id', $location->getKey())
            ->operational()
            ->get()
            ->first(fn (ToyotaThsCoverage $item): bool => Str::lower(trim($item->city))
                === Str::lower(trim($city)));

        if ($coverage === null || ! $coverage->containsCoordinates($latitude, $longitude)) {
            throw ValidationException::withMessages([
                'ths_city' => ['Alamat berada di luar cakupan Toyota Home Service yang aktif.'],
            ]);
        }

        return $coverage;
    }

    private function assertConfigurationSupports(
        ToyotaServiceLocation $location,
        ToyotaServiceType $serviceType,
        ToyotaServiceFulfillmentType $fulfillmentType,
    ): void {
        $today = now($location->timezone)->toDateString();
        $locationEffective = $location->is_active
            && $location->effective_from->toDateString() <= $today
            && ($location->effective_to === null || $location->effective_to->toDateString() >= $today);
        $typeEffective = $serviceType->is_active
            && $serviceType->effective_from->toDateString() <= $today
            && ($serviceType->effective_to === null || $serviceType->effective_to->toDateString() >= $today);
        $locationSupports = match ($fulfillmentType) {
            ToyotaServiceFulfillmentType::Workshop => $location->supports_workshop,
            ToyotaServiceFulfillmentType::Ths => $location->supports_ths
                && ToyotaThsCoverage::query()
                    ->where('service_location_id', $location->getKey())
                    ->operational()
                    ->exists(),
        };

        if (! $locationEffective || ! $typeEffective || ! $locationSupports || ! $serviceType->supports($fulfillmentType)) {
            throw ValidationException::withMessages([
                'fulfillment_type' => ['Kombinasi lokasi, layanan, dan cara servis tidak tersedia.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, ToyotaServiceHoliday>  $holidays
     * @return list<string>
     */
    private function windowsForDate(
        ToyotaServiceLocation $location,
        Carbon $date,
        Collection $holidays,
    ): array {
        $closed = $holidays->contains(
            fn (ToyotaServiceHoliday $holiday): bool => $holiday->is_closed
        );
        if ($closed) {
            return [];
        }

        $override = $holidays
            ->first(fn (ToyotaServiceHoliday $holiday): bool => $holiday->time_windows !== null)
            ?->time_windows;

        return $override ?? $location->timeWindowsFor($date);
    }
}
