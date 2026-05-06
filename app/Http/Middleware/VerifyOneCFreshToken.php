<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOneCFreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('epd.integration.one_c_fresh_token', '');
        $provided = (string) $request->header('X-Integration-Token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Integration token is invalid.');
        }

        return $next($request);
    }
}
