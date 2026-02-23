<?php

namespace App\Support;

use App\Http\Services\TelegramNotificationService;
use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Notifier
{
    public function __construct(
        private readonly TelegramNotificationService $telegram,
    ) {}

    /**
     * @param iterable<int, User> $recipients
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toUsers(iterable $recipients, string $title, string $message, array $options = []): array
    {
        $users = collect($recipients)
            ->filter(fn ($user) => $user instanceof User)
            ->unique(fn (User $user) => (int) $user->id)
            ->values();

        $channels = $this->normalizeChannels((array) ($options['channels'] ?? ['in_app']));
        $level = in_array((string) ($options['level'] ?? 'info'), ['info', 'success', 'warning', 'error'], true)
            ? (string) $options['level']
            : 'info';
        $url = isset($options['url']) ? trim((string) $options['url']) : null;
        if ($url === '') {
            $url = null;
        }

        $inAppEnabled = in_array('in_app', $channels, true) && Schema::hasTable('notifications');
        $telegramEnabled = in_array('telegram', $channels, true);

        $inAppCount = 0;
        $telegramCount = 0;

        /** @var User $user */
        foreach ($users as $user) {
            if ($inAppEnabled && (bool) $user->receive_in_app_notifications) {
                $user->notify(new SystemBroadcastNotification(
                    title: $title,
                    message: $message,
                    level: $level,
                    url: $url,
                ));
                $inAppCount++;
            }

            if ($telegramEnabled && (bool) $user->receive_telegram_notifications && trim((string) $user->telegram_chat_id) !== '') {
                $sent = $this->telegram->sendMessage(
                    (string) $user->telegram_chat_id,
                    '<b>' . e($title) . '</b>' . PHP_EOL . e($message),
                );
                if ($sent) {
                    $telegramCount++;
                }
            }
        }

        return [
            'recipients' => $users->count(),
            'in_app' => $inAppCount,
            'telegram' => $telegramCount,
            'channels' => $channels,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toUser(User $user, string $title, string $message, array $options = []): array
    {
        return $this->toUsers([$user], $title, $message, $options);
    }

    /**
     * @param array<int, int|string> $userIds
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toIds(array $userIds, string $title, string $message, array $options = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($ids)) {
            return [
                'recipients' => 0,
                'in_app' => 0,
                'telegram' => 0,
                'channels' => $this->normalizeChannels((array) ($options['channels'] ?? ['in_app'])),
            ];
        }

        $users = User::query()->whereIn('id', $ids)->get();
        return $this->toUsers($users, $title, $message, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toRole(string $roleName, string $title, string $message, array $options = []): array
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return [
                'recipients' => 0,
                'in_app' => 0,
                'telegram' => 0,
                'channels' => $this->normalizeChannels((array) ($options['channels'] ?? ['in_app'])),
            ];
        }

        $query = User::query();
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $query->role($roleName);
        } else {
            $query->where('role', $roleName);
        }

        return $this->toUsers($query->get(), $title, $message, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toAdmins(string $title, string $message, array $options = []): array
    {
        return $this->toRole('admin', $title, $message, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{recipients:int,in_app:int,telegram:int,channels:array<int,string>}
     */
    public function toAll(string $title, string $message, array $options = []): array
    {
        return $this->toUsers(User::query()->get(), $title, $message, $options);
    }

    /**
     * @param array<int, string> $channels
     * @return array<int, string>
     */
    private function normalizeChannels(array $channels): array
    {
        $allowed = ['in_app', 'telegram'];
        $normalized = array_values(array_filter(array_map(
            fn ($channel) => trim((string) $channel),
            $channels
        ), fn ($channel) => in_array($channel, $allowed, true)));

        return !empty($normalized) ? array_values(array_unique($normalized)) : ['in_app'];
    }
}
