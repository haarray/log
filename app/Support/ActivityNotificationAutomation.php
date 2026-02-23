<?php

namespace App\Support;

use App\Http\Services\TelegramNotificationService;
use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ActivityNotificationAutomation
{
    public function __construct(
        private readonly TelegramNotificationService $telegram,
    ) {
    }

    public function dispatch(Request $request, Response $response, User $actor): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $rules = $this->rules();
        if (empty($rules)) {
            return;
        }

        $method = strtoupper((string) $request->method());
        $routeName = (string) ($request->route()?->getName() ?? '');
        $path = '/' . ltrim((string) $request->path(), '/');
        $status = (int) $response->getStatusCode();

        $context = [
            'actor_name' => (string) $actor->name,
            'actor_email' => (string) $actor->email,
            'method' => $method,
            'path' => $path,
            'route_name' => $routeName !== '' ? $routeName : 'n/a',
            'status' => (string) $status,
            'ip' => (string) ($request->ip() ?? ''),
            'timestamp' => now()->toDateTimeString(),
        ];

        foreach ($rules as $rule) {
            if (!$this->matchesRule($rule, $context)) {
                continue;
            }

            if (!$this->acquireThrottle($rule, $actor->id, $routeName, $method)) {
                continue;
            }

            $title = $this->renderTemplate((string) ($rule['title_template'] ?? ''), $context);
            $message = $this->renderTemplate((string) ($rule['message_template'] ?? ''), $context);
            $level = $this->normalizeLevel((string) ($rule['level'] ?? 'info'));
            $channels = $this->normalizeChannels((array) ($rule['channels'] ?? ['in_app']));
            $targetUsers = $this->resolveAudience((array) ($rule['audience'] ?? []));

            if ($title === '' || $message === '' || $targetUsers->isEmpty()) {
                continue;
            }

            foreach ($targetUsers as $user) {
                if (in_array('in_app', $channels, true) && (bool) $user->receive_in_app_notifications) {
                    if (Schema::hasTable('notifications')) {
                        $user->notify(new SystemBroadcastNotification(
                            title: $title,
                            message: $message,
                            level: $level,
                            url: null,
                        ));
                    }
                }

                if (in_array('telegram', $channels, true) && (bool) $user->receive_telegram_notifications && (string) $user->telegram_chat_id !== '') {
                    $this->telegram->sendMessage(
                        (string) $user->telegram_chat_id,
                        "<b>{$title}</b>\n{$message}"
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rules(): array
    {
        $raw = trim((string) AppSettings::get('notifications.automation_rules_json', ''));
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $rules = [];
        foreach ($decoded as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (!($rule['active'] ?? false)) {
                continue;
            }
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, string> $context
     */
    private function matchesRule(array $rule, array $context): bool
    {
        $methods = array_values(array_filter(array_map(
            fn ($item) => strtoupper(trim((string) $item)),
            (array) ($rule['methods'] ?? [])
        )));
        if (!empty($methods) && !in_array(strtoupper((string) ($context['method'] ?? '')), $methods, true)) {
            return false;
        }

        $patterns = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            (array) ($rule['route_patterns'] ?? [])
        )));
        if (!empty($patterns)) {
            $routeName = (string) ($context['route_name'] ?? '');
            $path = (string) ($context['path'] ?? '');
            $match = false;

            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $routeName) || Str::is($pattern, ltrim($path, '/'))) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function acquireThrottle(array $rule, int $actorId, string $routeName, string $method): bool
    {
        $ruleId = trim((string) ($rule['id'] ?? ''));
        if ($ruleId === '') {
            $ruleId = sha1(json_encode($rule));
        }

        $window = max(10, min((int) ($rule['throttle_seconds'] ?? 60), 3600));
        $bucket = now()->format('YmdHi');
        $key = implode(':', ['haarray', 'notify-rule', $ruleId, $actorId, $routeName ?: 'n/a', $method, $bucket]);

        return Cache::add($key, '1', $window);
    }

    /**
     * @param array<int, string> $channels
     * @return array<int, string>
     */
    private function normalizeChannels(array $channels): array
    {
        $allowed = ['in_app', 'telegram'];
        $normalized = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $channels
        ), fn ($item) => in_array($item, $allowed, true)));

        return !empty($normalized) ? array_values(array_unique($normalized)) : ['in_app'];
    }

    /**
     * @param array<string, mixed> $audience
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveAudience(array $audience)
    {
        $type = trim((string) ($audience['type'] ?? 'admins')) ?: 'admins';
        $role = trim((string) ($audience['role'] ?? 'admin'));
        $ids = array_values(array_filter(array_map('intval', (array) ($audience['user_ids'] ?? [])), fn ($id) => $id > 0));

        $query = User::query();
        if ($type === 'users' && !empty($ids)) {
            return $query->whereIn('id', $ids)->get();
        }

        if ($type === 'role') {
            if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                return $query->role($role !== '' ? $role : 'admin')->get();
            }
            return $query->where('role', $role !== '' ? $role : 'admin')->get();
        }

        if ($type === 'all') {
            return $query->get();
        }

        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            return $query->role(['super-admin', 'admin'])->get();
        }

        return $query->whereIn('role', ['super-admin', 'admin'])->get();
    }

    /**
     * @param array<string, string> $context
     */
    private function renderTemplate(string $template, array $context): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($context) {
            $key = (string) ($matches[1] ?? '');
            return array_key_exists($key, $context) ? (string) $context[$key] : (string) ($matches[0] ?? '');
        }, $template) ?: $template;
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        return in_array($level, ['info', 'success', 'warning', 'error'], true) ? $level : 'info';
    }
}

