<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteLocaleSwitchTest extends TestCase
{
    public function test_guest_switching_locale_sets_cookie_and_redirects_back(): void
    {
        $host = config('app.showcase_hosts')[0];

        $response = $this->from(sprintf('http://%s/about', $host))
            ->get(sprintf('http://%s/locale/en', $host));

        $response->assertRedirect();
        $response->assertCookie('public_site_locale', 'en');
    }

    public function test_unknown_locale_returns_not_found(): void
    {
        $host = config('app.showcase_hosts')[0];

        $this->from(sprintf('http://%s/', $host))
            ->get(sprintf('http://%s/locale/de', $host))
            ->assertNotFound();
    }
}
