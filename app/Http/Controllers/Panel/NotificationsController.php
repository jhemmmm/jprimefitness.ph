<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

class NotificationsController extends Controller
{
    public function index(): View
    {
        return view('panel.notifications');
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $filter = $data['filter'] ?? 'all';
        $perPage = (int) ($data['per_page'] ?? 20);

        $notifications = $request->user()
            ->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $notifications->setCollection(
            $notifications->getCollection()->map(
                fn (DatabaseNotification $notification) => $this->serializeNotification($notification)
            )
        );

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'notification' => $this->serializeNotification($notification->fresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? 'Notification'),
            'message' => (string) ($data['message'] ?? ''),
            'action_url' => $data['action_url'] ?? null,
            'type' => (string) ($data['type'] ?? $notification->type),
            'severity' => (string) ($data['severity'] ?? 'info'),
            'branch_id' => $data['branch_id'] ?? null,
            'branch_name' => $data['branch_name'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? $notification->created_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
