<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NtfyChannel
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('notifications.ntfy_enabled', false)) {
            return;
        }

        $baseUrl = (string) config('ntfy.base_url', '');
        if ($baseUrl === '') {
            return;
        }

        if (! method_exists($notification, 'toNtfy')) {
            return;
        }

        $topic = $notifiable->ntfy_topic ?? null;
        if (! is_string($topic) || $topic === '') {
            return;
        }

        /** @var array{title?: string, body?: string, click?: string, priority?: int}|null $message */
        $message = $notification->toNtfy($notifiable);

        if (! is_array($message)) {
            return;
        }

        $title = (string) ($message['title'] ?? '');
        $body = (string) ($message['body'] ?? '');

        if ($title === '' && $body === '') {
            return;
        }

        $request = Http::timeout((int) config('ntfy.timeout_seconds', 5))
            ->withHeaders(array_filter([
                'Title' => $title !== '' ? $title : null,
                'Click' => isset($message['click']) && is_string($message['click']) && $message['click'] !== ''
                    ? $message['click']
                    : null,
                'Priority' => isset($message['priority'])
                    ? (string) $message['priority']
                    : (string) config('ntfy.default_priority', 3),
            ]));

        try {
            $request->post($baseUrl.'/'.rawurlencode($topic), $body !== '' ? $body : $title);
        } catch (\Throwable $exception) {
            Log::warning('ntfy delivery failed', [
                'user_id' => $notifiable->id ?? null,
                'topic' => $topic,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
