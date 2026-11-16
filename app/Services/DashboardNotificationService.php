<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\DashboardEventNotification;
use Illuminate\Database\Eloquent\Collection;

class DashboardNotificationService
{
    public function notify(
        User $user,
        string $title,
        string $description,
        ?int $brandId = null,
        ?string $actionUrl = null,
    ): void {
        $user->notify(new DashboardEventNotification($title, $description, $actionUrl, $brandId));
    }

    public function recent(User $user, int $limit = 20, ?int $brandId = null): Collection
    {
        return $user->notifications()
            ->when($brandId, fn ($query) => $query->where('data->brand_id', $brandId))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markRead(User $user, string $notificationId): object
    {
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }
}
