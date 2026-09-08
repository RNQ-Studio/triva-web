<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NotificationCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Notification>  $notifications
     */
    public function __construct(
        public Collection $notifications,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        $items = $this->items();

        return new Content(
            markdown: 'mail.notifications.copy',
            with: [
                'appName' => $this->appName(),
                'items' => $items,
                'isBroadcast' => $this->isBroadcast($items),
            ],
        );
    }

    public function subjectLine(): string
    {
        /** @var Notification $first */
        $first = $this->notifications->first();
        $count = $this->notifications->count();

        if ($count > 1) {
            return sprintf('[%s] %d notifikasi: %s', $this->appName(), $count, $first->title);
        }

        return sprintf('[%s] %s: %s', $this->appName(), self::typeLabel($first->type), $first->title);
    }

    /**
     * @return list<array{title: string, body: string, type_label: string, recipient_name: string, recipient_email: string, audience: string, route: string|null, created_at: string}>
     */
    public function items(): array
    {
        $timezone = (string) config('notification_email.timezone', 'Asia/Jakarta');

        return $this->notifications
            ->map(function (Notification $notification) use ($timezone): array {
                $data = $notification->data ?? [];
                $route = $data['route'] ?? null;

                return [
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'type_label' => self::typeLabel($notification->type),
                    'recipient_name' => $notification->user->name ?? '-',
                    'recipient_email' => $notification->user->email ?? '-',
                    'audience' => ($data['audience'] ?? null) === 'admin' ? 'Admin' : 'Pelanggan',
                    'route' => is_string($route) && $route !== '' ? $route : null,
                    'created_at' => $notification->created_at?->copy()->timezone($timezone)->format('d M Y H:i T') ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    public static function typeLabel(string $type): string
    {
        $labels = config('notification_email.type_labels', []);

        if (is_array($labels) && isset($labels[$type])) {
            return (string) $labels[$type];
        }

        return Str::of($type)->replace('_', ' ')->title()->toString();
    }

    /**
     * Broadcast: banyak penerima dengan judul dan isi yang sama persis.
     *
     * @param  list<array{title: string, body: string, type_label: string, recipient_name: string, recipient_email: string, audience: string, route: string|null, created_at: string}>  $items
     */
    private function isBroadcast(array $items): bool
    {
        if (count($items) < 2) {
            return false;
        }

        $signatures = array_unique(array_map(
            static fn (array $item): string => $item['title']."\0".$item['body'],
            $items
        ));

        return count($signatures) === 1;
    }

    private function appName(): string
    {
        return (string) config('app.name', 'TRIVA');
    }
}
