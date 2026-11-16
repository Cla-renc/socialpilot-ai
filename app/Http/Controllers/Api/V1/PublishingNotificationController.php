<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublishingNotificationResource;
use App\Models\PublishingNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = PublishingNotification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread_only'), fn($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(PublishingNotificationResource::collection($notifications)->response()->getData(true));
    }

    public function markRead(PublishingNotification $notification, Request $request): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $notification->update(['read_at' => now()]);
        return response()->json(['message' => 'Marked as read.', 'data' => new PublishingNotificationResource($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        PublishingNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = PublishingNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
        return response()->json(['data' => ['unread_count' => $count]]);
    }
}
