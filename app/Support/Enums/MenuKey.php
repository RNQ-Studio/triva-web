<?php

namespace App\Support\Enums;

/**
 * Menu pelanggan yang pemakaiannya dicatat untuk dashboard admin.
 *
 * Nilai baru boleh ditambahkan kapan saja; kolom penyimpanannya string biasa
 * supaya aplikasi yang lebih baru tidak tertolak oleh server yang belum
 * mengenal menu tersebut.
 */
enum MenuKey: string
{
    case Appraisal = 'appraisal';
    case ToyotaService = 'toyota_service';
    case Otoxpert = 'otoxpert';
    case Credit = 'credit';
    case BodyPaint = 'body_paint';
    case VehicleBenefit = 'vehicle_benefit';
    case MaintenanceEstimate = 'maintenance_estimate';
    case Promotion = 'promotion';
    case Notification = 'notification';
    case Profile = 'profile';

    public function label(): string
    {
        return match ($this) {
            self::Appraisal => 'Taksir Harga Mobil',
            self::ToyotaService => 'Booking Servis Toyota',
            self::Otoxpert => 'Booking OtoXpert',
            self::Credit => 'Simulasi Kredit',
            self::BodyPaint => 'Estimasi Body & Paint',
            self::VehicleBenefit => 'Cek No. Rangka',
            self::MaintenanceEstimate => 'Estimasi Biaya Servis',
            self::Promotion => 'Promo',
            self::Notification => 'Notifikasi',
            self::Profile => 'Profil',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Label ramah untuk kunci yang belum dikenal enum ini. */
    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label()
            ?? ucwords(str_replace('_', ' ', $value));
    }
}
