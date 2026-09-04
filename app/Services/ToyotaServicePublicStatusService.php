<?php

namespace App\Services;

use App\Exceptions\ToyotaServiceConflictException;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Support\Facades\DB;

/**
 * Pembaruan status booking lewat tautan publik bertoken.
 *
 * PIC cabang menerima tautan ini di pesan WhatsApp booking dan memakainya
 * tanpa login. Karena itu alur statusnya sengaja disederhanakan menjadi tiga
 * tahap yang dipahami cabang -- menunggu, diproses, selesai -- dan dipetakan
 * ke status internal yang sudah ada supaya aplikasi pelanggan, panel admin,
 * dan notifikasi tetap bekerja tanpa perubahan.
 */
class ToyotaServicePublicStatusService
{
    public const STAGE_WAITING = 'waiting';

    public const STAGE_PROCESSING = 'processing';

    public const STAGE_COMPLETED = 'completed';

    /** @var list<string> */
    public const STAGES = [
        self::STAGE_WAITING,
        self::STAGE_PROCESSING,
        self::STAGE_COMPLETED,
    ];

    public function __construct(
        private readonly ToyotaServiceNotificationService $notifications,
    ) {}

    public function stageOf(ToyotaServiceBooking $booking): string
    {
        return match ($booking->status) {
            ToyotaServiceBookingStatus::CheckedIn,
            ToyotaServiceBookingStatus::InService => self::STAGE_PROCESSING,
            ToyotaServiceBookingStatus::Completed => self::STAGE_COMPLETED,
            default => self::STAGE_WAITING,
        };
    }

    /**
     * Tahap yang masih bisa dituju dari status sekarang. Booking yang sudah
     * ditutup (ditolak, dibatalkan, kedaluwarsa, tidak hadir, selesai) tidak
     * bisa diubah lagi lewat tautan publik.
     *
     * @return list<string>
     */
    public function availableStages(ToyotaServiceBooking $booking): array
    {
        if ($booking->status->isTerminal()) {
            return [];
        }

        return match ($this->stageOf($booking)) {
            self::STAGE_WAITING => [self::STAGE_PROCESSING, self::STAGE_COMPLETED],
            self::STAGE_PROCESSING => [self::STAGE_COMPLETED],
            default => [],
        };
    }

    public function advance(ToyotaServiceBooking $booking, string $stage): ToyotaServiceBooking
    {
        return DB::transaction(function () use ($booking, $stage): ToyotaServiceBooking {
            /** @var ToyotaServiceBooking $locked */
            $locked = ToyotaServiceBooking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($stage, $this->availableStages($locked), true)) {
                throw new ToyotaServiceConflictException(
                    'Status booking tidak bisa diubah ke tahap tersebut.',
                );
            }

            match ($stage) {
                self::STAGE_PROCESSING => $this->startProcessing($locked),
                self::STAGE_COMPLETED => $this->complete($locked),
            };

            return $locked->refresh();
        }, 3);
    }

    private function startProcessing(ToyotaServiceBooking $booking): void
    {
        $booking->update([
            'status' => ToyotaServiceBookingStatus::InService,
            // Booking yang langsung dikerjakan dianggap dikonfirmasi pada
            // jadwal utama pelanggan supaya aplikasi menampilkan PIC dan
            // jadwal seperti booking yang dikonfirmasi lewat panel admin.
            'confirmed_at' => $booking->confirmed_at ?? now(),
            'confirmed_start_at' => $booking->confirmed_start_at ?? $booking->primary_start_at,
            'confirmed_end_at' => $booking->confirmed_end_at ?? $booking->primary_end_at,
            'active_slot_start_at' => $booking->active_slot_start_at ?? $booking->primary_start_at,
            'active_slot_end_at' => $booking->active_slot_end_at ?? $booking->primary_end_at,
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'service_started',
            'Servis dimulai',
            'Kendaraan sedang dikerjakan.',
        );
        $this->notifications->record(
            $booking,
            'Servis dimulai',
            "{$booking->reference_no}: Kendaraan sedang dikerjakan.",
        );
    }

    private function complete(ToyotaServiceBooking $booking): void
    {
        $booking->update([
            'status' => ToyotaServiceBookingStatus::Completed,
            'confirmed_at' => $booking->confirmed_at ?? now(),
            'completed_at' => now(),
            'last_status_changed_at' => now(),
        ]);
        $this->history(
            $booking,
            'service_completed',
            'Servis selesai',
            'Detail pekerjaan dan harga final mengikuti dokumen bengkel.',
        );
        $this->notifications->record(
            $booking,
            'Servis selesai',
            "Servis untuk {$booking->reference_no} telah selesai.",
        );
    }

    private function history(
        ToyotaServiceBooking $booking,
        string $event,
        string $title,
        string $description,
    ): void {
        $history = new ToyotaServiceBookingStatusHistory([
            'status' => $booking->status,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'user_visible' => true,
            'actor_type' => 'staff',
            'metadata' => ['source' => 'public_status_link'],
        ]);
        $history->booking()->associate($booking);
        $history->save();
    }
}
