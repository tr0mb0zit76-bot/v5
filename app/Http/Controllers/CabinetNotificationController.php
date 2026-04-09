<?php

namespace App\Http\Controllers;

use App\Support\CabinetNotificationBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class CabinetNotificationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! Schema::hasTable('notifications')) {
            return response()->json([
                'unread_count' => 0,
                'latest' => null,
                'badges' => [
                    'total' => 0,
                    'orders' => 0,
                    'tasks' => 0,
                ],
            ]);
        }

        $badges = CabinetNotificationBadges::unreadFor($user);
        $latest = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'unread_count' => $badges['total'],
            'latest' => $latest === null ? null : $this->serializeNotification($latest),
            'badges' => $badges,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! Schema::hasTable('notifications')) {
            return response()->json(['notifications' => []]);
        }

        $items = $user->notifications()
            ->limit(40)
            ->get()
            ->map(fn (DatabaseNotification $n): array => $this->serializeNotification($n));

        return response()->json(['notifications' => $items]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $model = $user->notifications()->where('id', $notification)->firstOrFail();
        $model->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (Schema::hasTable('notifications')) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'action_url' => (string) ($data['action_url'] ?? '#'),
            'kind' => (string) ($data['kind'] ?? ''),
        ];
    }
}
