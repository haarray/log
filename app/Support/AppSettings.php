<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSettings
{
    private const CACHE_KEY = 'haarray.app.settings.v1';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (!self::tableReady()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 300, static function (): array {
            return AppSetting::query()
                ->orderBy('key')
                ->pluck('value', 'key')
                ->map(fn ($value) => (string) ($value ?? ''))
                ->all();
        });
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $value = trim((string) $all[$key]);
        return $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, string|null> $values
     */
    public static function putMany(array $values): void
    {
        if (!self::tableReady()) {
            return;
        }

        foreach ($values as $key => $value) {
            AppSetting::query()->updateOrCreate(
                ['key' => (string) $key],
                ['value' => $value !== null ? trim((string) $value) : null]
            );
        }

        self::forgetCache();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{display_name:string,brand_subtitle:string,brand_mark:string,logo_url:string,favicon_url:string,app_icon_url:string,theme_color:string}
     */
    public static function uiBranding(): array
    {
        $defaultName = (string) config('app.name', 'HariLog');
        $defaultSubtitle = 'by ' . ((string) config('haarray.brand_name', 'Haarray'));
        $defaultMark = (string) config('haarray.app_initial', 'H');

        return [
            'display_name' => self::get('ui.display_name', $defaultName),
            'brand_subtitle' => self::get('ui.brand_subtitle', $defaultSubtitle),
            'brand_mark' => self::get('ui.brand_mark', $defaultMark),
            'logo_url' => self::get('ui.logo_url', ''),
            'favicon_url' => self::get('ui.favicon_url', ''),
            'app_icon_url' => self::get('ui.app_icon_url', ''),
            'theme_color' => self::get('ui.theme_color', '#2f7df6'),
        ];
    }

    public static function resolveUiAsset(?string $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $raw) || str_starts_with($raw, 'data:')) {
            $parsedHost = strtolower((string) (parse_url($raw, PHP_URL_HOST) ?? ''));
            $appHost = strtolower((string) (parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? ''));
            $sameHost = $parsedHost !== '' && $appHost !== '' && $parsedHost === $appHost;
            $parsedPath = (string) (parse_url($raw, PHP_URL_PATH) ?? '');
            if ($sameHost && $parsedPath !== '' && str_contains($parsedPath, '/uploads/')) {
                $relative = ltrim($parsedPath, '/');
                if (!is_file(public_path($relative))) {
                    return '';
                }

                return url($relative);
            }

            return $raw;
        }

        $relative = ltrim($raw, '/');
        if (str_starts_with($relative, 'uploads/') && !is_file(public_path($relative))) {
            return '';
        }

        return url($relative);
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('app_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
