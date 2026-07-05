<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectExternalFromInternalRoutes
{
    /**
     * @var list<string>
     */
    private const ALLOWED_PATTERNS = [
        'mobile/login',
        'mobile/logout',
        'mobile/messenger',
        'mobile/messenger/*',
        'mobile/shell/counterparty/*',
        'mobile/pin-setup',
        'mobile/pin-unlock',
        'mobile/app-update',
        'messenger/*',
        'portal/*',
        'external/invite/*',
        'logout',
        'up',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isExternal()) {
            return $next($request);
        }

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, 'Доступ к CRM недоступен для внешних пользователей.');
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('mobile.messenger.app');
        }

        if ($request->is('mobile/*')) {
            abort(403, 'Раздел недоступен.');
        }

        return redirect()->route('mobile.messenger.app');
    }

    private function isAllowedPath(Request $request): bool
    {
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
