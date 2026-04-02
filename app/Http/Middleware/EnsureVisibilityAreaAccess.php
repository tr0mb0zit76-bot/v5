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

        $role = $user->role;
        $visibilityAreas = is_array($role?->visibility_areas)
            ? $role->visibility_areas
            : RoleAccess::defaultVisibilityAreas($role?->name);

        abort_unless(in_array($area, $visibilityAreas, true), 403);

        return $next($request);
    }
}
