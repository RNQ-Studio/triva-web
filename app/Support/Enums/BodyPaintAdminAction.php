<?php

namespace App\Support\Enums;

enum BodyPaintAdminAction: string
{
    case Assign = 'assign';
    case StartReview = 'start_review';
    case RequestPhotos = 'request_photos';
    case Publish = 'publish';
    case ScheduleInspection = 'schedule_inspection';

    public function label(): string
    {
        return match ($this) {
            self::Assign => 'Tetapkan estimator',
            self::StartReview => 'Mulai review',
            self::RequestPhotos => 'Minta foto tambahan',
            self::Publish => 'Terbitkan estimasi',
            self::ScheduleInspection => 'Jadwalkan inspeksi',
        };
    }
}
