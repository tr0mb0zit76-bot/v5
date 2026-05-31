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

        // Use "|" so Laravel does not treat "," as a delimiter between middleware parameters.
        $required = array_values(array_filter(array_map('trim', explode('|', $areasList))));

        abort_unless(RoleAccess::canAccessAnyVisibilityArea($user, $required), 403);

        return $next($request);
    }
}
