<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly DashboardNotificationService $notifications) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'unread_only' => ['sometimes', 'boolean'],
            'brand_id' => ['nullable', 'integer'],
        ]);

        $query = $request->user()->notifications();
        if (filter_var($validated['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('read_at');
        }
        if (isset($validated['brand_id'])) {
            $query->where('data->brand_id', (int) $validated['brand_id']);
            abort_unless(
                $request->user()->brands()->whereKey($validated['brand_id'])->exists(),
                403,
                'Unauthorized brand access.'
            );
        }

        $notifications = $query->latest()->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn ($notification) => $this->serialize($notification)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $this->notifications->markRead($request->user(), $id);

        return response()->json(['data' => $this->serialize($notification)]);
    }

    private function serialize($notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'Notification',
            'description' => $data['description'] ?? '',
            'brand_id' => isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            'action_url' => $data['action_url'] ?? null,
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
