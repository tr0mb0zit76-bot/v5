<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrakloApkUrlTest extends TestCase
{
    public function test_public_site_normalizes_legacy_traklo_landing_path_to_apk_file(): void
    {
        config([
            'external_users.apk_url' => '/downloads/traklo',
            'app.crm_domain' => 'crm.example.test',
        ]);

        $host = config('app.showcase_hosts')[0] ?? 'v5.local';

        $this->get('http://'.$host.'/sla')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('publicSite.traklo_apk_url', 'http://crm.example.test/downloads/traklo.apk')
            );
    }
}
