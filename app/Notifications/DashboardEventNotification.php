<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DashboardEventNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly ?string $actionUrl = null,
        private readonly ?int $brandId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'action_url' => $this->actionUrl,
            'brand_id' => $this->brandId,
        ];
    }
}
