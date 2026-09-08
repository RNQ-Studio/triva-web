<?php

namespace App\Support;

use App\Jobs\SendNotificationEmailCopyJob;
use Closure;

/**
 * Salinan email untuk notifikasi in-app/push.
 *
 * Observer model Notification memanggil dispatchFor() untuk setiap baris baru.
 * Service yang membuat banyak baris sekaligus (broadcast, fan-out admin)
 * membungkus pembuatannya dengan withoutAutoDispatch() lalu memanggil
 * dispatchFor() sekali supaya penerima menerima satu email ringkasan, bukan
 * satu email per baris.
 */
final class NotificationEmailCopy
{
    private static bool $autoDispatchSuspended = false;

    /** @return list<string> */
    public static function recipients(): array
    {
        $configured = config('notification_email.copy_recipients', []);
        $recipients = [];

        foreach ((array) $configured as $address) {
            $address = strtolower(trim((string) $address));

            if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[] = $address;
        }

        return array_values(array_unique($recipients));
    }

    public static function enabled(): bool
    {
        return self::recipients() !== [];
    }

    public static function queue(): string
    {
        return (string) config('notification_email.queue', 'triva');
    }

    /**
     * Antrekan satu email untuk kumpulan notifikasi. Dispatch ditunda sampai
     * transaksi database yang sedang berjalan di-commit.
     *
     * @param  list<string>  $notificationIds
     */
    public static function dispatchFor(array $notificationIds): void
    {
        $notificationIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => (string) $id, $notificationIds),
            static fn (string $id): bool => $id !== ''
        )));

        if ($notificationIds === [] || ! self::enabled()) {
            return;
        }

        SendNotificationEmailCopyJob::dispatch($notificationIds)
            ->onQueue(self::queue())
            ->afterCommit();
    }

    public static function autoDispatchSuspended(): bool
    {
        return self::$autoDispatchSuspended;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withoutAutoDispatch(Closure $callback): mixed
    {
        $previous = self::$autoDispatchSuspended;
        self::$autoDispatchSuspended = true;

        try {
            return $callback();
        } finally {
            self::$autoDispatchSuspended = $previous;
        }
    }
}
