<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PushNotificationService
{
    /**
     * Send a push notification to one or many users and persist a record.
     *
     * @param  User|Collection<int, User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function send(
        User|Collection $recipients,
        string $title,
        string $body,
        array $data = [],
        string $type = 'system',
    ): void {
        $users = $recipients instanceof User ? collect([$recipients]) : $recipients;

        foreach ($users as $user) {
            $payload = [
                ...$data,
                'type' => $type,
            ];
            $notification = Notification::create([
                'user_id' => $user->getKey(),
                'title' => $title,
                'body' => $body,
                'data' => $payload,
                'type' => $type,
            ]);

            SendPushNotificationJob::dispatch($notification)
                ->onQueue((string) config('appraisal.market_data.queue'));
        }
    }
}
