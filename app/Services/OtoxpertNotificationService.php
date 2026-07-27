<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\OtoxpertBooking;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OtoxpertNotificationService
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function record(
        OtoxpertBooking $booking,
        string $title,
        string $body,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $booking->user_id,
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'otoxpert_booking',
                'booking_id' => $booking->getKey(),
                'otoxpert_booking_id' => $booking->getKey(),
                'status' => $booking->status->value,
                'route' => '/otoxpert/bookings/'.$booking->getKey(),
            ],
            'type' => 'otoxpert_booking',
        ]);

        DB::afterCommit(function () use ($notification): void {
            try {
                $this->bus->dispatch(
                    new SendPushNotificationJob($notification)
                );
            } catch (Throwable $exception) {
                Log::error('OtoXpert push dispatch failed', [
                    'notification_id' => $notification->getKey(),
                    'exception' => $exception,
                ]);
                try {
                    Notification::query()
                        ->whereKey($notification->getKey())
                        ->whereNull('sent_at')
                        ->update(['failed_at' => now()]);
                } catch (Throwable $markingException) {
                    Log::error(
                        'OtoXpert push failure state could not be persisted',
                        [
                            'notification_id' => $notification->getKey(),
                            'exception' => $markingException,
                        ],
                    );
                }
            }
        });

        return $notification;
    }
}
