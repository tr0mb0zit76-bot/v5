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

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $role = $user->role_id ? Role::query()->find($user->role_id) : null;
        $visibilityAreas = RoleAccess::userVisibilityAreas($user);

        abort_unless(RoleAccess::hasVisibilityArea($visibilityAreas, $area), 403);

        return $next($request);
    }
}
