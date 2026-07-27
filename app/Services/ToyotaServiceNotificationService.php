<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\ToyotaServiceBooking;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ToyotaServiceNotificationService
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function record(
        ToyotaServiceBooking $booking,
        string $title,
        string $body,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $booking->user_id,
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'toyota_service_booking',
                'booking_id' => $booking->getKey(),
                'status' => $booking->status->value,
                'route' => '/toyota-service/bookings/'.$booking->getKey(),
            ],
            'type' => 'toyota_service_booking',
        ]);

        DB::afterCommit(function () use ($notification): void {
            try {
                $this->bus->dispatch(new SendPushNotificationJob($notification));
            } catch (Throwable $exception) {
                // The domain mutation and in-app notification are already durable.
                // Queue transport failures are retried operationally and must not
                // turn a successful booking mutation into a false API failure.
                Log::error('Toyota service push dispatch failed', [
                    'notification_id' => $notification->getKey(),
                    'error' => $exception->getMessage(),
                ]);
                try {
                    Notification::query()
                        ->whereKey($notification->getKey())
                        ->whereNull('sent_at')
                        ->update(['failed_at' => now()]);
                } catch (Throwable $markingException) {
                    Log::error('Toyota service push failure state could not be persisted', [
                        'notification_id' => $notification->getKey(),
                        'error' => $markingException->getMessage(),
                    ]);
                }
            }
        });

        return $notification;
    }
}
