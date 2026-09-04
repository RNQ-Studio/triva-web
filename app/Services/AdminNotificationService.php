<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi in-app + push untuk seluruh admin cabang.
 *
 * Dipakai ketika aktivitas pelanggan perlu ditindaklanjuti staf (mis. simulasi
 * kredit dihitung), sesuai revisi 4 September 2026. Setiap admin mendapat
 * baris notifikasi sendiri supaya status dibaca tidak saling menimpa.
 */
class AdminNotificationService
{
    /** @var list<string> */
    public const ADMIN_ROLES = ['super-admin', 'admin'];

    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return list<Notification>
     */
    public function notify(string $type, string $title, string $body, array $data): array
    {
        $admins = User::role(self::ADMIN_ROLES)
            ->where('is_active', true)
            ->get();

        $notifications = [];
        foreach ($admins as $admin) {
            $notification = Notification::query()->create([
                'user_id' => $admin->getKey(),
                'title' => $title,
                'body' => $body,
                'data' => [...$data, 'type' => $type, 'audience' => 'admin'],
                'type' => $type,
            ]);
            $notifications[] = $notification;

            DB::afterCommit(function () use ($notification): void {
                try {
                    $this->bus->dispatch(new SendPushNotificationJob($notification));
                } catch (Throwable $exception) {
                    Log::error('Admin push dispatch failed', [
                        'notification_id' => $notification->getKey(),
                        'exception' => $exception,
                    ]);
                    Notification::query()
                        ->whereKey($notification->getKey())
                        ->whereNull('sent_at')
                        ->update(['failed_at' => now()]);
                }
            });
        }

        return $notifications;
    }
}
