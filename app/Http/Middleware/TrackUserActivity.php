<?php

namespace App\Http\Middleware;

use App\Models\UserActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (!$user) {
            return $response;
        }

        if (!Schema::hasTable('user_activities')) {
            return $response;
        }

        if ($this->shouldSkip($request)) {
            return $response;
        }

        try {
            UserActivity::create([
                'user_id' => $user->id,
                'method' => strtoupper((string) $request->method()),
                'path' => '/' . ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) ($request->userAgent() ?? ''), 0, 1000),
                'meta' => [
                    'query' => $request->query(),
                    'status' => $response->getStatusCode(),
                ],
            ]);

        } catch (\Throwable $exception) {
            // Never block user flows on activity tracking failures.
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $path = trim($request->path(), '/');

        if ($path === 'up') {
            return true;
        }

        if ($request->is('ui/*')) {
            return true;
        }

        if (in_array($routeName, ['notifications.feed', 'notifications.read'], true)) {
            return true;
        }

        return false;
    }
}
