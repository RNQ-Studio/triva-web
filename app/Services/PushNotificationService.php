<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationEmailCopy;
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

        // Satu email salinan untuk seluruh penerima (broadcast), bukan satu per baris.
        $notificationIds = NotificationEmailCopy::withoutAutoDispatch(function () use ($users, $title, $body, $data, $type): array {
            $ids = [];

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
                $ids[] = (string) $notification->getKey();

                SendPushNotificationJob::dispatch($notification)
                    ->onQueue((string) config('appraisal.market_data.queue'));
            }

            return $ids;
        });

        NotificationEmailCopy::dispatchFor($notificationIds);
    }
}
