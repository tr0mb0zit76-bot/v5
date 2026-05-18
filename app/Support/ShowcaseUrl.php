<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Абсолютный URL главной витрины (другой хост, чем CRM).
 */
final class ShowcaseUrl
{
    public static function home(?Request $request = null): string
    {
        if (config('app.same_showcase_and_crm_host', false) && Route::has('public.home')) {
            return route('public.home');
        }

        /** @var list<string> $hosts */
        $hosts = config('app.showcase_hosts', []);
        $host = trim($hosts[0] ?? '');

        if ($host === '') {
            return Route::has('public.home') ? route('public.home') : '/';
        }

        $request ??= request();
        $secure = $request?->isSecure()
            ?? str_starts_with(strtolower((string) config('app.url')), 'https://');
        $scheme = $secure ? 'https' : 'http';

        return sprintf('%s://%s/', $scheme, $host);
    }
}
