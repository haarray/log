<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        $scriptName = str_replace('\\', '/', (string) $request->server('SCRIPT_NAME', ''));
        $basePath = Str::before($scriptName, '/index.php');

        if ($basePath === '/public') {
            $basePath = '';
        } elseif (Str::endsWith($basePath, '/public')) {
            $basePath = (string) Str::beforeLast($basePath, '/public');
        }

        $root = rtrim($request->getSchemeAndHttpHost() . $basePath, '/');
        URL::forceRootUrl($root);
    }
}
