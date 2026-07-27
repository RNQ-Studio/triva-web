<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\CreditSimulation;
use App\Models\Notification;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreditSimulationNotificationService
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function followUpCreated(CreditSimulation $simulation): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $simulation->user_id,
            'title' => 'Permintaan follow-up diterima',
            'body' => "Permintaan {$simulation->reference_no} akan diteruskan ke tim sales.",
            'data' => [
                'type' => 'credit_simulation',
                'credit_simulation_id' => $simulation->getKey(),
                'status' => 'lead_created',
                'route' => '/credit/simulations/'.$simulation->getKey(),
            ],
            'type' => 'credit_simulation',
        ]);

        DB::afterCommit(function () use ($notification): void {
            try {
                $this->bus->dispatch(
                    new SendPushNotificationJob($notification)
                );
            } catch (Throwable $exception) {
                Log::error('Credit follow-up push dispatch failed', [
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
