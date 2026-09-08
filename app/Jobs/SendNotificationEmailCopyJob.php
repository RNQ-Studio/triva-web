<?php

namespace App\Jobs;

use App\Mail\NotificationCopyMail;
use App\Models\Notification;
use App\Support\NotificationEmailCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mengirim satu email salinan untuk satu atau banyak notifikasi.
 *
 * Kegagalan email tidak pernah mengubah sent_at/failed_at notifikasi; kolom
 * tersebut milik jalur push Firebase.
 */
class SendNotificationEmailCopyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<string>  $notificationIds
     */
    public function __construct(
        public array $notificationIds,
    ) {}

    public function handle(): void
    {
        $recipients = NotificationEmailCopy::recipients();

        if ($recipients === []) {
            return;
        }

        $notifications = Notification::query()
            ->with('user')
            ->whereKey($this->notificationIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        Mail::to($recipients)->send(new NotificationCopyMail($notifications));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Notification email copy failed', [
            'notification_ids' => $this->notificationIds,
            'error' => $exception?->getMessage(),
        ]);
    }
}
