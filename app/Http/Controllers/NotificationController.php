<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app notification center (Module 12): list, mark-as-read and
 * mark-all-read for the logged-in user's own notifications.
 *
 * Notifications are delivered branch-targeted (payment requests to their
 * branch, approvals for their outlet, …). Strict branch isolation is kept
 * on read too: branch-tagged entries only surface for the matching branch,
 * while personal entries without a branch tag always stay visible. Super
 * Admin sees everything.
 */
class NotificationController extends Controller
{
    /**
     * Columns that the list can be sorted by are intentionally fixed:
     * notifications are always listed newest first.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $filter = in_array($request->string('filter')->toString(), ['all', 'unread'], true)
            ? $request->string('filter')->toString()
            : 'all';

        $notifications = NotificationService::scopeFor($request->user()->notifications(), $request->user())
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => NotificationService::scopeFor($request->user()->unreadNotifications(), $request->user())->count(),
            'filter' => $filter,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Mark one of the user's notifications as read.
     */
    public function read(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark every unread notification of the user as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->get()->each->markAsRead();

        return redirect()->route('notifications.index')
            ->with('success', 'All notifications marked as read.');
    }
}
