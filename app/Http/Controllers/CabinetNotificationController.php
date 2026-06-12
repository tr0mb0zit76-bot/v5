<?php

namespace App\Http\Controllers;

use App\Support\CabinetNotificationBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class CabinetNotificationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
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
        if ($user === null) {
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

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markUnread(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $model = $user->notifications()->where('id', $notification)->firstOrFail();
        $model->forceFill(['read_at' => null])->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->notifications()->where('id', $notification)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        /** @var array<string, mixed> $payload */
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return [
            'id' => $notification->id,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'action_url' => $this->normalizeActionUrl((string) ($data['action_url'] ?? '#')),
            'kind' => (string) ($data['kind'] ?? ''),
            'order_id' => isset($payload['order_id']) ? (int) $payload['order_id'] : null,
            'clipboard_summary' => (string) ($payload['clipboard_summary'] ?? ''),
        ];
    }

    private function normalizeActionUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '' || $trimmed === '#') {
            return '#';
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts)) {
            return $trimmed;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
