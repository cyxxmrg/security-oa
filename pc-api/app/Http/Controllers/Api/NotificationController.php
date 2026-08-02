<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\MarkReadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->boolean('unread')) $query->whereNull('read_at');
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate()]);
    }

    public function markAsRead(MarkReadRequest $request): JsonResponse
    {
        $notification = $request->user()->allNotifications()->where('id', $request->notification_id)->first();
        $notification?->update(['read_at' => now()]);
        return response()->json(['code' => 0, 'message' => '已标记为已读']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->notifications()->update(['read_at' => now()]);
        return response()->json(['code' => 0, 'message' => '全部已读']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => ['count' => $request->user()->notifications()->count()]]);
    }
}
