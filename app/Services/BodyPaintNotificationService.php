<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\BodyPaintEstimate;
use App\Models\Notification;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BodyPaintNotificationService
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function record(
        BodyPaintEstimate $estimate,
        string $title,
        string $body,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $estimate->user_id,
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'body_paint_estimate',
                'body_paint_estimate_id' => $estimate->getKey(),
                'status' => $estimate->status->value,
                'route' => '/body-paint/estimates/'.$estimate->getKey(),
            ],
            'type' => 'body_paint_estimate',
        ]);

        DB::afterCommit(function () use ($notification): void {
            try {
                $this->bus->dispatch(
                    new SendPushNotificationJob($notification),
                );
            } catch (Throwable $exception) {
                Log::error('Body & Paint push dispatch failed', [
                    'notification_id' => $notification->getKey(),
                    'exception' => $exception,
                ]);
                Notification::query()
                    ->whereKey($notification->getKey())
                    ->whereNull('sent_at')
                    ->update(['failed_at' => now()]);
            }
        });

        return $notification;
    }
}
