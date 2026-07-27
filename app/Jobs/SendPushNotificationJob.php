<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\UserDevice;
use App\Services\Push\FcmDriverInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Notification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FcmDriverInterface $fcm): void
    {
        $user = $this->notification->user;
        if (! $user) {
            return;
        }

        $devices = UserDevice::query()
            ->where('user_id', $user->getKey())
            ->withPushToken()
            ->get();

        if ($devices->isEmpty()) {
            $this->notification->update([
                'sent_at' => now(),
                'failed_at' => null,
            ]);

            return;
        }

        $anySuccess = false;

        foreach ($devices as $device) {
            $success = $fcm->send(
                (string) $device->push_token,
                $this->notification->title,
                $this->notification->body,
                $this->notification->data ?: []
            );

            if (! $success) {
                // Invalid token — clear it to avoid future failed sends
                $device->update(['push_token' => null]);
            } else {
                $anySuccess = true;
            }
        }

        $this->notification->update($anySuccess
            ? ['sent_at' => now(), 'failed_at' => null]
            : ['failed_at' => now()]
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function failed(Throwable $exception): void
    {
        Notification::query()
            ->whereKey($this->notification->getKey())
            ->whereNull('sent_at')
            ->update(['failed_at' => now()]);
    }
}
