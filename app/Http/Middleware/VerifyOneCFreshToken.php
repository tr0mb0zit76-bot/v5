<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class VerifyOneCFreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->ensureAllowedIp($request);

        $expected = (string) config('epd.integration.one_c_fresh_token', '');
        $provided = (string) $request->header('X-Integration-Token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Integration token is invalid.');
        }

        $this->ensureValidSignature($request);

        return $next($request);
    }

    private function ensureAllowedIp(Request $request): void
    {
        $allowedIps = config('epd.integration.one_c_fresh_allowed_ips', []);

        if (! is_array($allowedIps) || $allowedIps === []) {
            return;
        }

        $ip = $request->ip();

        abort_unless(is_string($ip) && IpUtils::checkIp($ip, $allowedIps), 403);
    }

    private function ensureValidSignature(Request $request): void
    {
        $secret = (string) config('epd.integration.one_c_fresh_hmac_secret', '');
        $requireHmac = (bool) config('epd.integration.one_c_fresh_require_hmac', false);
        $providedSignature = trim((string) $request->header('X-Integration-Signature', ''));

        if (! $requireHmac && $providedSignature === '') {
            return;
        }

        if ($secret === '' || $providedSignature === '') {
            abort(401, 'Integration signature is invalid.');
        }

        $timestamp = trim((string) $request->header('X-Integration-Timestamp', ''));
        $nonce = trim((string) $request->header('X-Integration-Nonce', ''));
        $ttl = max(30, (int) config('epd.integration.one_c_fresh_signature_ttl_seconds', 300));

        if (! ctype_digit($timestamp)
            || abs(now()->timestamp - (int) $timestamp) > $ttl
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $nonce) !== 1) {
            abort(401, 'Integration signature is invalid.');
        }

        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            '/'.$request->path(),
            hash('sha256', $request->getContent()),
        ]);
        $expectedSignature = hash_hmac('sha256', $canonical, $secret);
        $normalizedProvided = str_starts_with($providedSignature, 'sha256=')
            ? substr($providedSignature, 7)
            : $providedSignature;

        if (! hash_equals($expectedSignature, strtolower($normalizedProvided))) {
            abort(401, 'Integration signature is invalid.');
        }

        $replayKey = 'onec-signature-replay:'.hash('sha256', $nonce);

        abort_unless(Cache::add($replayKey, true, now()->addSeconds($ttl)), 409, 'Integration request was already processed.');
    }
}
