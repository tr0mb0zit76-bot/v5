<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExpireLegacyDomainCookies
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $legacyDomain = config('session.legacy_domain');

        if (! is_string($legacyDomain)
            || trim($legacyDomain) === ''
            || config('session.domain') !== null) {
            return $response;
        }

        $path = (string) config('session.path', '/');
        $legacySessionCookie = (string) config(
            'session.legacy_cookie',
            config('session.cookie'),
        );

        $response->headers->clearCookie(
            $legacySessionCookie,
            $path,
            $legacyDomain,
            true,
            true,
            'lax',
        );
        $response->headers->clearCookie(
            'XSRF-TOKEN',
            $path,
            $legacyDomain,
            true,
            false,
            'lax',
        );

        return $response;
    }
}
