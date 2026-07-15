<?php

namespace Tests\Feature;

use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class ExpireLegacyDomainCookiesTest extends TestCase
{
    public function test_parent_domain_session_and_xsrf_cookies_are_expired_after_host_only_switch(): void
    {
        config([
            'session.domain' => null,
            'session.legacy_domain' => '.avtoaliyans.ru',
            'session.legacy_cookie' => 'avtoalians-session',
        ]);

        $cookies = collect($this->get('/up')->assertOk()->headers->getCookies());

        $legacySession = $cookies->first(
            fn (Cookie $cookie): bool => $cookie->getName() === 'avtoalians-session'
                && $cookie->getDomain() === '.avtoaliyans.ru',
        );
        $legacyXsrf = $cookies->first(
            fn (Cookie $cookie): bool => $cookie->getName() === 'XSRF-TOKEN'
                && $cookie->getDomain() === '.avtoaliyans.ru',
        );

        $this->assertNotNull($legacySession);
        $this->assertNotNull($legacyXsrf);
        $this->assertLessThan(time(), $legacySession->getExpiresTime());
        $this->assertLessThan(time(), $legacyXsrf->getExpiresTime());
    }

    public function test_no_legacy_cookie_is_emitted_without_transition_domain(): void
    {
        config([
            'session.domain' => null,
            'session.legacy_domain' => null,
            'session.legacy_cookie' => 'avtoalians-session',
        ]);

        $cookies = collect($this->get('/up')->assertOk()->headers->getCookies());

        $this->assertFalse($cookies->contains(
            fn (Cookie $cookie): bool => $cookie->getDomain() === '.avtoaliyans.ru',
        ));
    }

    public function test_legacy_cookie_is_not_expired_before_host_only_switch(): void
    {
        config([
            'session.domain' => '.avtoaliyans.ru',
            'session.legacy_domain' => '.avtoaliyans.ru',
            'session.legacy_cookie' => 'avtoalians-session',
        ]);

        $cookies = collect($this->get('/up')->assertOk()->headers->getCookies());

        $this->assertFalse($cookies->contains(
            fn (Cookie $cookie): bool => $cookie->getName() === 'avtoalians-session'
                && $cookie->getExpiresTime() <= time(),
        ));
    }
}
