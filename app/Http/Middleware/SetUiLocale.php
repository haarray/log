<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetUiLocale
{
    /**
     * @var array<int, string>
     */
    private array $allowed = ['en', 'ne'];

    public function handle(Request $request, Closure $next): Response
    {
        $queryLocale = strtolower(trim((string) $request->query('lang', '')));
        if (in_array($queryLocale, $this->allowed, true)) {
            $request->session()->put('haarray.locale', $queryLocale);
        }

        $sessionLocale = strtolower(trim((string) $request->session()->get('haarray.locale', '')));
        $storedLocale = strtolower(trim(AppSettings::get('ui.locale', (string) config('app.locale', 'en'))));
        $locale = in_array($sessionLocale, $this->allowed, true)
            ? $sessionLocale
            : (in_array($storedLocale, $this->allowed, true) ? $storedLocale : 'en');

        app()->setLocale($locale);
        Carbon::setLocale($locale === 'ne' ? 'ne' : 'en');
        $request->attributes->set('haarray.locale', $locale);

        return $next($request);
    }
}

