<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShowcaseViteAssetOriginTest extends TestCase
{
    public function test_showcase_generates_urls_on_showcase_host_not_crm_app_url(): void
    {
        /** @var list<string> $showcaseHosts */
        $showcaseHosts = config('app.showcase_hosts', []);
        $showcaseHost = $showcaseHosts[0] ?? 'v5.local';
        $crmHost = (string) config('app.crm_domain');

        config([
            'app.url' => 'https://'.$crmHost,
        ]);

        $this->get('https://'.$showcaseHost.'/');

        $this->assertSame('https://'.$showcaseHost, rtrim(url('/'), '/'));
        $this->assertStringNotContainsString($crmHost, url('/'));
    }

    public function test_crm_host_keeps_app_url_as_root_for_generated_urls(): void
    {
        $crmHost = (string) config('app.crm_domain');

        config([
            'app.url' => 'https://'.$crmHost,
        ]);

        $this->get('https://'.$crmHost.'/login');

        $this->assertSame('https://'.$crmHost, rtrim(url('/'), '/'));
    }
}
