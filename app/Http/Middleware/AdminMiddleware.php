<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Простая проверка - можно заменить на свою логику
        if ($request->user() && $request->user()->email === 'admin@example.com') {
            return $next($request);
        }

        abort(403, 'Access denied. Admin rights required.');
    }
}
