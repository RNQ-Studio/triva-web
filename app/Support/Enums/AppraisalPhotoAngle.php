<?php

namespace App\Support\Enums;

enum AppraisalPhotoAngle: string
{
    case Front = 'front';
    case Rear = 'rear';
    case LeftSide = 'left_side';
    case RightSide = 'right_side';
    case DashboardOdometer = 'dashboard_odometer';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Depan',
            self::Rear => 'Belakang',
            self::LeftSide => 'Sisi kiri',
            self::RightSide => 'Sisi kanan',
            self::DashboardOdometer => 'Dashboard / odometer',
        };
    }
}
