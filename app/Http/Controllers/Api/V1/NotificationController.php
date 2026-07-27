<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderByRaw('read_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Notification $notification): array => $this->format($notification));

        return ApiResponse::success($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(['count' => $user->notifications()->unread()->count()]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($notification->user_id !== $user->getKey()) {
            return ApiResponse::error('Not found.', 404, code: 'NOT_FOUND');
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success($this->format($notification->refresh()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->notifications()->unread()->update(['read_at' => now()]);

        return ApiResponse::success(null, 'All notifications marked as read');
    }

    /** @return array<string, mixed> */
    private function format(Notification $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'type' => $n->type,
            'is_read' => $n->isRead(),
            'read_at' => $n->read_at?->toIso8601String(),
            'sent_at' => $n->sent_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
