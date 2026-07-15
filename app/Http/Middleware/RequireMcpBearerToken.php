<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class RequireMcpBearerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $tokenModel = Sanctum::$personalAccessTokenModel;
        $accessToken = $plainTextToken === null ? null : $tokenModel::findToken($plainTextToken);

        if (! $accessToken
            || ! $accessToken->tokenable instanceof User
            || ($accessToken->expires_at !== null && $accessToken->expires_at->isPast())
            || ($expiration = config('sanctum.expiration'))
                && $accessToken->created_at->lte(now()->subMinutes((int) $expiration))) {
            return $this->unauthorized();
        }

        $user = $accessToken->tokenable->withAccessToken($accessToken);
        $accessToken->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()
            ->json(['message' => 'Unauthenticated.'], 401)
            ->header('WWW-Authenticate', 'Bearer');
    }
}
