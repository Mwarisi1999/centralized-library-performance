<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_type === $request->user()::class
            && (int) $notification->notifiable_id === $request->user()->id, 403);

        $notification->markAsRead();

        $target = data_get($notification->data, 'url');

        return is_string($target) && str_starts_with($target, url('/'))
            ? redirect()->to($target)
            : back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
