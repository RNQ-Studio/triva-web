<?php

namespace App\Support\Enums;

enum BodyPaintEstimateStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case AutoEstimated = 'auto_estimated';
    case ManualReview = 'manual_review';
    case UnderEstimatorReview = 'under_estimator_review';
    case NeedsCustomerAction = 'needs_customer_action';
    case EstimateReady = 'estimate_ready';
    case BookingRequested = 'booking_requested';
    case InspectionScheduled = 'inspection_scheduled';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Terkirim',
            self::AutoEstimated => 'Estimasi awal tersedia',
            self::ManualReview => 'Menunggu review manual',
            self::UnderEstimatorReview => 'Sedang direview estimator',
            self::NeedsCustomerAction => 'Perlu tindakan Anda',
            self::EstimateReady => 'Estimasi tersedia',
            self::BookingRequested => 'Booking diminta',
            self::InspectionScheduled => 'Inspeksi dijadwalkan',
            self::Accepted => 'Diterima',
            self::Declined => 'Tidak dilanjutkan',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isCustomerEditable(): bool
    {
        return in_array($this, [self::Draft, self::NeedsCustomerAction], true);
    }

    public function exposesPublishedEstimate(): bool
    {
        return in_array($this, [
            self::EstimateReady,
            self::BookingRequested,
            self::InspectionScheduled,
            self::Accepted,
            self::Declined,
            self::Expired,
        ], true);
    }

    /** @return list<string> */
    public function customerActions(): array
    {
        return match ($this) {
            self::NeedsCustomerAction => ['resubmit'],
            self::EstimateReady => ['request_booking', 'accept', 'decline'],
            self::Accepted => ['request_booking'],
            default => [],
        };
    }
}
