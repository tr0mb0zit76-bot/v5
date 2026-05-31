<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVisibilityAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        abort_unless(RoleAccess::canAccessVisibilityArea($user, $area), 403);

        return $next($request);
    }
}
