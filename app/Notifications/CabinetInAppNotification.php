<?php

namespace App\Notifications;

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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
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
}
