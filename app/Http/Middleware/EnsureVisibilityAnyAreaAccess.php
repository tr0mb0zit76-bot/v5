<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVisibilityAnyAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $areasList): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        // Use "|" so Laravel does not treat "," as a delimiter between middleware parameters.
        $required = array_values(array_filter(array_map('trim', explode('|', $areasList))));

        $visibilityAreas = RoleAccess::userVisibilityAreas($user);

        foreach ($required as $area) {
            if (RoleAccess::hasVisibilityArea($visibilityAreas, $area)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
