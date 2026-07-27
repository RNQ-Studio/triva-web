<?php

namespace App\Support\Enums;

enum ToyotaServiceReasonCode: string
{
    case ScheduleUnavailable = 'schedule_unavailable';
    case CapacityFull = 'capacity_full';
    case ServiceUnavailable = 'service_unavailable';
    case OperationalIssue = 'operational_issue';
    case CustomerRequest = 'customer_request';
    case CustomerNoShow = 'customer_no_show';

    public function label(): string
    {
        return match ($this) {
            self::ScheduleUnavailable => 'Jadwal tidak tersedia',
            self::CapacityFull => 'Kapasitas penuh',
            self::ServiceUnavailable => 'Layanan tidak tersedia',
            self::OperationalIssue => 'Kendala operasional',
            self::CustomerRequest => 'Permintaan pelanggan',
            self::CustomerNoShow => 'Pelanggan tidak hadir',
        };
    }
}
