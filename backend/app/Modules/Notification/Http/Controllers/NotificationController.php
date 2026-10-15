<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\AppNotification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /** Notifications for the current user (own + company-wide), newest first. */
    public function index(): JsonResponse
    {
        return response()->json($this->scope()->orderByDesc('id')->limit(100)->get());
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => $this->scope()->whereNull('read_at')->count()]);
    }

    public function markRead(AppNotification $notification): JsonResponse
    {
        abort_unless($this->visible($notification), 404);
        $notification->forceFill(['read_at' => now()])->save();

        return response()->json(['message' => 'ok']);
    }

    public function markAllRead(): JsonResponse
    {
        $this->scope()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    private function scope()
    {
        $uid = auth()->id();

        return AppNotification::query()->where(function ($q) use ($uid) {
            $q->whereNull('user_id')->orWhere('user_id', $uid);
        });
    }

    private function visible(AppNotification $n): bool
    {
        return $n->user_id === null || $n->user_id === auth()->id();
    }
}
