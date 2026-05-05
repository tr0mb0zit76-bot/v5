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

        $user->loadMissing('role');
        $visibilityAreas = RoleAccess::effectiveVisibilityAreasFromRolePayload($user->role?->name, $user->role?->visibility_areas);

        abort_unless(RoleAccess::hasVisibilityArea($visibilityAreas, $area), 403);

        return $next($request);
    }
}
