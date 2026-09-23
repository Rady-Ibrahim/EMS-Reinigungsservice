<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AdminNotificationTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = AdminNotification::latest()
            ->when(
                $request->filled('type') && AdminNotificationTypeEnum::tryFrom($request->input('type')),
                fn($q) => $q->ofType(AdminNotificationTypeEnum::from($request->input('type')))
            )
            ->paginate(30);

        $types = AdminNotificationTypeEnum::cases();

        return view('admin.notifications.index', compact('notifications', 'types'));
    }

    public function show(AdminNotification $notification): View
    {
        return view('admin.notifications.show', compact('notification'));
    }

    public function markRead(AdminNotification $notification): RedirectResponse
    {
        $notification->update(['is_read' => true]);

        return back();
    }

    public function markUserRead(UserNotification $userNotification): RedirectResponse
    {
        $userNotification->markAsRead();

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        AdminNotification::unread()->update(['is_read' => true]);

        return back()->with('success', __('messages.success'));
    }
}