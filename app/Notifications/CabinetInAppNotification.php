<?php

namespace App\Notifications;

use App\Notifications\Channels\NtfyChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CabinetInAppNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $actionUrl,
        public array $payload = [],
    ) {}

    /**
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldSendNtfy()) {
            $channels[] = NtfyChannel::class;
        }

        return $channels;
    }

    public function databaseType(object $notifiable): string
    {
        return 'cabinet';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'payload' => $this->payload,
        ];
    }

    /**
     * @return array{title: string, body: string, click: string, priority: int}
     */
    public function toNtfy(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'click' => url($this->actionUrl),
            'priority' => 4,
        ];
    }

    private function shouldSendNtfy(): bool
    {
        if (! config('notifications.ntfy_enabled', false)) {
            return false;
        }

        /** @var list<string> $ntfyKinds */
        $ntfyKinds = config('notifications.ntfy_kinds', config('notifications.approval_kinds', []));

        return in_array($this->kind, $ntfyKinds, true);
    }
}
