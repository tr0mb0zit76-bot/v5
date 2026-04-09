<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSettingsVisibilityAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $allowed = match ($scope) {
            'overview' => RoleAccess::canAccessSettingsOverview($user),
            'system' => RoleAccess::canAccessSettingsSystem($user),
            'motivation' => RoleAccess::canAccessSettingsMotivation($user),
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
