<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !Schema::hasTable('notifications')) {
            return response()->json([
                'unread_count' => 0,
                'items' => [],
            ]);
        }

        $items = $user
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data ?? [];

                return [
                    'id'       => $notification->id,
                    'title'    => $data['title'] ?? 'Notification',
                    'message'  => $data['message'] ?? '',
                    'level'    => $data['level'] ?? 'info',
                    'url'      => $data['url'] ?? null,
                    'read'     => $notification->read_at !== null,
                    'time'     => optional($notification->created_at)->diffForHumans(),
                    'datetime' => optional($notification->created_at)?->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        if (!Schema::hasTable('notifications')) {
            return response()->json(['ok' => false, 'message' => 'Notifications table is missing.'], 404);
        }

        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['ok' => false, 'message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (!Schema::hasTable('notifications')) {
            return response()->json(['ok' => false, 'message' => 'Notifications table is missing.'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $unread = $user->unreadNotifications()->get();
        $count = $unread->count();
        if ($count > 0) {
            $unread->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'marked' => $count,
        ]);
    }

    public function broadcast(Request $request, Notifier $notifier): RedirectResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->can('manage notifications')) {
            abort(403, 'You are not allowed to broadcast notifications.');
        }

        $rawChannels = $request->input('channels', []);
        if (is_string($rawChannels)) {
            $rawChannels = [$rawChannels];
        }
        if (!is_array($rawChannels)) {
            $rawChannels = [];
        }
        $channels = array_values(array_unique(array_filter(array_map(
            fn ($channel) => trim((string) $channel),
            $rawChannels
        ))));
        if (empty($channels)) {
            $channels = ['in_app'];
        }
        $request->merge(['channels' => $channels]);

        $availableRoles = $this->availableRoleNames();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1200'],
            'level' => ['required', Rule::in(['info', 'success', 'warning', 'error'])],
            'url' => ['nullable', 'url', 'max:255'],
            'audience' => ['required', Rule::in(['all', 'admins', 'role', 'users'])],
            'role' => ['nullable', 'string', Rule::in($availableRoles)],
            'channels' => ['array', 'min:1'],
            'channels.*' => [Rule::in(['in_app', 'telegram'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $query = User::query();
        $audience = $validated['audience'];

        if ($audience === 'admins') {
            if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                $query->role('admin');
            } else {
                $query->where('role', 'admin');
            }
        } elseif ($audience === 'role') {
            $roleFilter = $validated['role'] ?? 'user';
            if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                $query->role($roleFilter);
            } else {
                $query->where('role', $roleFilter);
            }
        } elseif ($audience === 'users') {
            $ids = $validated['user_ids'] ?? [];
            if (empty($ids)) {
                return back()->with('error', 'Select at least one user for custom audience.');
            }
            $query->whereIn('id', $ids);
        }

        $recipients = $query->get();
        $channels = $validated['channels'] ?? ['in_app'];
        if (in_array('in_app', $channels, true) && !Schema::hasTable('notifications')) {
            return back()->with('error', 'Run migrations first to enable in-app notifications.');
        }

        $summary = $notifier->toUsers($recipients, (string) $validated['title'], (string) $validated['message'], [
            'level' => (string) $validated['level'],
            'url' => (string) ($validated['url'] ?? ''),
            'channels' => $channels,
        ]);

        return back()->with(
            'success',
            'Broadcast sent. In-app: ' . (int) $summary['in_app'] . ', Telegram: ' . (int) $summary['telegram'] . '.'
        );
    }

    /**
     * @return array<string>
     */
    private function availableRoleNames(): array
    {
        if (Schema::hasTable('roles')) {
            $roles = Role::query()->orderBy('name')->pluck('name')->all();
            if (!empty($roles)) {
                return $roles;
            }
        }

        return ['admin', 'manager', 'user'];
    }
}
