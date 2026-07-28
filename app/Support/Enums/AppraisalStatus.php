<?php

namespace App\Support\Enums;

enum AppraisalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case CollectingMarketData = 'collecting_market_data';
    case AutoEstimated = 'auto_estimated';
    case InsufficientComparables = 'insufficient_comparables';
    case UnderAppraiserReview = 'under_appraiser_review';
    case NeedsCustomerAction = 'needs_customer_action';
    case ResultReady = 'result_ready';
    case AcceptedByCustomer = 'accepted_by_customer';
    case RejectedByCustomer = 'rejected_by_customer';
    case InspectionScheduled = 'inspection_scheduled';
    case Converted = 'converted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Permintaan diterima',
            self::CollectingMarketData => 'Mencari data pembanding',
            self::AutoEstimated => 'Estimasi otomatis selesai',
            self::InsufficientComparables => 'Pemrosesan otomatis belum berhasil',
            self::UnderAppraiserReview => 'Pemrosesan otomatis dilanjutkan',
            self::NeedsCustomerAction => 'Perlu tindakan Anda',
            self::ResultReady => 'Hasil tersedia',
            self::AcceptedByCustomer => 'Harga diterima',
            self::RejectedByCustomer => 'Harga belum cocok',
            self::InspectionScheduled => 'Inspeksi dijadwalkan',
            self::Converted => 'Proses dilanjutkan',
            self::Expired => 'Hasil kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Pemrosesan belum berhasil',
        };
    }

    public function isCustomerEditable(): bool
    {
        return in_array($this, [self::Draft, self::NeedsCustomerAction], true);
    }
}
