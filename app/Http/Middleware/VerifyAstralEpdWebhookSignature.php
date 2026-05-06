<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAstralEpdWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('epd.operator.webhook_secret', '');
        $provided = (string) $request->header('X-Epd-Signature', '');

        if ($secret === '' || $provided === '') {
            abort(401, 'Webhook signature is missing.');
        }

        $calculated = hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($calculated, $provided)) {
            abort(401, 'Webhook signature is invalid.');
        }

        return $next($request);
    }
}
