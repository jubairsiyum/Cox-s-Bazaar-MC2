<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app (database) notification with a title, body, link and type.
 * Used by NotificationService so every module renders alerts the same way.
 */
class GenericDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body = '',
        public ?string $url = null,
        public string $type = 'info',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: ?string, type: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'type' => $this->type,
        ];
    }
}
